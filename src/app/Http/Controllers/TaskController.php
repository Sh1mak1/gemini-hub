<?php

namespace App\Http\Controllers;

use App\Enums\TaskCategory;
use App\Enums\TaskStatus;
use App\Models\FallbackTask;
use App\Models\Task;
use App\Services\Tasks\TaskListAssembler;
use App\Support\DisplayTime;
use App\Support\OperationLogger;
use App\Support\TodayDueTasksSlackDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(TaskListAssembler $assembler): Response
    {
        return Inertia::render('Tasks/Index', [
            'pendingTasks' => $assembler->pendingTasks(),
            'completedTasks' => $assembler->completedTasks(),
            'timelineTasks' => $assembler->timelineTasks(),
            'stats' => $assembler->stats(),
        ]);
    }

    public function show(string $source, int $id, TaskListAssembler $assembler): Response
    {
        $task = $this->findTask($source, $id);

        return Inertia::render('Tasks/Show', [
            'task' => $this->formatDetailTask($task, $source, $assembler),
            'categoryOptions' => $this->categoryOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function update(Request $request, string $source, int $id): RedirectResponse
    {
        $task = $this->findTask($source, $id);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'category' => ['required', Rule::enum(TaskCategory::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ]);

        $task->update($validated);

        OperationLogger::info('task.update', 'updated', [
            'task_id' => $task->id,
            'title' => $task->title,
            'category' => $task->category->value,
            'status' => $task->status->value,
            'source' => $source,
        ]);

        return redirect()->route('tasks.show', [
            'source' => $source,
            'id' => $task->id,
        ]);
    }

    public function complete(
        string $source,
        int $id,
        TodayDueTasksSlackDispatcher $todayDueTasksSlack,
    ): RedirectResponse {
        if ($source === 'ai') {
            $response = $this->completeAiTask($id);
        } elseif ($source === 'fallback') {
            $response = $this->completeFallbackTask($id);
        } else {
            abort(404);
        }

        $todayDueTasksSlack->dispatchNow();

        return $response;
    }

    private function completeAiTask(int $id): RedirectResponse
    {
        $task = Task::query()->findOrFail($id);

        if ($task->isCompleted()) {
            OperationLogger::info('task.complete', 'already_completed', [
                'task_id' => $task->id,
                'source' => 'ai',
            ]);

            return back();
        }

        $task->markCompleted();

        OperationLogger::info('task.complete', 'completed', [
            'task_id' => $task->id,
            'title' => $task->title,
            'category' => $task->category->value,
            'source' => 'ai',
        ]);

        return back();
    }

    private function completeFallbackTask(int $id): RedirectResponse
    {
        $task = FallbackTask::query()->findOrFail($id);

        if ($task->isCompleted()) {
            OperationLogger::info('task.complete', 'already_completed', [
                'task_id' => $task->id,
                'source' => 'fallback',
            ]);

            return back();
        }

        $task->markCompleted();

        OperationLogger::info('task.complete', 'completed', [
            'task_id' => $task->id,
            'title' => $task->title,
            'category' => $task->category->value,
            'source' => 'fallback',
        ]);

        return back();
    }

    private function findTask(string $source, int $id): Task|FallbackTask
    {
        if ($source === 'ai') {
            return Task::query()->findOrFail($id);
        }

        if ($source === 'fallback') {
            return FallbackTask::query()->findOrFail($id);
        }

        abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDetailTask(Task|FallbackTask $task, string $source, TaskListAssembler $assembler): array
    {
        return [
            ...$assembler->formatTask($task, $source),
            'source_label' => $source === 'ai' ? 'AI解析' : 'フォールバック',
            'raw_input' => $task instanceof FallbackTask ? $task->raw_input : null,
            'created_at' => DisplayTime::fromModelTimestamp($task, 'created_at')?->format('Y-m-d H:i'),
            'updated_at_display' => DisplayTime::fromModelTimestamp($task, 'updated_at')?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function categoryOptions(): array
    {
        return array_map(
            fn (TaskCategory $category) => [
                'value' => $category->value,
                'label' => $category->label(),
            ],
            TaskCategory::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (TaskStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            TaskStatus::cases(),
        );
    }
}

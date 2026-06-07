<?php

namespace App\Http\Controllers;

use App\Models\FallbackTask;
use App\Models\Task;
use App\Services\Tasks\TaskListAssembler;
use App\Support\OperationLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

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

    public function complete(string $source, int $id): RedirectResponse
    {
        if ($source === 'ai') {
            return $this->completeAiTask($id);
        }

        if ($source === 'fallback') {
            return $this->completeFallbackTask($id);
        }

        abort(404);
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
}

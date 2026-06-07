<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\OperationLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $pendingTasks = Task::query()
            ->pending()
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Task $task) => $this->formatTask($task));

        $completedTasks = Task::query()
            ->completed()
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (Task $task) => $this->formatTask($task));

        $timelineTasks = Task::query()
            ->where(function ($query) {
                $query->pending()
                    ->orWhere(function ($query) {
                        $query->completed()->where('updated_at', '>=', now()->subDays(30));
                    });
            })
            ->orderByRaw("CASE WHEN status = 'completed' THEN 1 ELSE 0 END")
            ->orderByRaw('due_date ASC NULLS LAST')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Task $task) => $this->formatTask($task));

        return Inertia::render('Tasks/Index', [
            'pendingTasks' => $pendingTasks,
            'completedTasks' => $completedTasks,
            'timelineTasks' => $timelineTasks,
            'stats' => [
                'pending' => $pendingTasks->count(),
                'completed' => Task::completed()->count(),
                'overdue' => Task::pending()
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', today())
                    ->count(),
            ],
        ]);
    }

    public function complete(Task $task): RedirectResponse
    {
        if ($task->isCompleted()) {
            OperationLogger::info('task.complete', 'already_completed', [
                'task_id' => $task->id,
                'source' => 'web',
            ]);

            return back();
        }

        $task->markCompleted();

        OperationLogger::info('task.complete', 'completed', [
            'task_id' => $task->id,
            'title' => $task->title,
            'category' => $task->category->value,
            'source' => 'web',
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTask(Task $task): array
    {
        $startDate = $task->created_at->startOfDay();
        $dueDate = $task->due_date?->startOfDay();
        $timelineEnd = $dueDate ?? $startDate->copy()->addDays(7);

        return [
            'id' => $task->id,
            'title' => $task->title,
            'due_date' => $dueDate?->format('Y-m-d'),
            'start_date' => $startDate->format('Y-m-d'),
            'timeline_end' => $timelineEnd->format('Y-m-d'),
            'category' => $task->category->value,
            'category_label' => $task->category->label(),
            'location' => $task->location,
            'status' => $task->status->value,
            'status_label' => $task->status->label(),
            'is_overdue' => $task->status->value === 'pending'
                && $dueDate !== null
                && $dueDate->lt(today()),
        ];
    }
}

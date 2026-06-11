<?php

namespace App\Services\Tasks;

use App\Models\FallbackTask;
use App\Models\Task;
use App\Support\DisplayTime;
use Illuminate\Support\Collection;

class TaskListAssembler
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function pendingTasks(): Collection
    {
        return $this->mergePendingCollections(
            Task::query()
                ->pending()
                ->orderByRaw('due_date is null')
                ->orderBy('due_date')
                ->orderBy('id')
                ->get(),
            FallbackTask::query()
                ->pending()
                ->orderByRaw('due_date is null')
                ->orderBy('due_date')
                ->orderBy('id')
                ->get(),
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function completedTasks(): Collection
    {
        return $this->mergeCompletedCollections(
            Task::query()
                ->completed()
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get(),
            FallbackTask::query()
                ->completed()
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get(),
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function timelineTasks(): Collection
    {
        $tasks = Task::query()
            ->pending()
            ->get()
            ->map(fn (Task $task) => $this->formatTask($task, 'ai'));

        $fallbackTasks = FallbackTask::query()
            ->pending()
            ->get()
            ->map(fn (FallbackTask $task) => $this->formatTask($task, 'fallback'));

        return $tasks
            ->merge($fallbackTasks)
            ->sortBy([
                fn (array $task) => $task['due_date'] ?? '9999-99-99',
                fn (array $task) => $task['updated_at'],
            ])
            ->values();
    }

    /**
     * @return array{pending: int, completed: int, overdue: int}
     */
    public function stats(): array
    {
        $pending = Task::pending()->count() + FallbackTask::pending()->count();
        $completed = Task::completed()->count() + FallbackTask::completed()->count();
        $overdue = Task::pending()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', DisplayTime::today())
            ->count()
            + FallbackTask::pending()
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', DisplayTime::today())
                ->count();

        return [
            'pending' => $pending,
            'completed' => $completed,
            'overdue' => $overdue,
        ];
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int, FallbackTask>  $fallbackTasks
     * @return Collection<int, array<string, mixed>>
     */
    private function mergePendingCollections(Collection $tasks, Collection $fallbackTasks): Collection
    {
        return $tasks
            ->map(fn (Task $task) => $this->formatTask($task, 'ai'))
            ->merge($fallbackTasks->map(fn (FallbackTask $task) => $this->formatTask($task, 'fallback')))
            ->sortBy([
                fn (array $task) => $task['due_date'] === null ? 1 : 0,
                fn (array $task) => $task['due_date'] ?? '9999-99-99',
                fn (array $task) => $task['source'],
                fn (array $task) => $task['id'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int, FallbackTask>  $fallbackTasks
     * @return Collection<int, array<string, mixed>>
     */
    private function mergeCompletedCollections(Collection $tasks, Collection $fallbackTasks): Collection
    {
        return $tasks
            ->map(fn (Task $task) => $this->formatTask($task, 'ai'))
            ->merge($fallbackTasks->map(fn (FallbackTask $task) => $this->formatTask($task, 'fallback')))
            ->sortByDesc(fn (array $task) => $task['updated_at'])
            ->take(50)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatTask(Task|FallbackTask $task, string $source): array
    {
        $startDate = DisplayTime::fromModelTimestamp($task, 'created_at')?->startOfDay();
        $dueDate = $task->due_date?->copy()->startOfDay();
        $timelineEnd = $dueDate ?? $startDate?->copy()->addDays(7);

        return [
            'id' => $task->id,
            'source' => $source,
            'title' => $task->title,
            'due_date' => $dueDate?->format('Y-m-d'),
            'start_date' => $startDate?->format('Y-m-d'),
            'timeline_end' => $timelineEnd?->format('Y-m-d'),
            'category' => $task->category->value,
            'category_label' => $task->category->label(),
            'location' => $task->location,
            'status' => $task->status->value,
            'status_label' => $task->status->label(),
            'is_overdue' => $task->status->value === 'pending'
                && $dueDate !== null
                && $dueDate->lt(DisplayTime::today()),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];
    }
}

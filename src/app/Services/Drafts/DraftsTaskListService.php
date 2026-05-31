<?php

namespace App\Services\Drafts;

use App\Data\DraftsTaskSnapshot;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DraftsTaskListService
{
    public function __construct(
        private DraftsTaskCache $cache,
    ) {}

    public function fetchAndCache(): string
    {
        $tasks = $this->todayPendingTasksQuery()->get();
        $snapshot = $this->buildSnapshot($tasks);

        $this->cache->store($snapshot);

        return $snapshot->text;
    }

    /**
     * @return Builder<Task>
     */
    private function todayPendingTasksQuery(): Builder
    {
        return Task::query()
            ->pending()
            ->where(function (Builder $query): void {
                $query->whereNull('due_date')
                    ->orWhereDate('due_date', '<=', today());
            })
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, Task>  $tasks
     */
    private function buildSnapshot(Collection $tasks): DraftsTaskSnapshot
    {
        if ($tasks->isEmpty()) {
            return new DraftsTaskSnapshot('（本日の未完了タスクはありません）', []);
        }

        $lines = [];
        $textLines = [];

        foreach ($tasks->values() as $index => $task) {
            $lineNumber = $index + 1;
            $lines[$lineNumber] = [
                'id' => $task->id,
                'title' => $task->title,
            ];

            $dueDate = $task->due_date?->format('Y-m-d') ?? '未設定';
            $location = $task->location ?? '未設定';
            $category = $task->category->label();

            $textLines[] = "{$lineNumber}. {$task->title}（{$category} / 期日: {$dueDate} / 場所: {$location}）";
        }

        return new DraftsTaskSnapshot(implode("\n", $textLines), $lines);
    }
}

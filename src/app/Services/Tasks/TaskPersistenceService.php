<?php

namespace App\Services\Tasks;

use App\Data\PersistedTaskReference;
use App\Data\TaskExtractionOutcome;
use App\Enums\TaskCategory;
use App\Enums\TaskStatus;
use App\Models\FallbackTask;
use App\Models\Task;
use App\Support\OperationLogger;

class TaskPersistenceService
{
    public function persist(TaskExtractionOutcome $outcome): PersistedTaskReference
    {
        if ($outcome->usedAi()) {
            $task = Task::create([
                ...$outcome->aiData->toTaskAttributes(),
                'status' => TaskStatus::Pending,
            ]);

            OperationLogger::info('task.persist', 'ai_created', [
                'task_id' => $task->id,
                'title' => $task->title,
            ]);

            return new PersistedTaskReference('ai', $task->id, $task);
        }

        $rawInput = trim((string) $outcome->fallbackRawInput);
        $title = $this->fallbackTitle($rawInput);

        $task = FallbackTask::create([
            'title' => $title,
            'raw_input' => $rawInput,
            'due_date' => null,
            'category' => TaskCategory::Other,
            'location' => null,
            'status' => TaskStatus::Pending,
        ]);

        OperationLogger::warning('task.persist', 'fallback_created', [
            'fallback_task_id' => $task->id,
            'title' => $task->title,
            'raw_input_preview' => mb_substr($rawInput, 0, 100),
        ]);

        return new PersistedTaskReference('fallback', $task->id, $task);
    }

    private function fallbackTitle(string $rawInput): string
    {
        $lines = preg_split('/\R/u', $rawInput) ?: [];
        $title = trim((string) ($lines[0] ?? $rawInput));

        if ($title === '') {
            $title = '無題のタスク';
        }

        return mb_substr($title, 0, 255);
    }
}

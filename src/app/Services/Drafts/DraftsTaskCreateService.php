<?php

namespace App\Services\Drafts;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\Gemini\TaskExtractionService;
use App\Support\OperationLogger;

class DraftsTaskCreateService
{
    public function __construct(
        private TaskExtractionService $taskExtraction,
    ) {}

    /**
     * @return list<int> Created task IDs
     */
    public function createFromInput(string $input): array
    {
        $lines = $this->parseLines($input);

        if ($lines === []) {
            return [];
        }

        $createdIds = [];

        foreach ($lines as $line) {
            $extracted = $this->taskExtraction->extract($line);

            if ($extracted === null) {
                OperationLogger::warning('drafts.add', 'line_skipped', [
                    'input_preview' => mb_substr($line, 0, 100),
                ]);

                continue;
            }

            $task = Task::create([
                ...$extracted->toTaskAttributes(),
                'status' => TaskStatus::Pending,
            ]);

            $createdIds[] = $task->id;

            OperationLogger::info('drafts.add', 'task_created', [
                'task_id' => $task->id,
                'title' => $task->title,
            ]);
        }

        return $createdIds;
    }

    /**
     * @return list<string>
     */
    private function parseLines(string $input): array
    {
        $lines = preg_split('/\R/u', trim($input)) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $line) => trim($line), $lines),
            static fn (string $line) => $line !== '',
        ));
    }
}

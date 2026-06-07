<?php

namespace App\Services\Drafts;

use App\Services\Gemini\TaskExtractionService;
use App\Services\Tasks\TaskPersistenceService;
use App\Support\OperationLogger;

class DraftsTaskCreateService
{
    public function __construct(
        private TaskExtractionService $taskExtraction,
        private TaskPersistenceService $taskPersistence,
    ) {}

    /**
     * @return list<string> Created task references (`ai:1`, `fallback:2`, ...)
     */
    public function createFromInput(string $input): array
    {
        $lines = $this->parseLines($input);

        if ($lines === []) {
            return [];
        }

        $createdRefs = [];

        foreach ($lines as $line) {
            $outcome = $this->taskExtraction->extract($line);

            if ($outcome === null) {
                continue;
            }

            $persisted = $this->taskPersistence->persist($outcome);
            $createdRefs[] = "{$persisted->source}:{$persisted->id}";

            OperationLogger::info('drafts.add', 'task_created', [
                'task_id' => $persisted->id,
                'task_source' => $persisted->source,
                'title' => $persisted->model->title,
                'used_ai' => $persisted->isAi(),
            ]);
        }

        return $createdRefs;
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

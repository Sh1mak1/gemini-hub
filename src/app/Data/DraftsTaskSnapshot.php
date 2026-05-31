<?php

namespace App\Data;

readonly class DraftsTaskSnapshot
{
    /**
     * @param  array<int, array{id: int, title: string}>  $lines
     */
    public function __construct(
        public string $text,
        public array $lines,
    ) {}

    public function taskIdForLine(int $lineNumber): ?int
    {
        return $this->lines[$lineNumber]['id'] ?? null;
    }

    /**
     * @return list<int>
     */
    public function taskIdsForLines(array $lineNumbers): array
    {
        $ids = [];

        foreach ($lineNumbers as $lineNumber) {
            if (! is_int($lineNumber)) {
                continue;
            }

            $taskId = $this->taskIdForLine($lineNumber);

            if ($taskId !== null) {
                $ids[] = $taskId;
            }
        }

        return array_values(array_unique($ids));
    }
}

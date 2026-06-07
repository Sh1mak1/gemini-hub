<?php

namespace App\Data;

use App\Models\FallbackTask;
use App\Models\Task;

readonly class PersistedTaskReference
{
    public function __construct(
        public string $source,
        public int $id,
        public Task|FallbackTask $model,
    ) {}

    public function isAi(): bool
    {
        return $this->source === 'ai';
    }
}

<?php

namespace App\Data;

readonly class TaskExtractionOutcome
{
    private function __construct(
        public ?ExtractedTaskData $aiData,
        public ?string $fallbackRawInput,
    ) {}

    public static function fromAi(ExtractedTaskData $data): self
    {
        return new self(aiData: $data, fallbackRawInput: null);
    }

    public static function fromFallback(string $rawInput): self
    {
        return new self(aiData: null, fallbackRawInput: $rawInput);
    }

    public function usedAi(): bool
    {
        return $this->aiData !== null;
    }
}

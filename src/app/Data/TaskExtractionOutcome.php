<?php

namespace App\Data;

readonly class TaskExtractionOutcome
{
    private function __construct(
        public ?ExtractedTaskData $aiData,
        public ?string $fallbackRawInput,
        public ?GeminiExtractionFailure $geminiFailure,
    ) {}

    public static function fromAi(ExtractedTaskData $data): self
    {
        return new self(aiData: $data, fallbackRawInput: null, geminiFailure: null);
    }

    public static function fromFallback(
        string $rawInput,
        ?GeminiExtractionFailure $geminiFailure = null,
    ): self {
        return new self(aiData: null, fallbackRawInput: $rawInput, geminiFailure: $geminiFailure);
    }

    public function usedAi(): bool
    {
        return $this->aiData !== null;
    }

    public function hadGeminiApiError(): bool
    {
        return $this->geminiFailure !== null;
    }
}

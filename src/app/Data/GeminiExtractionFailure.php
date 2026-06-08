<?php

namespace App\Data;

readonly class GeminiExtractionFailure
{
    public function __construct(
        public string $message,
        public ?int $status = null,
        public bool $retryable = false,
    ) {}
}

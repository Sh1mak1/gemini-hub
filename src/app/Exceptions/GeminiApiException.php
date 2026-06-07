<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class GeminiApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status,
        public readonly bool $retryable,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

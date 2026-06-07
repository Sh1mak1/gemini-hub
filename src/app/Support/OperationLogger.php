<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class OperationLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $operation, string $step, array $context = []): void
    {
        self::write('info', $operation, $step, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $operation, string $step, array $context = []): void
    {
        self::write('warning', $operation, $step, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function error(string $operation, string $step, array $context = []): void
    {
        self::write('error', $operation, $step, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function write(string $level, string $operation, string $step, array $context): void
    {
        Log::channel('operations')->{$level}("[{$operation}] {$step}", array_merge([
            'operation' => $operation,
            'step' => $step,
        ], $context));
    }
}

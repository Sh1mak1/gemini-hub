<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

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
        try {
            Log::channel('operations')->{$level}("[{$operation}] {$step}", array_merge([
                'operation' => $operation,
                'step' => $step,
            ], $context));
        } catch (Throwable $exception) {
            self::logWriteFailure($level, $operation, $step, $context, $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function logWriteFailure(
        string $level,
        string $operation,
        string $step,
        array $context,
        Throwable $exception,
    ): void {
        $payload = [
            'failed_at' => 'operations_channel_write',
            'intended_level' => $level,
            'operation' => $operation,
            'step' => $step,
            'operations_log_path' => config('logging.channels.operations.path'),
            'context_keys' => array_keys($context),
            'exception_class' => $exception::class,
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        try {
            Log::warning('[operation_logger] operations log write failed', $payload);
        } catch (Throwable $fallbackException) {
            try {
                Log::channel('errorlog')->warning('[operation_logger] operations log write failed', array_merge($payload, [
                    'fallback_log_failed' => true,
                    'fallback_exception_class' => $fallbackException::class,
                    'fallback_error' => $fallbackException->getMessage(),
                ]));
            } catch (Throwable) {
                // ログ基盤自体が使えない場合は握りつぶす（リクエストを 500 にしない）
            }
        }
    }
}

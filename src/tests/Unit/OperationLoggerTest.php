<?php

namespace Tests\Unit;

use App\Support\OperationLogger;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class OperationLoggerTest extends TestCase
{
    public function test_does_not_throw_when_operations_channel_fails(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('operations')
            ->andThrow(new RuntimeException('Permission denied'));

        Log::shouldReceive('warning')
            ->once()
            ->with('[operation_logger] operations log write failed', \Mockery::subset([
                'failed_at' => 'operations_channel_write',
                'intended_level' => 'info',
                'operation' => 'task.complete',
                'step' => 'completed',
                'exception_class' => RuntimeException::class,
                'error' => 'Permission denied',
                'context_keys' => ['task_id'],
            ]));

        OperationLogger::info('task.complete', 'completed', ['task_id' => 1]);

        $this->addToAssertionCount(1);
    }

    public function test_falls_back_to_errorlog_when_default_log_also_fails(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('operations')
            ->andThrow(new RuntimeException('Permission denied'));

        Log::shouldReceive('warning')
            ->once()
            ->andThrow(new RuntimeException('Default log unavailable'));

        Log::shouldReceive('channel')
            ->once()
            ->with('errorlog')
            ->andReturnSelf();

        Log::shouldReceive('warning')
            ->once()
            ->with('[operation_logger] operations log write failed', \Mockery::subset([
                'failed_at' => 'operations_channel_write',
                'fallback_log_failed' => true,
                'fallback_exception_class' => RuntimeException::class,
                'fallback_error' => 'Default log unavailable',
            ]));

        OperationLogger::info('task.complete', 'completed', ['task_id' => 1]);

        $this->addToAssertionCount(1);
    }
}

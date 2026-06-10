<?php

namespace Tests\Unit;

use App\Services\Operations\OperationLogReader;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OperationLogReaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearOperationLogs();
    }

    protected function tearDown(): void
    {
        $this->clearOperationLogs();
        parent::tearDown();
    }

    private function clearOperationLogs(): void
    {
        foreach (glob(storage_path('logs/operations-*.log')) ?: [] as $file) {
            File::delete($file);
        }
    }

    public function test_log_timestamps_are_converted_to_display_timezone(): void
    {
        $logPath = storage_path('logs/operations-2026-06-07.log');

        File::ensureDirectoryExists(dirname($logPath));
        File::put(
            $logPath,
            '[2026-06-07 21:56:21] production.INFO: [slack.job] task_created {"operation":"slack.job","step":"task_created"}'."\n",
        );

        $reader = new OperationLogReader;
        $entries = $reader->read('2026-06-08');

        $this->assertCount(1, $entries);
        $this->assertSame('2026-06-08 06:56:21', $entries[0]['timestamp']);
        $this->assertContains('2026-06-08', $reader->availableDates());
    }
}

<?php

namespace App\Support;

use App\Jobs\SyncTodayDueTasksSlackJob;
use Throwable;

class TodayDueTasksSlackDispatcher
{
    public function dispatch(): void
    {
        SyncTodayDueTasksSlackJob::dispatch();
    }

    public function dispatchNow(): void
    {
        try {
            SyncTodayDueTasksSlackJob::dispatchSync();
        } catch (Throwable $exception) {
            OperationLogger::error('slack.today', 'dispatch_failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

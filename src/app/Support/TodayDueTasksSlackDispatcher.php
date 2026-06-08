<?php

namespace App\Support;

use App\Jobs\SyncTodayDueTasksSlackJob;

class TodayDueTasksSlackDispatcher
{
    public function dispatch(): void
    {
        SyncTodayDueTasksSlackJob::dispatch();
    }

    public function dispatchNow(): void
    {
        SyncTodayDueTasksSlackJob::dispatchSync();
    }
}

<?php

namespace App\Jobs;

use App\Services\Slack\TodayDueTasksSlackService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncTodayDueTasksSlackJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public bool $forceScheduled = false,
    ) {}

    public function handle(TodayDueTasksSlackService $service): void
    {
        $service->sync($this->forceScheduled);
    }
}

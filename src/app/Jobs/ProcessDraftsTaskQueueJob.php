<?php

namespace App\Jobs;

use App\Services\Drafts\DraftsTaskQueueService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDraftsTaskQueueJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(DraftsTaskQueueService $queueService): void
    {
        $queueService->processPending();
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDraftsTaskQueueJob;
use Illuminate\Console\Command;

class ProcessDraftsTaskQueueCommand extends Command
{
    protected $signature = 'drafts:process-queue';

    protected $description = 'Process pending Drafts task queue items (Gemini extraction retries)';

    public function handle(): int
    {
        ProcessDraftsTaskQueueJob::dispatchSync();

        return self::SUCCESS;
    }
}

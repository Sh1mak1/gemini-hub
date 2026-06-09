<?php

namespace App\Console\Commands;

use App\Services\Slack\TodayDueTasksSlackService;
use Illuminate\Console\Command;

class PostTodayDueTasksCommand extends Command
{
    protected $signature = 'slack:post-today-tasks';

    protected $description = 'Post pending tasks due today to the today Slack channel';

    public function handle(TodayDueTasksSlackService $service): int
    {
        $service->sync(forceScheduled: true);

        $this->info('Today due tasks posted to Slack.');

        return self::SUCCESS;
    }
}

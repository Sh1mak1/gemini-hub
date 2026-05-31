<?php

namespace App\Services\Slack;

use App\Models\Task;
use Illuminate\Support\Facades\Log;

class SlackNotificationService
{
    public function __construct(
        private SlackApiClient $slackApi,
        private SlackChannelResolver $channelResolver,
    ) {}

    public function notifyTaskCreated(Task $task): void
    {
        $channelId = $this->channelResolver->resolveForCategory($task->category);

        if ($channelId === null) {
            Log::warning('Slack notification channel not found.', [
                'category' => $task->category->value,
            ]);

            return;
        }

        $dueDate = $task->due_date?->format('Y-m-d') ?? '未設定';
        $location = $task->location ?? '未設定';

        $message = implode("\n", [
            '✅ タスクを登録しました',
            "• タイトル: {$task->title}",
            "• 期日: {$dueDate}",
            "• カテゴリ: {$task->category->label()}",
            "• 実行場所: {$location}",
        ]);

        $this->slackApi->postMessage($channelId, $message);
    }
}

<?php

namespace App\Services\Slack;

use App\Models\Task;
use App\Support\OperationLogger;

class SlackNotificationService
{
    public function __construct(
        private SlackApiClient $slackApi,
        private SlackChannelResolver $channelResolver,
    ) {}

    public function notifyTaskCreated(Task $task, ?string $sourceChannelId = null): void
    {
        $channelId = $this->channelResolver->resolveForCategory($task->category)
            ?? $sourceChannelId;

        if ($channelId === null) {
            OperationLogger::warning('slack.notify', 'channel_not_found', [
                'category' => $task->category->value,
                'task_id' => $task->id,
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

        OperationLogger::info('slack.notify', 'sent', [
            'task_id' => $task->id,
            'channel_id' => $channelId,
            'category' => $task->category->value,
            'used_source_channel' => $sourceChannelId !== null && $channelId === $sourceChannelId,
        ]);
    }
}

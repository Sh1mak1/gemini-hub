<?php

namespace App\Services\Slack;

use App\Data\GeminiExtractionFailure;
use App\Models\FallbackTask;
use App\Models\Task;
use App\Support\OperationLogger;
use Throwable;

class SlackNotificationService
{
    public function __construct(
        private SlackApiClient $slackApi,
        private SlackChannelResolver $channelResolver,
    ) {}

    public function notifyTaskCreated(
        Task|FallbackTask $task,
        ?string $sourceChannelId = null,
        ?GeminiExtractionFailure $geminiFailure = null,
    ): void {
        $channelId = $this->channelResolver->resolveForCategory($task->category)
            ?? $sourceChannelId;

        if ($channelId === null) {
            OperationLogger::warning('slack.notify', 'channel_not_found', [
                'category' => $task->category->value,
                'task_id' => $task->id,
                'task_source' => $task instanceof FallbackTask ? 'fallback' : 'ai',
            ]);

            return;
        }

        $message = $this->buildTaskCreatedMessage($task, $geminiFailure);

        try {
            $this->slackApi->postMessage($channelId, $message);
        } catch (Throwable $exception) {
            OperationLogger::error('slack.notify', 'post_failed', [
                'task_id' => $task->id,
                'task_source' => $task instanceof FallbackTask ? 'fallback' : 'ai',
                'channel_id' => $channelId,
                'category' => $task->category->value,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        OperationLogger::info('slack.notify', 'sent', [
            'task_id' => $task->id,
            'task_source' => $task instanceof FallbackTask ? 'fallback' : 'ai',
            'channel_id' => $channelId,
            'category' => $task->category->value,
            'used_source_channel' => $sourceChannelId !== null && $channelId === $sourceChannelId,
            'gemini_error_status' => $geminiFailure?->status,
        ]);
    }

    private function buildTaskCreatedMessage(
        Task|FallbackTask $task,
        ?GeminiExtractionFailure $geminiFailure,
    ): string {
        $dueDate = $task->due_date?->format('Y-m-d') ?? '未設定';
        $location = $task->location ?? '未設定';
        $header = $task instanceof FallbackTask
            ? '⚠️ タスクを登録しました（AI未解析）'
            : '✅ タスクを登録しました';

        $lines = [$header];

        if ($geminiFailure !== null) {
            $lines[] = $this->formatGeminiError($geminiFailure);
        }

        $lines[] = "• タイトル: {$task->title}";
        $lines[] = "• 期日: {$dueDate}";
        $lines[] = "• カテゴリ: {$task->category->label()}";
        $lines[] = "• 実行場所: {$location}";

        return implode("\n", $lines);
    }

    private function formatGeminiError(GeminiExtractionFailure $failure): string
    {
        $status = $failure->status !== null ? (string) $failure->status : '不明';
        $retry = $failure->retryable ? ' / リトライ可' : '';
        $detail = mb_substr($failure->message, 0, 200);

        return "• Gemini API エラー: HTTP {$status}{$retry}\n  {$detail}";
    }
}

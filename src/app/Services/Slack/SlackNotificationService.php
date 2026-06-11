<?php

namespace App\Services\Slack;

use App\Data\GeminiExtractionFailure;
use App\Enums\TaskCategory;
use App\Models\FallbackTask;
use App\Models\Task;
use App\Support\DisplayTime;
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
        $channelIds = $this->resolveTargetChannelIds($task, $sourceChannelId);

        if ($channelIds === []) {
            OperationLogger::warning('slack.notify', 'channel_not_found', [
                'category' => $task->category->value,
                'task_id' => $task->id,
                'task_source' => $task instanceof FallbackTask ? 'fallback' : 'ai',
                'due_today' => $this->isDueToday($task),
            ]);

            return;
        }

        $message = $this->buildTaskCreatedMessage($task, $geminiFailure);

        foreach ($channelIds as $channelId) {
            $this->postToChannel($task, $channelId, $message, $sourceChannelId, $geminiFailure);
        }
    }

    /**
     * @return list<string>
     */
    private function resolveTargetChannelIds(
        Task|FallbackTask $task,
        ?string $sourceChannelId,
    ): array {
        $channelIds = [];

        $categoryChannelId = $this->channelResolver->resolveForCategory($task->category);

        if ($categoryChannelId !== null) {
            $channelIds[] = $categoryChannelId;
        } elseif (
            $task->category === TaskCategory::Other
            && $sourceChannelId !== null
        ) {
            $channelIds[] = $sourceChannelId;
        }

        if ($this->isDueToday($task)) {
            $todayChannelId = $this->channelResolver->resolveTodayChannel();

            if ($todayChannelId !== null) {
                $channelIds[] = $todayChannelId;
            }
        }

        return array_values(array_unique($channelIds));
    }

    private function isDueToday(Task|FallbackTask $task): bool
    {
        if ($task->due_date === null) {
            return false;
        }

        return $task->due_date->format('Y-m-d') === DisplayTime::today()->format('Y-m-d');
    }

    private function postToChannel(
        Task|FallbackTask $task,
        string $channelId,
        string $message,
        ?string $sourceChannelId,
        ?GeminiExtractionFailure $geminiFailure,
    ): void {
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
            'due_today' => $this->isDueToday($task),
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

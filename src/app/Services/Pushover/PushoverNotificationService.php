<?php

namespace App\Services\Pushover;

use App\Data\GeminiExtractionFailure;
use App\Models\FallbackTask;
use App\Models\Task;
use App\Support\OperationLogger;
use Throwable;

class PushoverNotificationService
{
    public function __construct(
        private PushoverClient $pushover,
    ) {}

    public function notifyTaskCreated(
        Task|FallbackTask $task,
        string $source,
        ?GeminiExtractionFailure $geminiFailure = null,
    ): void {
        if (! $this->pushover->isConfigured()) {
            return;
        }

        $title = $task instanceof FallbackTask
            ? 'タスク登録（AI未解析）'
            : 'タスク登録完了';
        $message = $this->buildTaskCreatedMessage($task, $source, $geminiFailure);
        $taskSource = $task instanceof FallbackTask ? 'fallback' : 'ai';

        try {
            $this->pushover->send($title, $message);
        } catch (Throwable $exception) {
            OperationLogger::error('pushover.notify', 'task_created_failed', [
                'task_id' => $task->id,
                'task_source' => $taskSource,
                'source' => $source,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        OperationLogger::info('pushover.notify', 'task_created_sent', [
            'task_id' => $task->id,
            'task_source' => $taskSource,
            'source' => $source,
            'gemini_error_status' => $geminiFailure?->status,
        ]);
    }

    public function notifyRegistrationFailed(
        string $source,
        string $inputPreview,
        GeminiExtractionFailure $failure,
    ): void {
        if (! $this->pushover->isConfigured()) {
            return;
        }

        $title = 'タスク登録失敗';
        $lines = [
            "• 経路: {$source}",
            "• 入力: {$inputPreview}",
            $this->formatGeminiError($failure),
        ];

        try {
            $this->pushover->send($title, implode("\n", $lines));
        } catch (Throwable $exception) {
            OperationLogger::error('pushover.notify', 'registration_failed_notify_error', [
                'source' => $source,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        OperationLogger::info('pushover.notify', 'registration_failed_sent', [
            'source' => $source,
            'gemini_error_status' => $failure->status,
        ]);
    }

    public function notifyDeployCompleted(string $commit, string $appUrl): void
    {
        if (! $this->pushover->isConfigured()) {
            return;
        }

        $title = 'gemini-hub デプロイ完了';
        $message = "Commit: {$commit}\nURL: {$appUrl}";

        try {
            $this->pushover->send($title, $message);
        } catch (Throwable $exception) {
            OperationLogger::error('pushover.notify', 'deploy_completed_failed', [
                'commit' => $commit,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        OperationLogger::info('pushover.notify', 'deploy_completed_sent', [
            'commit' => $commit,
        ]);
    }

    private function buildTaskCreatedMessage(
        Task|FallbackTask $task,
        string $source,
        ?GeminiExtractionFailure $geminiFailure,
    ): string {
        $dueDate = $task->due_date?->format('Y-m-d') ?? '未設定';
        $location = $task->location ?? '未設定';

        $lines = ["• 経路: {$source}"];

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

<?php

namespace App\Services\Drafts;

use App\Data\GeminiExtractionFailure;
use App\Models\DraftsTaskQueue;
use App\Services\Gemini\TaskExtractionService;
use App\Services\Pushover\PushoverNotificationService;
use App\Services\Slack\SlackNotificationService;
use App\Services\Tasks\TaskPersistenceService;
use App\Support\OperationLogger;
use App\Support\TodayDueTasksSlackDispatcher;

class DraftsTaskQueueService
{
    public function __construct(
        private TaskExtractionService $taskExtraction,
        private TaskPersistenceService $taskPersistence,
        private SlackNotificationService $slackNotification,
        private PushoverNotificationService $pushover,
        private TodayDueTasksSlackDispatcher $todayDueTasksSlack,
    ) {}

    public function enqueue(string $inputText): DraftsTaskQueue
    {
        $item = DraftsTaskQueue::query()->create([
            'input_text' => $inputText,
        ]);

        OperationLogger::info('drafts.queue', 'enqueued', [
            'queue_id' => $item->id,
            'input_preview' => mb_substr($inputText, 0, 100),
        ]);

        return $item;
    }

    public function processPending(): int
    {
        $processed = 0;

        DraftsTaskQueue::query()
            ->orderBy('id')
            ->each(function (DraftsTaskQueue $item) use (&$processed): void {
                if (! $this->isReadyForAttempt($item)) {
                    return;
                }

                if ($this->processItem($item)) {
                    $processed++;
                }
            });

        return $processed;
    }

    public function processItem(DraftsTaskQueue $item): bool
    {
        $item->update([
            'last_attempted_at' => now(),
        ]);

        $lines = $this->parseLines($item->input_text);

        if ($lines === []) {
            OperationLogger::warning('drafts.queue', 'empty_input_deleted', [
                'queue_id' => $item->id,
            ]);
            $item->delete();

            return true;
        }

        $hadGeminiApiError = false;
        $lastFailure = null;

        foreach ($lines as $line) {
            $outcome = $this->taskExtraction->extract($line);

            if ($outcome === null) {
                continue;
            }

            if ($outcome->hadGeminiApiError()) {
                $hadGeminiApiError = true;
                $lastFailure = $outcome->geminiFailure;

                continue;
            }

            $persisted = $this->taskPersistence->persist($outcome);

            $this->slackNotification->notifyTaskCreated(
                $persisted->model,
                geminiFailure: $outcome->geminiFailure,
            );

            $this->pushover->notifyTaskCreated(
                $persisted->model,
                'Drafts',
                geminiFailure: $outcome->geminiFailure,
            );

            OperationLogger::info('drafts.queue', 'task_created', [
                'queue_id' => $item->id,
                'task_id' => $persisted->id,
                'task_source' => $persisted->source,
                'title' => $persisted->model->title,
                'used_ai' => $persisted->isAi(),
            ]);
        }

        if ($hadGeminiApiError) {
            $this->recordFailedAttempt($item, $lastFailure, $item->input_text);

            return false;
        }

        $this->todayDueTasksSlack->dispatchNow();

        OperationLogger::info('drafts.queue', 'processed', [
            'queue_id' => $item->id,
        ]);

        $item->delete();

        return true;
    }

    public function isReadyForAttempt(DraftsTaskQueue $item): bool
    {
        if ($item->last_attempted_at === null) {
            return true;
        }

        $delayMinutes = $this->retryDelayMinutes($item->attempts);

        return $item->last_attempted_at
            ->copy()
            ->addMinutes($delayMinutes)
            ->lte(now());
    }

    private function recordFailedAttempt(
        DraftsTaskQueue $item,
        ?GeminiExtractionFailure $failure,
        string $inputText,
    ): void {
        $isFirstFailure = ($item->attempts ?? 0) === 0;

        $item->update([
            'attempts' => $item->attempts + 1,
            'last_error' => $failure?->message,
        ]);

        if ($isFirstFailure && $failure !== null) {
            $this->pushover->notifyRegistrationFailed(
                'Drafts',
                mb_substr($inputText, 0, 100),
                $failure,
            );
        }

        OperationLogger::warning('drafts.queue', 'gemini_failed', [
            'queue_id' => $item->id,
            'attempts' => $item->attempts + 1,
            'error' => $failure?->message,
            'status' => $failure?->status,
            'retryable' => $failure?->retryable,
            'next_attempt_after_minutes' => $this->retryDelayMinutes($item->attempts + 1),
        ]);
    }

    private function retryDelayMinutes(int $attempts): int
    {
        return match (true) {
            $attempts <= 0 => 0,
            $attempts === 1 => (int) config('drafts.queue_retry_delays_minutes.second', 2),
            $attempts === 2 => (int) config('drafts.queue_retry_delays_minutes.third', 5),
            $attempts === 3 => (int) config('drafts.queue_retry_delays_minutes.fourth', 15),
            default => (int) config('drafts.queue_retry_delays_minutes.max', 60),
        };
    }

    /**
     * @return list<string>
     */
    private function parseLines(string $input): array
    {
        $lines = preg_split('/\R/u', trim($input)) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $line) => trim($line), $lines),
            static fn (string $line) => $line !== '',
        ));
    }
}

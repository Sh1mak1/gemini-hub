<?php

namespace App\Jobs;

use App\Data\SlackIncomingEvent;
use App\Services\Gemini\TaskExtractionService;
use App\Services\Slack\SlackApiClient;
use App\Services\Slack\SlackEventExtractor;
use App\Services\Pushover\PushoverNotificationService;
use App\Services\Slack\SlackNotificationService;
use App\Services\Tasks\TaskPersistenceService;
use App\Support\OperationLogger;
use App\Support\TodayDueTasksSlackDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessSlackEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
    ) {}

    public function handle(
        SlackEventExtractor $extractor,
        SlackApiClient $slackApi,
        TaskExtractionService $taskExtraction,
        TaskPersistenceService $taskPersistence,
        SlackNotificationService $notification,
        PushoverNotificationService $pushover,
        TodayDueTasksSlackDispatcher $todayDueTasksSlack,
    ): void {
        $event = $extractor->extract($this->payload);

        if ($event === null) {
            OperationLogger::info('slack.job', 'ignored', [
                'type' => $this->payload['type'] ?? null,
                'event_type' => $this->payload['event']['type'] ?? null,
            ]);

            return;
        }

        $inputText = $this->resolveInputText($event, $slackApi);

        if ($inputText === null) {
            OperationLogger::info('slack.job', 'no_text', $this->eventContext($event));

            return;
        }

        OperationLogger::info('slack.job', 'extracting', [
            'channel_id' => $event->channelId,
            'input_preview' => mb_substr($inputText, 0, 100),
        ]);

        $outcome = $taskExtraction->extract($inputText);

        if ($outcome === null) {
            OperationLogger::warning('slack.job', 'empty_input', [
                'channel_id' => $event->channelId,
            ]);

            return;
        }

        $persisted = $taskPersistence->persist($outcome);

        $notification->notifyTaskCreated(
            $persisted->model,
            sourceChannelId: $event->channelId,
            geminiFailure: $outcome->geminiFailure,
        );

        $pushover->notifyTaskCreated(
            $persisted->model,
            'Slack',
            geminiFailure: $outcome->geminiFailure,
        );

        $todayDueTasksSlack->dispatchNow();

        OperationLogger::info('slack.job', 'task_created', [
            'task_id' => $persisted->id,
            'task_source' => $persisted->source,
            'title' => $persisted->model->title,
            'category' => $persisted->model->category->value,
            'used_ai' => $persisted->isAi(),
            'gemini_error_status' => $outcome->geminiFailure?->status,
        ]);
    }

    private function resolveInputText(SlackIncomingEvent $event, SlackApiClient $slackApi): ?string
    {
        if ($event->hasProcessableText()) {
            return $event->text;
        }

        if (! $event->isReaction() || $event->messageTs === null) {
            return null;
        }

        try {
            return $slackApi->fetchMessageText($event->channelId, $event->messageTs);
        } catch (Throwable $exception) {
            OperationLogger::error('slack.job', 'fetch_message_failed', [
                'channel_id' => $event->channelId,
                'message_ts' => $event->messageTs,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function eventContext(SlackIncomingEvent $event): array
    {
        return [
            'event_type' => $event->eventType,
            'channel_id' => $event->channelId,
            'user_id' => $event->userId,
            'message_ts' => $event->messageTs,
            'reaction' => $event->reaction,
            'has_text' => $event->hasProcessableText(),
        ];
    }
}

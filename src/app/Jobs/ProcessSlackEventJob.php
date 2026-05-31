<?php

namespace App\Jobs;

use App\Data\SlackIncomingEvent;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\Gemini\TaskExtractionService;
use App\Services\Slack\SlackApiClient;
use App\Services\Slack\SlackEventExtractor;
use App\Services\Slack\SlackNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
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
        SlackNotificationService $notification,
    ): void {
        $event = $extractor->extract($this->payload);

        if ($event === null) {
            Log::debug('Slack event ignored.', [
                'type' => $this->payload['type'] ?? null,
                'event_type' => $this->payload['event']['type'] ?? null,
            ]);

            return;
        }

        $inputText = $this->resolveInputText($event, $slackApi);

        if ($inputText === null) {
            Log::info('Slack event has no processable text.', $this->eventContext($event));

            return;
        }

        $extracted = $taskExtraction->extract($inputText);

        if ($extracted === null) {
            Log::warning('Failed to extract task from Slack input.', [
                'channel_id' => $event->channelId,
                'input_preview' => mb_substr($inputText, 0, 100),
            ]);

            return;
        }

        $task = Task::create([
            ...$extracted->toTaskAttributes(),
            'status' => TaskStatus::Pending,
        ]);

        $notification->notifyTaskCreated($task);

        Log::info('Task created from Slack event.', [
            'task_id' => $task->id,
            'category' => $task->category->value,
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
            Log::error('Failed to fetch Slack message for reaction.', [
                'channel_id' => $event->channelId,
                'message_ts' => $event->messageTs,
                'message' => $exception->getMessage(),
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

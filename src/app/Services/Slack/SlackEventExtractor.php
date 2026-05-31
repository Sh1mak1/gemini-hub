<?php

namespace App\Services\Slack;

use App\Data\SlackIncomingEvent;

class SlackEventExtractor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function extract(array $payload): ?SlackIncomingEvent
    {
        if (($payload['type'] ?? null) !== 'event_callback') {
            return null;
        }

        $event = $payload['event'] ?? [];

        if (! is_array($event)) {
            return null;
        }

        return match ($event['type'] ?? null) {
            'message' => $this->fromMessage($event, $payload),
            'reaction_added' => $this->fromReaction($event, $payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $payload
     */
    private function fromMessage(array $event, array $payload): ?SlackIncomingEvent
    {
        if (isset($event['subtype']) || isset($event['bot_id']) || isset($event['app_id'])) {
            return null;
        }

        $text = $event['text'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            return null;
        }

        $channelId = $event['channel'] ?? null;

        if (! is_string($channelId)) {
            return null;
        }

        return new SlackIncomingEvent(
            eventType: 'message',
            text: trim($text),
            channelId: $channelId,
            userId: is_string($event['user'] ?? null) ? $event['user'] : null,
            messageTs: is_string($event['ts'] ?? null) ? $event['ts'] : null,
            reaction: null,
            rawEvent: $event,
            rawPayload: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $payload
     */
    private function fromReaction(array $event, array $payload): ?SlackIncomingEvent
    {
        $channelId = $event['item']['channel'] ?? null;
        $messageTs = $event['item']['ts'] ?? null;
        $reaction = $event['reaction'] ?? null;

        if (! is_string($channelId) || ! is_string($messageTs) || ! is_string($reaction)) {
            return null;
        }

        return new SlackIncomingEvent(
            eventType: 'reaction_added',
            text: null,
            channelId: $channelId,
            userId: is_string($event['user'] ?? null) ? $event['user'] : null,
            messageTs: $messageTs,
            reaction: $reaction,
            rawEvent: $event,
            rawPayload: $payload,
        );
    }
}

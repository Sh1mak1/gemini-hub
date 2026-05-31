<?php

namespace App\Data;

readonly class SlackIncomingEvent
{
    /**
     * @param  array<string, mixed>  $rawEvent
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $eventType,
        public ?string $text,
        public string $channelId,
        public ?string $userId,
        public ?string $messageTs,
        public ?string $reaction,
        public array $rawEvent,
        public array $rawPayload,
    ) {}

    public function isReaction(): bool
    {
        return $this->eventType === 'reaction_added';
    }

    public function hasProcessableText(): bool
    {
        return filled($this->text);
    }
}

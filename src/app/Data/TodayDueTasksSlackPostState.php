<?php

namespace App\Data;

readonly class TodayDueTasksSlackPostState
{
    public function __construct(
        public string $channelId,
        public string $messageTs,
        public string $contentHash,
    ) {}

    /**
     * @return array{channel_id: string, message_ts: string, content_hash: string}
     */
    public function toArray(): array
    {
        return [
            'channel_id' => $this->channelId,
            'message_ts' => $this->messageTs,
            'content_hash' => $this->contentHash,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $channelId = $data['channel_id'] ?? null;
        $messageTs = $data['message_ts'] ?? null;
        $contentHash = $data['content_hash'] ?? null;

        if (! is_string($channelId) || $channelId === ''
            || ! is_string($messageTs) || $messageTs === ''
            || ! is_string($contentHash) || $contentHash === '') {
            return null;
        }

        return new self($channelId, $messageTs, $contentHash);
    }
}

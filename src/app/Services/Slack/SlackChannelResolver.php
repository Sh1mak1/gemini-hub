<?php

namespace App\Services\Slack;

use App\Enums\TaskCategory;
use Illuminate\Support\Facades\Cache;

class SlackChannelResolver
{
    public function __construct(
        private SlackApiClient $slackApi,
    ) {}

    public function resolveKyouChannel(): ?string
    {
        $configuredChannelId = config('services.slack.kyou.channel_id');

        if (is_string($configuredChannelId) && $configuredChannelId !== '') {
            return $configuredChannelId;
        }

        $channelName = config('services.slack.kyou.channel_name');

        if (! is_string($channelName) || $channelName === '') {
            return null;
        }

        return Cache::remember(
            'slack.channel_id.kyou',
            now()->addHour(),
            fn () => $this->findChannelIdByName($channelName),
        );
    }

    public function resolveForCategory(TaskCategory $category): ?string
    {
        $configuredChannelId = config("services.slack.channels.{$category->value}");

        if (is_string($configuredChannelId) && $configuredChannelId !== '') {
            return $configuredChannelId;
        }

        $channelName = config("services.slack.channel_names.{$category->value}");

        if (! is_string($channelName) || $channelName === '') {
            return null;
        }

        return Cache::remember(
            "slack.channel_id.{$category->value}",
            now()->addHour(),
            fn () => $this->findChannelIdByName($channelName),
        );
    }

    private function findChannelIdByName(string $channelName): ?string
    {
        $normalizedTarget = $this->normalizeChannelName($channelName);

        foreach ($this->slackApi->listChannels() as $channel) {
            if ($this->normalizeChannelName($channel['name']) === $normalizedTarget) {
                return $channel['id'];
            }
        }

        return null;
    }

    private function normalizeChannelName(string $name): string
    {
        return ltrim(strtolower(trim($name)), '#');
    }
}

<?php

namespace App\Services\Slack;

use App\Enums\TaskCategory;
use App\Support\OperationLogger;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SlackChannelResolver
{
    public function __construct(
        private SlackApiClient $slackApi,
    ) {}

    public function resolveTodayChannel(): ?string
    {
        $configuredChannelId = config('services.slack.today.channel_id');

        if (is_string($configuredChannelId) && $configuredChannelId !== '') {
            return $configuredChannelId;
        }

        $channelName = config('services.slack.today.channel_name');

        if (! is_string($channelName) || $channelName === '') {
            return null;
        }

        return Cache::remember(
            'slack.channel_id.today',
            now()->addHour(),
            fn () => $this->findChannelIdByName($channelName),
        );
    }

    /** @deprecated Use resolveTodayChannel() */
    public function resolveKyouChannel(): ?string
    {
        return $this->resolveTodayChannel();
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
        try {
            $normalizedTarget = $this->normalizeChannelName($channelName);

            foreach ($this->slackApi->listChannels() as $channel) {
                if ($this->normalizeChannelName($channel['name']) === $normalizedTarget) {
                    return $channel['id'];
                }
            }
        } catch (Throwable $exception) {
            OperationLogger::warning('slack.channel', 'resolve_failed', [
                'channel_name' => $channelName,
                'error' => $exception->getMessage(),
            ]);
        }

        return null;
    }

    private function normalizeChannelName(string $name): string
    {
        return ltrim(strtolower(trim($name)), '#');
    }
}

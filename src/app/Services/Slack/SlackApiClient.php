<?php

namespace App\Services\Slack;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SlackApiClient
{
    public function fetchMessageText(string $channelId, string $messageTs): ?string
    {
        $response = $this->post('conversations.history', [
            'channel' => $channelId,
            'latest' => $messageTs,
            'inclusive' => true,
            'limit' => 1,
        ]);

        $message = $response['messages'][0] ?? null;

        if (! is_array($message)) {
            return null;
        }

        $text = $message['text'] ?? null;

        return is_string($text) && trim($text) !== '' ? trim($text) : null;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function listChannels(): array
    {
        $channels = [];
        $cursor = null;

        do {
            $params = [
                'types' => 'public_channel,private_channel',
                'limit' => 200,
            ];

            if ($cursor !== null) {
                $params['cursor'] = $cursor;
            }

            $response = $this->post('conversations.list', $params);

            foreach ($response['channels'] ?? [] as $channel) {
                if (! is_array($channel)) {
                    continue;
                }

                $id = $channel['id'] ?? null;
                $name = $channel['name'] ?? null;

                if (is_string($id) && is_string($name)) {
                    $channels[] = ['id' => $id, 'name' => $name];
                }
            }

            $cursor = $response['response_metadata']['next_cursor'] ?? null;
            $cursor = is_string($cursor) && $cursor !== '' ? $cursor : null;
        } while ($cursor !== null);

        return $channels;
    }

    public function joinChannel(string $channelId): void
    {
        $this->post('conversations.join', [
            'channel' => $channelId,
        ]);
    }

    public function postMessage(string $channelId, string $text): string
    {
        $response = $this->post('chat.postMessage', [
            'channel' => $channelId,
            'text' => $text,
        ]);

        $ts = $response['ts'] ?? null;

        if (! is_string($ts) || $ts === '') {
            throw new RuntimeException('Slack API did not return a message timestamp.');
        }

        return $ts;
    }

    public function updateMessage(string $channelId, string $messageTs, string $text): void
    {
        $this->post('chat.update', [
            'channel' => $channelId,
            'ts' => $messageTs,
            'text' => $text,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $method, array $payload): array
    {
        $token = config('services.slack.bot_token');

        if (empty($token)) {
            throw new RuntimeException('Slack bot token is not configured.');
        }

        try {
            $response = Http::withToken($token)
                ->asForm()
                ->post("https://slack.com/api/{$method}", $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            Log::error('Slack API request failed.', [
                'method' => $method,
                'status' => $exception->response?->status(),
                'body' => $exception->response?->body(),
            ]);

            throw new RuntimeException("Slack API request failed: {$method}", previous: $exception);
        }

        if (! is_array($response) || ($response['ok'] ?? false) !== true) {
            Log::error('Slack API returned an error.', [
                'method' => $method,
                'error' => $response['error'] ?? 'unknown',
            ]);

            throw new RuntimeException("Slack API error: {$method}");
        }

        return $response;
    }
}

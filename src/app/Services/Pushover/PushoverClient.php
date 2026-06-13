<?php

namespace App\Services\Pushover;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PushoverClient
{
    public function isConfigured(): bool
    {
        $token = config('pushover.app_token');
        $userKey = config('pushover.user_key');

        return is_string($token) && $token !== ''
            && is_string($userKey) && $userKey !== '';
    }

    public function send(string $title, string $message, ?int $priority = null): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Pushover is not configured.');
        }

        $payload = [
            'token' => config('pushover.app_token'),
            'user' => config('pushover.user_key'),
            'title' => $title,
            'message' => $message,
            'priority' => $priority ?? (int) config('pushover.priority', 0),
        ];

        try {
            $response = Http::asForm()
                ->post((string) config('pushover.api_url'), $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            Log::error('Pushover API request failed.', [
                'status' => $exception->response?->status(),
                'body' => $exception->response?->body(),
            ]);

            throw new RuntimeException('Pushover API request failed.', previous: $exception);
        }

        if (! is_array($response) || ($response['status'] ?? null) !== 1) {
            $errors = $response['errors'] ?? ['unknown'];

            Log::error('Pushover API returned an error.', [
                'errors' => $errors,
            ]);

            throw new RuntimeException('Pushover API error: '.implode(', ', (array) $errors));
        }
    }
}

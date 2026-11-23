<?php

namespace App\Services\Llm;

use App\Exceptions\GeminiApiException;
use App\Support\OperationLogger;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenRouterClient
{
    /**
     * @param  array<string, mixed>  $responseSchema
     * @return array<string, mixed>
     */
    public function generateStructured(string $prompt, array $responseSchema): array
    {
        $maxAttempts = 2;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->attempt($prompt, $responseSchema);
            } catch (GeminiApiException $exception) {
                $lastException = $exception;

                if (! $exception->retryable || $attempt >= $maxAttempts) {
                    throw $exception;
                }

                $delayMs = max(0, (int) config('openrouter.retry_delay_ms', 400));

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        throw $lastException ?? new RuntimeException('OpenRouter API request failed.');
    }

    /**
     * @param  array<string, mixed>  $responseSchema
     * @return array<string, mixed>
     */
    private function attempt(string $prompt, array $responseSchema): array
    {
        $apiKey = config('openrouter.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('OpenRouter API key is not configured.');
        }

        $schema = $responseSchema;
        $schema['additionalProperties'] = false;
        $url = rtrim((string) config('openrouter.base_url'), '/').'/chat/completions';
        $model = (string) config('openrouter.model');

        try {
            $response = Http::timeout((int) config('openrouter.timeout', 25))
                ->withToken($apiKey)
                ->acceptJson()
                ->post($url, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'contact_turn',
                            'strict' => true,
                            'schema' => $schema,
                        ],
                    ],
                    'reasoning' => [
                        'enabled' => false,
                    ],
                ])
                ->throw();
        } catch (RequestException $exception) {
            $status = $exception->response?->status();
            $retryable = in_array($status, [429, 500, 502, 503, 504], true);

            OperationLogger::error('openrouter.api', 'request_failed', [
                'model' => $model,
                'status' => $status,
                'retryable' => $retryable,
            ]);

            throw new GeminiApiException(
                message: 'OpenRouter API request failed.',
                status: $status,
                retryable: $retryable,
                previous: $exception,
            );
        }

        $payload = $response->json();
        $text = data_get($payload, 'choices.0.message.content');

        if (is_array($text)) {
            $text = collect($text)
                ->map(fn ($part) => is_array($part) ? ($part['text'] ?? '') : '')
                ->implode('');
        }

        if (! is_string($text) || trim($text) === '') {
            throw new GeminiApiException(
                message: 'OpenRouter API returned an empty response.',
                status: $response->status(),
                retryable: false,
            );
        }

        $decoded = json_decode($this->jsonText($text), true);

        if (! is_array($decoded)) {
            throw new GeminiApiException(
                message: 'OpenRouter API returned invalid JSON.',
                status: $response->status(),
                retryable: false,
            );
        }

        OperationLogger::info('openrouter.api', 'success', [
            'model' => $model,
        ]);

        return $decoded;
    }

    private function jsonText(string $text): string
    {
        $trimmed = trim($text);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }
}

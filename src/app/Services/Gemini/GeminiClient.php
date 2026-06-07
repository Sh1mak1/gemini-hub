<?php

namespace App\Services\Gemini;

use App\Exceptions\GeminiApiException;
use App\Support\OperationLogger;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClient
{
    /**
     * @param  array<string, mixed>  $responseSchema
     * @return array<string, mixed>
     */
    public function generateStructured(string $model, string $prompt, array $responseSchema): array
    {
        $models = $this->modelChain($model);
        $lastException = null;

        foreach ($models as $currentModel) {
            try {
                return $this->generateStructuredWithRetries($currentModel, $prompt, $responseSchema);
            } catch (GeminiApiException $exception) {
                $lastException = $exception;

                OperationLogger::warning('gemini.api', 'model_failed', [
                    'model' => $currentModel,
                    'status' => $exception->status,
                    'retryable' => $exception->retryable,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        throw $lastException ?? new RuntimeException('Gemini API request failed.');
    }

    /**
     * @param  array<string, mixed>  $responseSchema
     * @return array<string, mixed>
     */
    private function generateStructuredWithRetries(
        string $model,
        string $prompt,
        array $responseSchema,
    ): array {
        $maxAttempts = max(1, (int) config('gemini.retry.max_attempts_per_model', 3));
        $baseDelayMs = max(0, (int) config('gemini.retry.base_delay_ms', 2000));
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->attemptGenerate($model, $prompt, $responseSchema);
            } catch (GeminiApiException $exception) {
                $lastException = $exception;

                if (! $exception->retryable || $attempt >= $maxAttempts) {
                    throw $exception;
                }

                $delayMs = $baseDelayMs * (2 ** ($attempt - 1));

                OperationLogger::info('gemini.api', 'retrying', [
                    'model' => $model,
                    'attempt' => $attempt + 1,
                    'max_attempts' => $maxAttempts,
                    'delay_ms' => $delayMs,
                    'status' => $exception->status,
                ]);

                usleep($delayMs * 1000);
            }
        }

        throw $lastException ?? new RuntimeException('Gemini API request failed.');
    }

    /**
     * @param  array<string, mixed>  $responseSchema
     * @return array<string, mixed>
     */
    private function attemptGenerate(string $model, string $prompt, array $responseSchema): array
    {
        $apiKey = config('gemini.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $baseUrl = rtrim((string) config('gemini.base_url'), '/');
        $url = "{$baseUrl}/models/{$model}:generateContent";

        try {
            $response = Http::timeout((int) config('gemini.timeout'))
                ->withQueryParameters(['key' => $apiKey])
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $responseSchema,
                    ],
                ])
                ->throw();
        } catch (RequestException $exception) {
            $status = $exception->response?->status();
            $retryable = $this->isRetryableStatus($status);

            OperationLogger::error('gemini.api', 'request_failed', [
                'model' => $model,
                'status' => $status,
                'retryable' => $retryable,
                'body_preview' => mb_substr((string) $exception->response?->body(), 0, 200),
            ]);

            throw new GeminiApiException(
                message: 'Gemini API request failed.',
                status: $status,
                retryable: $retryable,
                previous: $exception,
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new GeminiApiException(
                message: 'Gemini API returned an invalid response.',
                status: $response->status(),
                retryable: false,
            );
        }

        $text = data_get($payload, 'candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            throw new GeminiApiException(
                message: 'Gemini API returned an empty response.',
                status: $response->status(),
                retryable: $this->isRetryableStatus($response->status()),
            );
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new GeminiApiException(
                message: 'Gemini API returned invalid JSON.',
                status: $response->status(),
                retryable: false,
            );
        }

        OperationLogger::info('gemini.api', 'success', [
            'model' => $model,
        ]);

        return $decoded;
    }

    /**
     * @return list<string>
     */
    private function modelChain(string $model): array
    {
        $configuredChain = config('gemini.model_chain');

        if (! is_array($configuredChain) || $configuredChain === []) {
            return [$model];
        }

        $chain = array_values(array_unique(array_filter(
            $configuredChain,
            fn ($value) => is_string($value) && $value !== '',
        )));

        if (! in_array($model, $chain, true)) {
            array_unshift($chain, $model);
        }

        return $chain;
    }

    private function isRetryableStatus(?int $status): bool
    {
        return in_array($status, [429, 500, 502, 503, 504], true);
    }
}

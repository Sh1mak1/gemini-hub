<?php

namespace App\Services\Gemini;

use App\Support\OperationLogger;
use Illuminate\Http\Client\RequestException;
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
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            OperationLogger::error('gemini.api', 'request_failed', [
                'model' => $model,
                'status' => $exception->response?->status(),
                'body_preview' => mb_substr((string) $exception->response?->body(), 0, 200),
            ]);

            throw new RuntimeException('Gemini API request failed.', previous: $exception);
        }

        $text = data_get($response, 'candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Gemini API returned an empty response.');
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini API returned invalid JSON.');
        }

        return $decoded;
    }
}

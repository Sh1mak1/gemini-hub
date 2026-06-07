<?php

namespace Tests\Unit;

use App\Exceptions\GeminiApiException;
use App\Services\Gemini\GeminiClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiClientTest extends TestCase
{
    public function test_retries_retryable_status_and_succeeds(): void
    {
        Config::set('gemini.api_key', 'test-key');
        Config::set('gemini.model_chain', ['gemini-2.5-flash']);
        Config::set('gemini.retry.max_attempts_per_model', 3);
        Config::set('gemini.retry.base_delay_ms', 0);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['code' => 503]], 503)
                ->push($this->successPayload(), 200),
        ]);

        $client = new GeminiClient;
        $result = $client->generateStructured(
            model: 'gemini-2.5-flash',
            prompt: 'test',
            responseSchema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                ],
                'required' => ['title'],
            ],
        );

        $this->assertSame('歯医者に行く', $result['title']);
        Http::assertSentCount(2);
    }

    public function test_falls_back_to_next_model_after_retryable_failures(): void
    {
        Config::set('gemini.api_key', 'test-key');
        Config::set('gemini.model_chain', ['gemini-2.5-flash', 'gemini-2.0-flash']);
        Config::set('gemini.retry.max_attempts_per_model', 1);
        Config::set('gemini.retry.base_delay_ms', 0);

        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent*' => Http::response(['error' => ['code' => 503]], 503),
            'generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent*' => Http::response($this->successPayload(), 200),
        ]);

        $client = new GeminiClient;
        $result = $client->generateStructured(
            model: 'gemini-2.5-flash',
            prompt: 'test',
            responseSchema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                ],
                'required' => ['title'],
            ],
        );

        $this->assertSame('歯医者に行く', $result['title']);
    }

    public function test_throws_when_all_models_fail(): void
    {
        Config::set('gemini.api_key', 'test-key');
        Config::set('gemini.model_chain', ['gemini-2.5-flash']);
        Config::set('gemini.retry.max_attempts_per_model', 1);
        Config::set('gemini.retry.base_delay_ms', 0);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 429]], 429),
        ]);

        $client = new GeminiClient;

        $this->expectException(GeminiApiException::class);

        $client->generateStructured(
            model: 'gemini-2.5-flash',
            prompt: 'test',
            responseSchema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                ],
                'required' => ['title'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function successPayload(): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => json_encode(['title' => '歯医者に行く'], JSON_UNESCAPED_UNICODE)],
                        ],
                    ],
                ],
            ],
        ];
    }
}

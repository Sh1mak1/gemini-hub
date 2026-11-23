<?php

namespace Tests\Unit;

use App\Exceptions\GeminiApiException;
use App\Services\Llm\OpenRouterClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterClientTest extends TestCase
{
    public function test_parses_structured_json_and_retries_a_transient_failure(): void
    {
        Config::set('openrouter.api_key', 'test-key');
        Config::set('openrouter.model', 'google/gemini-2.5-flash-lite');
        Config::set('openrouter.retry_delay_ms', 0);

        Http::fake([
            'openrouter.ai/*' => Http::sequence()
                ->push(['error' => ['message' => 'unavailable']], 503)
                ->push([
                    'choices' => [[
                        'message' => ['content' => '{"title":"歯医者に行く"}'],
                    ]],
                ], 200),
        ]);

        $result = (new OpenRouterClient)->generateStructured('test', [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
            ],
            'required' => ['title'],
        ]);

        $this->assertSame('歯医者に行く', $result['title']);
        Http::assertSentCount(2);
    }

    public function test_missing_key_is_not_retried_as_an_api_failure(): void
    {
        Config::set('openrouter.api_key', '');

        try {
            (new OpenRouterClient)->generateStructured('test', [
                'type' => 'object',
                'properties' => ['title' => ['type' => 'string']],
                'required' => ['title'],
            ]);
            $this->fail('A missing key should fail before the request.');
        } catch (GeminiApiException) {
            $this->fail('A missing key must not look like a retryable API failure.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('not configured', $exception->getMessage());
        }
    }
}

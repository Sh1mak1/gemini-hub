<?php

namespace Tests\Unit;

use App\Data\GeminiExtractionFailure;
use App\Enums\TaskCategory;
use App\Models\FallbackTask;
use App\Services\Slack\SlackApiClient;
use App\Services\Slack\SlackChannelResolver;
use App\Services\Slack\SlackNotificationService;
use Mockery;
use Tests\TestCase;

class SlackNotificationServiceTest extends TestCase
{
    public function test_fallback_notification_includes_gemini_api_error_details(): void
    {
        $slackApi = Mockery::mock(SlackApiClient::class);
        $channelResolver = Mockery::mock(SlackChannelResolver::class);

        $channelResolver->shouldReceive('resolveForCategory')->once()->andReturn(null);

        $slackApi->shouldReceive('postMessage')
            ->once()
            ->with('C123', Mockery::on(function (string $message): bool {
                return str_contains($message, 'Gemini API エラー: HTTP 429')
                    && str_contains($message, 'リトライ可')
                    && str_contains($message, 'AI未解析')
                    && str_contains($message, '歯医者に行く');
            }));

        $task = new FallbackTask([
            'title' => '歯医者に行く',
            'raw_input' => '歯医者に行く',
            'category' => TaskCategory::Other,
        ]);
        $task->id = 1;

        $service = new SlackNotificationService($slackApi, $channelResolver);
        $service->notifyTaskCreated(
            $task,
            sourceChannelId: 'C123',
            geminiFailure: new GeminiExtractionFailure(
                message: 'Gemini API request failed.',
                status: 429,
                retryable: true,
            ),
        );
    }
}

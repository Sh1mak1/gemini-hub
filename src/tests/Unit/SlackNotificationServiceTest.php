<?php

namespace Tests\Unit;

use App\Data\GeminiExtractionFailure;
use App\Enums\TaskCategory;
use App\Models\FallbackTask;
use App\Models\Task;
use App\Services\Slack\SlackApiClient;
use App\Services\Slack\SlackChannelResolver;
use App\Services\Slack\SlackNotificationService;
use App\Support\DisplayTime;
use Mockery;
use Tests\TestCase;

class SlackNotificationServiceTest extends TestCase
{
    public function test_fallback_notification_includes_gemini_api_error_details(): void
    {
        $slackApi = Mockery::mock(SlackApiClient::class);
        $channelResolver = Mockery::mock(SlackChannelResolver::class);

        $channelResolver->shouldReceive('resolveForCategory')->once()->andReturn(null);
        $channelResolver->shouldReceive('resolveTodayChannel')->never();

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

    public function test_work_task_posts_to_work_channel(): void
    {
        $slackApi = Mockery::mock(SlackApiClient::class);
        $channelResolver = Mockery::mock(SlackChannelResolver::class);

        $channelResolver->shouldReceive('resolveForCategory')->once()->andReturn('C_WORK');
        $channelResolver->shouldReceive('resolveTodayChannel')->never();

        $slackApi->shouldReceive('postMessage')->once()->with('C_WORK', Mockery::type('string'));

        $task = new Task([
            'title' => '資料作成',
            'category' => TaskCategory::Work,
            'due_date' => DisplayTime::today()->addDay()->format('Y-m-d'),
        ]);
        $task->id = 1;

        (new SlackNotificationService($slackApi, $channelResolver))->notifyTaskCreated($task);
    }

    public function test_hobby_task_due_today_posts_to_hobby_and_today_channels(): void
    {
        $slackApi = Mockery::mock(SlackApiClient::class);
        $channelResolver = Mockery::mock(SlackChannelResolver::class);

        $channelResolver->shouldReceive('resolveForCategory')->once()->andReturn('C_HOBBY');
        $channelResolver->shouldReceive('resolveTodayChannel')->once()->andReturn('C_TODAY');

        $slackApi->shouldReceive('postMessage')->once()->with('C_HOBBY', Mockery::type('string'));
        $slackApi->shouldReceive('postMessage')->once()->with('C_TODAY', Mockery::type('string'));

        $task = new Task([
            'title' => 'カレーを食べる',
            'category' => TaskCategory::Hobby,
            'due_date' => DisplayTime::today()->format('Y-m-d'),
        ]);
        $task->id = 2;

        (new SlackNotificationService($slackApi, $channelResolver))->notifyTaskCreated($task);
    }

    public function test_other_task_due_today_posts_to_today_channel_only(): void
    {
        $slackApi = Mockery::mock(SlackApiClient::class);
        $channelResolver = Mockery::mock(SlackChannelResolver::class);

        $channelResolver->shouldReceive('resolveForCategory')->once()->andReturn(null);
        $channelResolver->shouldReceive('resolveTodayChannel')->once()->andReturn('C_TODAY');

        $slackApi->shouldReceive('postMessage')->once()->with('C_TODAY', Mockery::type('string'));

        $task = new Task([
            'title' => 'カレーを食べる',
            'category' => TaskCategory::Other,
            'due_date' => DisplayTime::today()->format('Y-m-d'),
        ]);
        $task->id = 3;

        (new SlackNotificationService($slackApi, $channelResolver))->notifyTaskCreated($task);
    }
}

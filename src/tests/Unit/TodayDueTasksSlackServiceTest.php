<?php

namespace Tests\Unit;

use App\Enums\TaskCategory;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\Slack\SlackApiClient;
use App\Services\Slack\SlackChannelResolver;
use App\Services\Slack\TodayDueTasksSlackService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class TodayDueTasksSlackServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_scheduled_sync_posts_message_for_tasks_due_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 09:00:00', 'Asia/Tokyo'));

        Task::factory()->create([
            'title' => '歯医者',
            'due_date' => '2026-06-08',
            'category' => TaskCategory::Work,
            'location' => '新宿',
            'status' => TaskStatus::Pending,
        ]);

        $slackApi = Mockery::mock(SlackApiClient::class);
        $channelResolver = Mockery::mock(SlackChannelResolver::class);
        $channelResolver->shouldReceive('resolveKyouChannel')->once()->andReturn('CKYOU');

        $slackApi->shouldReceive('postMessage')
            ->once()
            ->with('CKYOU', Mockery::on(function (string $message): bool {
                return str_contains($message, '本日（2026-06-08）が期限のタスク')
                    && str_contains($message, '1. 歯医者（仕事 / 場所: 新宿）')
                    && str_contains($message, '全1件');
            }))
            ->andReturn('1234.5678');

        $service = new TodayDueTasksSlackService($slackApi, $channelResolver);
        $service->sync(forceScheduled: true);

        $cached = Cache::get('slack.kyou.daily_post.2026-06-08');
        $this->assertSame('CKYOU', $cached['channel_id'] ?? null);
        $this->assertSame('1234.5678', $cached['message_ts'] ?? null);
    }

    public function test_sync_before_post_hour_skips_when_no_message_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 08:30:00', 'Asia/Tokyo'));

        Task::factory()->create([
            'title' => '朝の準備',
            'due_date' => '2026-06-08',
            'status' => TaskStatus::Pending,
        ]);

        $slackApi = Mockery::mock(SlackApiClient::class);
        $channelResolver = Mockery::mock(SlackChannelResolver::class);
        $channelResolver->shouldReceive('resolveKyouChannel')->never();
        $slackApi->shouldReceive('postMessage')->never();
        $slackApi->shouldReceive('updateMessage')->never();

        $service = new TodayDueTasksSlackService($slackApi, $channelResolver);
        $service->sync();

        $this->assertNull(Cache::get('slack.kyou.daily_post.2026-06-08'));
    }

    public function test_sync_updates_existing_message_when_tasks_change(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 10:00:00', 'Asia/Tokyo'));

        Cache::put('slack.kyou.daily_post.2026-06-08', [
            'channel_id' => 'CKYOU',
            'message_ts' => '1234.5678',
            'content_hash' => 'old-hash',
        ], now()->addDay());

        Task::factory()->create([
            'title' => '買い物',
            'due_date' => '2026-06-08',
            'category' => TaskCategory::Hobby,
            'location' => null,
            'status' => TaskStatus::Pending,
        ]);

        $slackApi = Mockery::mock(SlackApiClient::class);
        $channelResolver = Mockery::mock(SlackChannelResolver::class);
        $channelResolver->shouldReceive('resolveKyouChannel')->once()->andReturn('CKYOU');

        $slackApi->shouldReceive('updateMessage')
            ->once()
            ->with('CKYOU', '1234.5678', Mockery::on(function (string $message): bool {
                return str_contains($message, '1. 買い物（趣味 / 場所: 未設定）');
            }));

        $slackApi->shouldReceive('postMessage')->never();

        $service = new TodayDueTasksSlackService($slackApi, $channelResolver);
        $service->sync();
    }

    public function test_sync_posts_empty_message_when_no_tasks_due_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 09:00:00', 'Asia/Tokyo'));

        Task::factory()->create([
            'title' => '明日の予定',
            'due_date' => '2026-06-09',
            'status' => TaskStatus::Pending,
        ]);

        $slackApi = Mockery::mock(SlackApiClient::class);
        $channelResolver = Mockery::mock(SlackChannelResolver::class);
        $channelResolver->shouldReceive('resolveKyouChannel')->once()->andReturn('CKYOU');

        $slackApi->shouldReceive('postMessage')
            ->once()
            ->with('CKYOU', Mockery::on(fn (string $message): bool => str_contains($message, '（なし）')))
            ->andReturn('1234.5678');

        $service = new TodayDueTasksSlackService($slackApi, $channelResolver);
        $service->sync(forceScheduled: true);
    }
}

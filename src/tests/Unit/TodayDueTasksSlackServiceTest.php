<?php

namespace Tests\Unit;

use App\Enums\TaskCategory;
use App\Enums\TaskStatus;
use App\Models\FallbackTask;
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

    public function test_scheduled_sync_posts_message_for_open_tasks_with_due_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 09:00:00', 'Asia/Tokyo'));

        Task::factory()->create([
            'title' => '期限切れの提出',
            'due_date' => '2026-06-06',
            'category' => TaskCategory::Work,
            'location' => 'オフィス',
            'status' => TaskStatus::Pending,
        ]);

        Task::factory()->create([
            'title' => '歯医者',
            'due_date' => '2026-06-08',
            'category' => TaskCategory::Work,
            'location' => '新宿',
            'status' => TaskStatus::Pending,
        ]);

        FallbackTask::factory()->create([
            'title' => '未解析タスク',
            'raw_input' => '未解析タスク',
            'due_date' => '2026-06-11',
            'category' => TaskCategory::Other,
            'location' => null,
            'status' => TaskStatus::Pending,
        ]);

        Task::factory()->create([
            'title' => '更新手続き',
            'due_date' => '2026-06-20',
            'category' => TaskCategory::Hobby,
            'location' => null,
            'status' => TaskStatus::Pending,
        ]);

        Task::factory()->create([
            'title' => '期限なしメモ',
            'due_date' => null,
            'status' => TaskStatus::Pending,
        ]);

        Task::factory()->create([
            'title' => '完了済み',
            'due_date' => '2026-06-07',
            'status' => TaskStatus::Completed,
        ]);

        $slackApi = Mockery::mock(SlackApiClient::class);
        $channelResolver = Mockery::mock(SlackChannelResolver::class);
        $channelResolver->shouldReceive('resolveTodayChannel')->once()->andReturn('CKYOU');

        $slackApi->shouldReceive('postMessage')
            ->once()
            ->with('CKYOU', Mockery::on(function (string $message): bool {
                return str_contains($message, '期限付き未完了タスク（2026-06-08時点）')
                    && str_contains($message, '1. 期限切れ（2日遅れ） 2026-06-06｜期限切れの提出（仕事 / 場所: オフィス）')
                    && str_contains($message, '   `!*`')
                    && str_contains($message, '2. 今日 2026-06-08｜歯医者（仕事 / 場所: 新宿）')
                    && str_contains($message, '   `*`')
                    && str_contains($message, '3. あと3日 2026-06-11｜未解析タスク（その他 / 場所: 未設定）')
                    && str_contains($message, '   `123*`')
                    && str_contains($message, '4. あと12日 2026-06-20｜更新手続き（趣味 / 場所: 未設定）')
                    && str_contains($message, '   `123456789101112*`')
                    && str_contains($message, '全4件')
                    && ! str_contains($message, '凡例:')
                    && ! str_contains($message, 'D+')
                    && ! str_contains($message, 'D-')
                    && ! str_contains($message, '🔴')
                    && ! str_contains($message, '🟠')
                    && ! str_contains($message, '🟡')
                    && ! str_contains($message, '🔵')
                    && ! str_contains($message, '🟢')
                    && ! str_contains($message, '期限なしメモ')
                    && ! str_contains($message, '完了済み');
            }))
            ->andReturn('1234.5678');

        $service = new TodayDueTasksSlackService($slackApi, $channelResolver);
        $service->sync(forceScheduled: true);

        $cached = Cache::get('slack.today.daily_post.2026-06-08');
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
        $channelResolver->shouldReceive('resolveTodayChannel')->never();
        $slackApi->shouldReceive('postMessage')->never();
        $slackApi->shouldReceive('updateMessage')->never();

        $service = new TodayDueTasksSlackService($slackApi, $channelResolver);
        $service->sync();

        $this->assertNull(Cache::get('slack.today.daily_post.2026-06-08'));
    }

    public function test_sync_reposts_when_tasks_change(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 10:00:00', 'Asia/Tokyo'));

        Cache::put('slack.today.daily_post.2026-06-08', [
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
        $channelResolver->shouldReceive('resolveTodayChannel')->once()->andReturn('CKYOU');

        $slackApi->shouldReceive('deleteMessage')
            ->once()
            ->with('CKYOU', '1234.5678');

        $slackApi->shouldReceive('postMessage')
            ->once()
            ->with('CKYOU', Mockery::on(function (string $message): bool {
                return str_contains($message, '   `*`')
                    && str_contains($message, '1. 今日 2026-06-08｜買い物（趣味 / 場所: 未設定）');
            }))
            ->andReturn('9999.0001');

        $service = new TodayDueTasksSlackService($slackApi, $channelResolver);
        $service->sync();
    }

    public function test_sync_posts_empty_message_when_no_open_tasks_with_due_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 09:00:00', 'Asia/Tokyo'));

        Task::factory()->create([
            'title' => '期限なしメモ',
            'due_date' => null,
            'status' => TaskStatus::Pending,
        ]);

        Task::factory()->create([
            'title' => '完了済み',
            'due_date' => '2026-06-08',
            'status' => TaskStatus::Completed,
        ]);

        $slackApi = Mockery::mock(SlackApiClient::class);
        $channelResolver = Mockery::mock(SlackChannelResolver::class);
        $channelResolver->shouldReceive('resolveTodayChannel')->once()->andReturn('CKYOU');

        $slackApi->shouldReceive('postMessage')
            ->once()
            ->with('CKYOU', Mockery::on(fn (string $message): bool => str_contains($message, '（期限付きの未完了タスクなし）')))
            ->andReturn('1234.5678');

        $service = new TodayDueTasksSlackService($slackApi, $channelResolver);
        $service->sync(forceScheduled: true);
    }
}

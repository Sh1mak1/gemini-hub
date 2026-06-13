<?php

namespace Tests\Unit;

use App\Data\GeminiExtractionFailure;
use App\Enums\TaskCategory;
use App\Models\FallbackTask;
use App\Models\Task;
use App\Services\Pushover\PushoverClient;
use App\Services\Pushover\PushoverNotificationService;
use Mockery;
use Tests\TestCase;

class PushoverNotificationServiceTest extends TestCase
{
    public function test_notify_task_created_sends_success_message(): void
    {
        $pushover = Mockery::mock(PushoverClient::class);
        $pushover->shouldReceive('isConfigured')->once()->andReturn(true);
        $pushover->shouldReceive('send')
            ->once()
            ->with('タスク登録完了', Mockery::on(function (string $message): bool {
                return str_contains($message, '買い物に行く')
                    && str_contains($message, 'Slack');
            }));

        $task = new Task([
            'title' => '買い物に行く',
            'category' => TaskCategory::Work,
        ]);
        $task->id = 1;

        (new PushoverNotificationService($pushover))->notifyTaskCreated($task, 'Slack');
    }

    public function test_notify_task_created_includes_gemini_error_for_fallback(): void
    {
        $pushover = Mockery::mock(PushoverClient::class);
        $pushover->shouldReceive('isConfigured')->once()->andReturn(true);
        $pushover->shouldReceive('send')
            ->once()
            ->with('タスク登録（AI未解析）', Mockery::on(function (string $message): bool {
                return str_contains($message, 'Gemini API エラー: HTTP 429')
                    && str_contains($message, '歯医者に行く');
            }));

        $task = new FallbackTask([
            'title' => '歯医者に行く',
            'raw_input' => '歯医者に行く',
            'category' => TaskCategory::Other,
        ]);
        $task->id = 1;

        (new PushoverNotificationService($pushover))->notifyTaskCreated(
            $task,
            'Slack',
            geminiFailure: new GeminiExtractionFailure(
                message: 'Gemini API request failed.',
                status: 429,
                retryable: true,
            ),
        );
    }

    public function test_notify_registration_failed_sends_error_message(): void
    {
        $pushover = Mockery::mock(PushoverClient::class);
        $pushover->shouldReceive('isConfigured')->once()->andReturn(true);
        $pushover->shouldReceive('send')
            ->once()
            ->with('タスク登録失敗', Mockery::on(function (string $message): bool {
                return str_contains($message, 'Drafts')
                    && str_contains($message, 'Gemini API エラー: HTTP 503');
            }));

        (new PushoverNotificationService($pushover))->notifyRegistrationFailed(
            'Drafts',
            '明日メール送る',
            new GeminiExtractionFailure(
                message: 'Service unavailable.',
                status: 503,
                retryable: true,
            ),
        );
    }

    public function test_notify_task_created_skips_when_not_configured(): void
    {
        $pushover = Mockery::mock(PushoverClient::class);
        $pushover->shouldReceive('isConfigured')->once()->andReturn(false);
        $pushover->shouldReceive('send')->never();

        $task = new Task([
            'title' => '買い物に行く',
            'category' => TaskCategory::Work,
        ]);
        $task->id = 1;

        (new PushoverNotificationService($pushover))->notifyTaskCreated($task, 'Slack');
    }

    public function test_notify_deploy_completed_sends_message(): void
    {
        $pushover = Mockery::mock(PushoverClient::class);
        $pushover->shouldReceive('isConfigured')->once()->andReturn(true);
        $pushover->shouldReceive('send')
            ->once()
            ->with(
                'gemini-hub デプロイ完了',
                "Commit: abc1234\nURL: https://gemini-hub.duckdns.org",
            );

        (new PushoverNotificationService($pushover))->notifyDeployCompleted(
            'abc1234',
            'https://gemini-hub.duckdns.org',
        );
    }
}

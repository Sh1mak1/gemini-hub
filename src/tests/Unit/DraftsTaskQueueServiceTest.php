<?php

namespace Tests\Unit;

use App\Data\ExtractedTaskData;
use App\Data\TaskExtractionOutcome;
use App\Enums\TaskCategory;
use App\Models\DraftsTaskQueue;
use App\Services\Drafts\DraftsTaskQueueService;
use App\Services\Gemini\TaskExtractionService;
use App\Services\Slack\SlackNotificationService;
use App\Services\Tasks\TaskPersistenceService;
use App\Support\TodayDueTasksSlackDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DraftsTaskQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_item_deletes_queue_record_when_gemini_succeeds(): void
    {
        $extraction = Mockery::mock(TaskExtractionService::class);
        $extraction->shouldReceive('extract')
            ->once()
            ->with('ラーメンを食べる')
            ->andReturn(TaskExtractionOutcome::fromAi(new ExtractedTaskData(
                title: 'ラーメンを食べる',
                dueDate: null,
                category: TaskCategory::Other,
                location: null,
            )));

        $slack = Mockery::mock(SlackNotificationService::class);
        $slack->shouldReceive('notifyTaskCreated')->once();

        $today = Mockery::mock(TodayDueTasksSlackDispatcher::class);
        $today->shouldReceive('dispatchNow')->once();

        $service = new DraftsTaskQueueService(
            $extraction,
            new TaskPersistenceService,
            $slack,
            $today,
        );

        $item = DraftsTaskQueue::query()->create([
            'input_text' => 'ラーメンを食べる',
        ]);

        $this->assertTrue($service->processItem($item));
        $this->assertDatabaseCount('drafts_task_queue', 0);
        $this->assertDatabaseHas('tasks', ['title' => 'ラーメンを食べる']);
    }

    public function test_process_item_keeps_queue_record_when_gemini_api_fails(): void
    {
        $extraction = Mockery::mock(TaskExtractionService::class);
        $extraction->shouldReceive('extract')
            ->once()
            ->andReturn(TaskExtractionOutcome::fromFallback(
                'ラーメンを食べる',
                new \App\Data\GeminiExtractionFailure(
                    message: 'Gemini API request failed.',
                    status: 429,
                    retryable: true,
                ),
            ));

        $slack = Mockery::mock(SlackNotificationService::class);
        $slack->shouldNotReceive('notifyTaskCreated');

        $today = Mockery::mock(TodayDueTasksSlackDispatcher::class);
        $today->shouldNotReceive('dispatchNow');

        $service = new DraftsTaskQueueService(
            $extraction,
            new TaskPersistenceService,
            $slack,
            $today,
        );

        $item = DraftsTaskQueue::query()->create([
            'input_text' => 'ラーメンを食べる',
        ]);

        $this->assertFalse($service->processItem($item));
        $this->assertDatabaseCount('drafts_task_queue', 1);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertSame(1, $item->fresh()->attempts);
    }

    public function test_is_ready_for_attempt_respects_retry_backoff(): void
    {
        $service = new DraftsTaskQueueService(
            Mockery::mock(TaskExtractionService::class),
            new TaskPersistenceService,
            Mockery::mock(SlackNotificationService::class),
            Mockery::mock(TodayDueTasksSlackDispatcher::class),
        );

        $item = new DraftsTaskQueue([
            'attempts' => 1,
            'last_attempted_at' => now()->subMinute(),
        ]);

        $this->assertFalse($service->isReadyForAttempt($item));

        $item->last_attempted_at = now()->subMinutes(3);
        $this->assertTrue($service->isReadyForAttempt($item));
    }
}

<?php

namespace Tests\Feature;

use App\Models\FallbackTask;
use App\Models\Task;
use App\Models\User;
use App\Exceptions\GeminiApiException;
use App\Services\Gemini\GeminiClient;
use App\Services\Gemini\TaskExtractionService;
use App\Services\Tasks\TaskPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TaskFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_index_includes_ai_and_fallback_tasks_together(): void
    {
        $user = User::factory()->create();
        Task::factory()->create(['title' => 'AI タスク', 'due_date' => null]);
        FallbackTask::factory()->create(['title' => '未解析タスク', 'due_date' => null]);

        $response = $this->actingAs($user)->get(route('tasks.index'));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Tasks/Index')
            ->has('pendingTasks', 2)
            ->where('pendingTasks', fn ($tasks) => collect($tasks)
                ->pluck('title')
                ->sort()
                ->values()
                ->all() === ['AI タスク', '未解析タスク'])
        );
    }

    public function test_extraction_failure_persists_fallback_task(): void
    {
        $gemini = Mockery::mock(GeminiClient::class);
        $gemini->shouldReceive('generateStructured')
            ->once()
            ->andThrow(new GeminiApiException('Gemini API request failed.', 503, true));

        $service = new TaskExtractionService($gemini);
        $outcome = $service->extract('歯医者に行く');

        $this->assertNotNull($outcome);
        $this->assertFalse($outcome->usedAi());
        $this->assertTrue($outcome->hadGeminiApiError());
        $this->assertSame(503, $outcome->geminiFailure?->status);

        $persisted = app(TaskPersistenceService::class)->persist($outcome);

        $this->assertSame('fallback', $persisted->source);
        $this->assertDatabaseHas('fallback_tasks', [
            'id' => $persisted->id,
            'title' => '歯医者に行く',
            'raw_input' => '歯医者に行く',
        ]);
        $this->assertDatabaseCount('tasks', 0);
    }
}

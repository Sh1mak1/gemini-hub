<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\FallbackTask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_complete_pending_ai_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['status' => TaskStatus::Pending]);

        $response = $this->actingAs($user)->patch(route('tasks.complete', [
            'source' => 'ai',
            'id' => $task->id,
        ]));

        $response->assertRedirect();
        $this->assertTrue($task->fresh()->isCompleted());
    }

    public function test_authenticated_user_can_complete_pending_fallback_task(): void
    {
        $user = User::factory()->create();
        $task = FallbackTask::factory()->create(['status' => TaskStatus::Pending]);

        $response = $this->actingAs($user)->patch(route('tasks.complete', [
            'source' => 'fallback',
            'id' => $task->id,
        ]));

        $response->assertRedirect();
        $this->assertTrue($task->fresh()->isCompleted());
    }

    public function test_guest_cannot_complete_task(): void
    {
        $task = Task::factory()->create(['status' => TaskStatus::Pending]);

        $response = $this->patch(route('tasks.complete', [
            'source' => 'ai',
            'id' => $task->id,
        ]));

        $response->assertRedirect(route('login'));
        $this->assertFalse($task->fresh()->isCompleted());
    }
}

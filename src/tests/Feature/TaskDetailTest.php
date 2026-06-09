<?php

namespace Tests\Feature;

use App\Enums\TaskCategory;
use App\Enums\TaskStatus;
use App\Models\FallbackTask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_ai_task_detail(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'title' => '企画書を書く',
            'category' => TaskCategory::Work,
            'status' => TaskStatus::Pending,
        ]);

        $response = $this->actingAs($user)->get(route('tasks.show', [
            'source' => 'ai',
            'id' => $task->id,
        ]));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Tasks/Show')
            ->where('task.id', $task->id)
            ->where('task.source', 'ai')
            ->where('task.title', '企画書を書く')
            ->where('task.raw_input', null)
            ->has('categoryOptions', 3)
            ->has('statusOptions', 2)
        );
    }

    public function test_authenticated_user_can_update_ai_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'title' => '変更前',
            'status' => TaskStatus::Pending,
        ]);

        $response = $this->actingAs($user)->patch(route('tasks.update', [
            'source' => 'ai',
            'id' => $task->id,
        ]), [
            'title' => '変更後のタスク',
            'due_date' => '2026-06-09',
            'category' => TaskCategory::Hobby->value,
            'location' => '自宅',
            'status' => TaskStatus::Completed->value,
        ]);

        $response->assertRedirect(route('tasks.show', [
            'source' => 'ai',
            'id' => $task->id,
        ]));

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => '変更後のタスク',
            'due_date' => '2026-06-09 00:00:00',
            'category' => TaskCategory::Hobby->value,
            'location' => '自宅',
            'status' => TaskStatus::Completed->value,
        ]);
    }

    public function test_authenticated_user_can_update_fallback_task(): void
    {
        $user = User::factory()->create();
        $task = FallbackTask::factory()->create([
            'title' => '未解析',
            'raw_input' => '元の入力テキスト',
        ]);

        $response = $this->actingAs($user)->patch(route('tasks.update', [
            'source' => 'fallback',
            'id' => $task->id,
        ]), [
            'title' => '整理したタスク',
            'due_date' => null,
            'category' => TaskCategory::Work->value,
            'location' => '',
            'status' => TaskStatus::Pending->value,
        ]);

        $response->assertRedirect(route('tasks.show', [
            'source' => 'fallback',
            'id' => $task->id,
        ]));

        $this->assertDatabaseHas('fallback_tasks', [
            'id' => $task->id,
            'title' => '整理したタスク',
            'raw_input' => '元の入力テキスト',
            'due_date' => null,
            'category' => TaskCategory::Work->value,
            'location' => null,
            'status' => TaskStatus::Pending->value,
        ]);
    }

    public function test_guest_cannot_view_or_update_task_detail(): void
    {
        $task = Task::factory()->create(['title' => '未ログイン確認']);

        $this->get(route('tasks.show', [
            'source' => 'ai',
            'id' => $task->id,
        ]))->assertRedirect(route('login'));

        $this->patch(route('tasks.update', [
            'source' => 'ai',
            'id' => $task->id,
        ]), [
            'title' => '変更されない',
            'due_date' => null,
            'category' => TaskCategory::Other->value,
            'location' => null,
            'status' => TaskStatus::Completed->value,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => '未ログイン確認',
            'status' => TaskStatus::Pending->value,
        ]);
    }
}

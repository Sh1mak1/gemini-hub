<?php

namespace Tests\Feature;

use App\Jobs\ProcessDraftsTaskQueueJob;
use App\Models\DraftsTaskQueue;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DraftsTaskQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['drafts.api_token' => 'test-drafts-token']);
    }

    public function test_store_accepts_input_and_returns_success_immediately(): void
    {
        Queue::fake();

        $response = $this->withToken('test-drafts-token')
            ->post('/api/drafts/tasks/add', ['text' => 'ラーメンを食べる']);

        $response->assertStatus(202);
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('受け付け', $response->getContent());

        $this->assertDatabaseCount('drafts_task_queue', 1);
        $this->assertDatabaseHas('drafts_task_queue', [
            'input_text' => 'ラーメンを食べる',
        ]);
        $this->assertDatabaseCount('tasks', 0);

        Queue::assertPushed(ProcessDraftsTaskQueueJob::class);
    }

    public function test_store_decodes_url_encoded_form_body(): void
    {
        Queue::fake();

        $body = 'text='.rawurlencode('今日までにラーメンを食べる');

        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer test-drafts-token',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])
            ->call(
                'POST',
                '/api/drafts/tasks/add',
                [],
                [],
                [],
                $this->transformHeadersToServerVars([
                    'Authorization' => 'Bearer test-drafts-token',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]),
                $body,
            );

        $response->assertStatus(202);

        $this->assertDatabaseHas('drafts_task_queue', [
            'input_text' => '今日までにラーメンを食べる',
        ]);
    }

    public function test_store_rejects_empty_body(): void
    {
        Queue::fake();

        $response = $this->withToken('test-drafts-token')
            ->post('/api/drafts/tasks/add', []);

        $response->assertStatus(400);
        $this->assertDatabaseCount('drafts_task_queue', 0);
        Queue::assertNothingPushed();
    }
}

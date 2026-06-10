<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DebugPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearOperationLogs();
    }

    protected function tearDown(): void
    {
        $this->clearOperationLogs();
        parent::tearDown();
    }

    private function clearOperationLogs(): void
    {
        foreach (glob(storage_path('logs/operations-*.log')) ?: [] as $file) {
            File::delete($file);
        }
    }

    public function test_guest_cannot_view_debug_pages(): void
    {
        $this->get(route('debug.logs'))->assertRedirect(route('login'));
        $this->get(route('debug.database'))->assertRedirect(route('login'));
        $this->get(route('debug.logs.entries'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_operation_logs(): void
    {
        $user = User::factory()->create();
        $logPath = storage_path('logs/operations-2026-06-07.log');

        File::ensureDirectoryExists(dirname($logPath));
        File::put(
            $logPath,
            '[2026-06-07 12:00:00] testing.INFO: [slack.job] task_created {"operation":"slack.job","step":"task_created","task_id":1}'."\n",
        );

        $this->actingAs($user)
            ->get(route('debug.logs', ['date' => '2026-06-07']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Debug/Logs')
                ->has('entries', 1)
                ->where('entries.0.operation', 'slack.job')
                ->where('entries.0.timestamp', '2026-06-07 21:00:00')
            );
    }

    public function test_authenticated_user_can_fetch_log_entries_as_json(): void
    {
        $user = User::factory()->create();
        $logPath = storage_path('logs/operations-2026-06-07.log');

        File::ensureDirectoryExists(dirname($logPath));
        File::put(
            $logPath,
            '[2026-06-07 12:00:00] testing.INFO: [gemini.extract] success {"operation":"gemini.extract","step":"success"}'."\n",
        );

        $this->actingAs($user)
            ->getJson(route('debug.logs.entries', ['date' => '2026-06-07']))
            ->assertOk()
            ->assertJsonPath('entries.0.operation', 'gemini.extract')
            ->assertJsonPath('entries.0.timestamp', '2026-06-07 21:00:00');
    }

    public function test_authenticated_user_can_view_database_tables(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('debug.database', ['table' => 'tasks']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Debug/Database')
                ->where('selectedTable', 'tasks')
                ->has('tableData.columns')
            );
    }
}

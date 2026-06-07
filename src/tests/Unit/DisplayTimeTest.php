<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Support\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisplayTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_at_is_formatted_in_display_timezone(): void
    {
        $task = Task::factory()->create([
            'created_at' => '2026-06-07 21:56:21',
            'updated_at' => '2026-06-07 21:56:21',
        ]);

        $displayed = DisplayTime::fromModelTimestamp($task, 'created_at');

        $this->assertSame('2026-06-08', $displayed?->toDateString());
        $this->assertSame('06:56:21', $displayed?->format('H:i:s'));
    }
}

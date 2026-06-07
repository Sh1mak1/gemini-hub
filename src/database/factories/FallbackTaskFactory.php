<?php

namespace Database\Factories;

use App\Enums\TaskCategory;
use App\Enums\TaskStatus;
use App\Models\FallbackTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FallbackTask>
 */
class FallbackTaskFactory extends Factory
{
    protected $model = FallbackTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'title' => $title,
            'raw_input' => $title,
            'due_date' => null,
            'category' => TaskCategory::Other,
            'location' => null,
            'status' => TaskStatus::Pending,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Completed,
        ]);
    }
}

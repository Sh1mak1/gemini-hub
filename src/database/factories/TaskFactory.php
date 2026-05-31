<?php

namespace Database\Factories;

use App\Enums\TaskCategory;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'due_date' => fake()->optional(0.8)->dateTimeBetween('now', '+2 weeks'),
            'category' => fake()->randomElement(TaskCategory::cases()),
            'location' => fake()->optional(0.6)->randomElement(['自宅', 'オフィス', 'カフェ', '外出先']),
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

<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $project = null;
        $resolveProject = function () use (&$project): Project {
            return $project ??= Project::factory()->create();
        };

        return [
            'workspace_id' => fn (array $attributes): int => (int) $resolveProject()->workspace_id,
            'project_id' => fn (array $attributes): int => (int) $resolveProject()->getKey(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
            'due_date' => null,
            'reviewer_id' => null,
            'created_by' => fn (array $attributes): int => (int) $resolveProject()->created_by,
            'position' => 0,
            'progress' => 0,
            'completed_at' => null,
        ];
    }
}

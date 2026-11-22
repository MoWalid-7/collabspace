<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $task = null;
        $resolveTask = function () use (&$task): Task {
            return $task ??= Task::factory()->create();
        };

        return [
            'workspace_id' => fn (array $attributes): int => (int) $resolveTask()->workspace_id,
            'commentable_type' => Task::class,
            'commentable_id' => fn (array $attributes): int => (int) $resolveTask()->getKey(),
            'user_id' => fn (array $attributes): int => (int) $resolveTask()->created_by,
            'body' => fake()->paragraph(),
        ];
    }
}

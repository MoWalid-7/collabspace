<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
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
            'workspace_id' => fn (): int => (int) $resolveProject()->workspace_id,
            'project_id' => fn (): int => (int) $resolveProject()->getKey(),
            'task_id' => null,
            'uploaded_by' => fn (): int => (int) $resolveProject()->created_by,
            'name' => fake()->word().'.pdf',
        ];
    }
}

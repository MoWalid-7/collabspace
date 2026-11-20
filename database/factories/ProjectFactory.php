<?php

namespace Database\Factories;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->catchPhrase(),
            'description' => fake()->optional()->paragraph(),
            'type' => fake()->randomElement(['SaaS', 'Mobile', 'Internal']),
            'priority' => ProjectPriority::Medium->value,
            'status' => ProjectStatus::Planned->value,
            'start_date' => Carbon::today(),
            'deadline' => Carbon::today()->addMonth(),
            'created_by' => User::factory(),
        ];
    }
}

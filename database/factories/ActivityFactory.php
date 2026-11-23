<?php

namespace Database\Factories;

use App\Enums\ActivityEvent;
use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
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
            'project_id' => null,
            'causer_id' => User::factory(),
            'subject_type' => Task::class,
            'subject_id' => 1,
            'event' => ActivityEvent::TaskCreated,
            'properties' => [],
            'created_at' => now(),
        ];
    }
}

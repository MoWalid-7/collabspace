<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    public function definition(): array
    {
        $started = fake()->dateTimeBetween('-1 month', 'now');
        $duration = fake()->numberBetween(600, 7200);

        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
            'task_id' => Task::factory(),
            'started_at' => $started,
            'ended_at' => (clone $started)->modify("+{$duration} seconds"),
            'duration_seconds' => $duration,
            'note' => fake()->sentence(),
        ];
    }
}

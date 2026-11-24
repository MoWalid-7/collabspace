<?php

namespace Database\Factories;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+1 month');
        $end = (clone $start)->modify('+1 hour');

        return [
            'workspace_id' => Workspace::factory(),
            'project_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'starts_at' => $start,
            'ends_at' => $end,
            'join_url' => 'https://meet.google.com/abc-defg-hij',
            'status' => MeetingStatus::Scheduled,
            'created_by' => User::factory(),
            'reminder_sent_at' => null,
        ];
    }
}

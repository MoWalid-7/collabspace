<?php

namespace Database\Factories;

use App\Models\DailyStatus;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyStatus>
 */
class DailyStatusFactory extends Factory
{
    protected $model = DailyStatus::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'project_id' => null,
            'date' => now()->toDateString(),
            'done_yesterday' => fake()->sentence(),
            'plan_today' => fake()->sentence(),
            'blockers' => null,
        ];
    }
}

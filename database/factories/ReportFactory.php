<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Report;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'type' => ReportType::Tasks,
            'params' => [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ],
            'status' => ReportStatus::Pending,
            'file_path' => null,
            'note' => fake()->sentence(),
            'generated_at' => null,
            'error' => null,
        ];
    }
}

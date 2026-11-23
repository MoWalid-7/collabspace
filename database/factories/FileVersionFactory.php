<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileVersion>
 */
class FileVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_id' => File::factory(),
            'version_number' => 1,
            'disk' => 'private',
            'path' => fake()->uuid(),
            'original_name' => fake()->word().'.pdf',
            'mime' => 'application/pdf',
            'size' => 100,
            'checksum' => hash('sha256', fake()->uuid()),
            'uploaded_by' => User::factory(),
            'note' => null,
        ];
    }
}

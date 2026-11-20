<?php

namespace App\Data\Projects;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;

readonly class UpdateProjectData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public int $projectId,
        public array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function from(int $projectId, array $validated): self
    {
        foreach (['priority' => ProjectPriority::class, 'status' => ProjectStatus::class] as $field => $enum) {
            if (isset($validated[$field])) {
                $validated[$field] = $enum::from($validated[$field]);
            }
        }

        return new self($projectId, $validated);
    }
}

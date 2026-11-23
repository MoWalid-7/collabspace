<?php

namespace App\Data\Files;

use App\Enums\FileCategory;

readonly class FileFilters
{
    public function __construct(
        public int $projectId,
        public ?string $search,
        public ?FileCategory $type,
        public ?int $taskId,
        public ?int $uploadedBy,
        public string $sort,
        public int $perPage,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function from(int $projectId, array $attributes): self
    {
        return new self(
            projectId: $projectId,
            search: $attributes['search'] ?? null,
            type: isset($attributes['type']) ? FileCategory::from($attributes['type']) : null,
            taskId: isset($attributes['task_id']) ? (int) $attributes['task_id'] : null,
            uploadedBy: isset($attributes['uploaded_by']) ? (int) $attributes['uploaded_by'] : null,
            sort: $attributes['sort'] ?? 'newest',
            perPage: (int) ($attributes['per_page'] ?? 15),
        );
    }
}

<?php

namespace App\Data\Projects;

readonly class ProjectFilters
{
    public function __construct(
        public ?string $search,
        public ?string $status,
        public ?string $priority,
        public ?int $teamId,
        public string $sort,
        public int $perPage,
    ) {}

    /**
     * @param  array{search?: string|null, status?: string|null, priority?: string|null, team_id?: int|null, sort?: string, per_page?: int}  $filters
     */
    public static function from(array $filters): self
    {
        return new self(
            search: $filters['search'] ?? null,
            status: $filters['status'] ?? null,
            priority: $filters['priority'] ?? null,
            teamId: isset($filters['team_id']) ? (int) $filters['team_id'] : null,
            sort: $filters['sort'] ?? 'newest',
            perPage: $filters['per_page'] ?? 15,
        );
    }
}

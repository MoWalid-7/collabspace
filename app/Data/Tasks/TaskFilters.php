<?php

namespace App\Data\Tasks;

readonly class TaskFilters
{
    public function __construct(
        public ?bool $mine,
        public ?string $status,
        public ?string $priority,
        public ?int $projectId,
        public ?string $search,
        public ?string $dueBefore,
        public ?string $dueAfter,
        public ?bool $overdue,
        public int $perPage,
    ) {}

    /**
     * @param  array{mine?: bool|string|null, status?: string|null, priority?: string|null, project_id?: int|string|null, search?: string|null, due_before?: string|null, due_after?: string|null, overdue?: bool|string|null, per_page?: int}  $filters
     */
    public static function from(array $filters): self
    {
        return new self(
            mine: array_key_exists('mine', $filters) ? filter_var($filters['mine'], FILTER_VALIDATE_BOOLEAN) : null,
            status: $filters['status'] ?? null,
            priority: $filters['priority'] ?? null,
            projectId: isset($filters['project_id']) ? (int) $filters['project_id'] : null,
            search: $filters['search'] ?? null,
            dueBefore: $filters['due_before'] ?? null,
            dueAfter: $filters['due_after'] ?? null,
            overdue: array_key_exists('overdue', $filters) ? filter_var($filters['overdue'], FILTER_VALIDATE_BOOLEAN) : null,
            perPage: (int) ($filters['per_page'] ?? 15),
        );
    }
}

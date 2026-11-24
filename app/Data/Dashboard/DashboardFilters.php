<?php

namespace App\Data\Dashboard;

readonly class DashboardFilters
{
    public function __construct(
        public ?int $projectId = null,
        public bool $mine = false,
        public string $range = 'week',
        public int $limit = 10,
    ) {}

    /**
     * @param  array{project_id?: int|string|null, mine?: bool|string|null, range?: string, limit?: int|string}  $filters
     */
    public static function from(array $filters, int $defaultLimit = 10): self
    {
        return new self(
            projectId: isset($filters['project_id']) ? (int) $filters['project_id'] : null,
            mine: isset($filters['mine']) && filter_var($filters['mine'], FILTER_VALIDATE_BOOLEAN),
            range: $filters['range'] ?? 'week',
            limit: isset($filters['limit']) ? (int) $filters['limit'] : $defaultLimit,
        );
    }
}

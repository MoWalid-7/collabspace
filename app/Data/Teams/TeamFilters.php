<?php

namespace App\Data\Teams;

readonly class TeamFilters
{
    public function __construct(
        public ?string $search,
        public int $perPage,
    ) {}

    /**
     * @param  array{search?: string|null, per_page?: int}  $filters
     */
    public static function from(array $filters): self
    {
        return new self(
            search: $filters['search'] ?? null,
            perPage: $filters['per_page'] ?? 15,
        );
    }
}

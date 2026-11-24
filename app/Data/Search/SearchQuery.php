<?php

namespace App\Data\Search;

readonly class SearchQuery
{
    /**
     * @param  list<string>  $types
     */
    public function __construct(
        public string $term,
        public array $types,
        public int $limit,
    ) {}

    /**
     * @param  array{q: string, types?: list<string>, limit?: int|string}  $filters
     */
    public static function from(array $filters): self
    {
        return new self(
            term: trim($filters['q']),
            types: $filters['types'] ?? ['projects', 'tasks', 'files', 'teams', 'members'],
            limit: isset($filters['limit']) ? (int) $filters['limit'] : 5,
        );
    }
}

<?php

namespace App\Data\Dashboard;

readonly class ProjectOverviewData
{
    /**
     * @param  list<array{date: string, label: string, count: int}>  $created
     * @param  list<array{date: string, label: string, count: int}>  $completed
     */
    public function __construct(
        public string $range,
        public array $created,
        public array $completed,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Data\DailyStatus;

final readonly class DailyStatusFilters
{
    public function __construct(
        public ?string $date = null,
        public ?int $projectId = null,
        public ?int $userId = null,
    ) {}
}

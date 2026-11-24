<?php

declare(strict_types=1);

namespace App\Data\DailyStatus;

final readonly class UpsertDailyStatusData
{
    public function __construct(
        public ?int $projectId,
        public string $date,
        public string $doneYesterday,
        public string $planToday,
        public ?string $blockers = null,
    ) {}
}

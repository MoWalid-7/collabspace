<?php

namespace App\Data\TimeTracking;

use Carbon\CarbonImmutable;

readonly class ManualTimeEntryData
{
    public function __construct(
        public int $taskId,
        public CarbonImmutable $startedAt,
        public CarbonImmutable $endedAt,
        public ?string $note = null,
    ) {}
}

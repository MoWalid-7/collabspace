<?php

namespace App\Data\TimeTracking;

use Carbon\CarbonImmutable;

readonly class UpdateTimeEntryData
{
    public function __construct(
        public ?CarbonImmutable $startedAt = null,
        public ?CarbonImmutable $endedAt = null,
        public ?string $note = null,
    ) {}
}

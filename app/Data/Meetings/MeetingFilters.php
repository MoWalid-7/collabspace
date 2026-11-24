<?php

declare(strict_types=1);

namespace App\Data\Meetings;

use Carbon\CarbonImmutable;

final readonly class MeetingFilters
{
    public function __construct(
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?int $projectId = null,
        public bool $mine = false,
    ) {}
}

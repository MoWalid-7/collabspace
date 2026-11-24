<?php

declare(strict_types=1);

namespace App\Data\Meetings;

use Carbon\CarbonImmutable;

final readonly class UpdateMeetingData
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?int $projectId = null,
        public ?CarbonImmutable $startsAt = null,
        public ?CarbonImmutable $endsAt = null,
        public ?string $joinUrl = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Data\Meetings;

use Carbon\CarbonImmutable;

final readonly class CreateMeetingData
{
    /**
     * @param  array<int, int>  $participantIds
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?int $projectId,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?string $joinUrl = null,
        public array $participantIds = [],
    ) {}
}

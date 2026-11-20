<?php

namespace App\Data\Teams;

readonly class TeamMemberData
{
    public function __construct(
        public int $teamId,
        public int $userId,
        public ?string $roleTitle,
    ) {}
}

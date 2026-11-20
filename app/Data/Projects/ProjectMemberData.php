<?php

namespace App\Data\Projects;

readonly class ProjectMemberData
{
    public function __construct(
        public int $projectId,
        public int $userId,
        public ?string $roleTitle,
    ) {}
}

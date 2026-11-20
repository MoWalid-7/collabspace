<?php

namespace App\Data\Projects;

readonly class ProjectTeamData
{
    public function __construct(
        public int $projectId,
        public int $teamId,
    ) {}
}

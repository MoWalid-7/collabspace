<?php

namespace App\Data\Projects;

use App\Models\Project;

readonly class ProjectDetailsData
{
    public function __construct(
        public Project $project,
        public ProjectStatsData $stats,
    ) {}
}

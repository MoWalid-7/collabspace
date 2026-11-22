<?php

namespace App\Data\Projects;

readonly class ProjectStatsData
{
    public function __construct(
        public int $totalTasks,
        public int $pending,
        public int $inProgress,
        public int $inReview,
        public int $completed,
        public int $overdue,
        public int $progressPercent,
    ) {}
}

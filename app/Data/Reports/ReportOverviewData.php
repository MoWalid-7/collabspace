<?php

namespace App\Data\Reports;

readonly class ReportOverviewData
{
    /**
     * @param  array{value: int, change_percent: int|null}  $taskCompletionRate
     * @param  array{value: int, change_percent: int|null}  $projectsCompletionRate
     * @param  array{value: int, change_percent: int|null}  $teamsActivityRate
     */
    public function __construct(
        public array $taskCompletionRate,
        public array $projectsCompletionRate,
        public array $teamsActivityRate,
    ) {}
}

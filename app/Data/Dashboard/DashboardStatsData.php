<?php

namespace App\Data\Dashboard;

readonly class DashboardStatsData
{
    /**
     * @param  array<string, array{value: int, change_percent: int|null}>  $cards
     */
    public function __construct(public array $cards) {}
}

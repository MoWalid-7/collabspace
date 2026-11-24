<?php

namespace App\Repositories\Contracts;

use App\Data\Dashboard\DashboardFilters;
use App\Models\Task;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

interface DashboardRepositoryInterface
{
    /**
     * @return array{
     *     total_tasks: int,
     *     pending_tasks: int,
     *     active_tasks: int,
     *     completed_tasks: int,
     *     overdue_tasks: int,
     *     created_current: int,
     *     created_previous: int,
     *     completed_current: int,
     *     completed_previous: int
     * }
     */
    public function stats(DashboardFilters $filters, int $userId, int $workspaceId): array;

    /**
     * @return list<CarbonImmutable>
     */
    public function createdTimestamps(
        DashboardFilters $filters,
        int $userId,
        int $workspaceId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array;

    /**
     * @return list<CarbonImmutable>
     */
    public function completedTimestamps(
        DashboardFilters $filters,
        int $userId,
        int $workspaceId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array;

    /**
     * @return Collection<int, Task>
     */
    public function deadlines(DashboardFilters $filters, int $userId, int $workspaceId): Collection;

    /**
     * @return Collection<int, Team>
     */
    public function teams(DashboardFilters $filters, int $userId, int $workspaceId): Collection;
}

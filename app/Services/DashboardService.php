<?php

namespace App\Services;

use App\Data\Dashboard\DashboardFilters;
use App\Data\Dashboard\DashboardStatsData;
use App\Data\Dashboard\ProjectOverviewData;
use App\Models\File;
use App\Models\Task;
use App\Models\Team;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\FileRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class DashboardService
{
    public function __construct(
        private DashboardRepositoryInterface $dashboard,
        private FileRepositoryInterface $files,
        private DashboardMetrics $metrics,
    ) {}

    /**
     * Total-task change compares current/previous calendar-month task creation;
     * completed-task change compares current/previous completed_at timestamps.
     * Other cards have no historical snapshots and therefore return null changes.
     */
    public function stats(DashboardFilters $filters, int $userId, int $workspaceId): DashboardStatsData
    {
        $stats = $this->dashboard->stats($filters, $userId, $workspaceId);
        $totalTasks = $stats['total_tasks'];
        $completedTasks = $stats['completed_tasks'];

        return new DashboardStatsData([
            'total_tasks' => [
                'value' => $totalTasks,
                'change_percent' => $this->metrics->changePercent(
                    $stats['created_current'],
                    $stats['created_previous'],
                ),
            ],
            'pending_tasks' => ['value' => $stats['pending_tasks'], 'change_percent' => null],
            'active_tasks' => ['value' => $stats['active_tasks'], 'change_percent' => null],
            'completed_tasks' => [
                'value' => $completedTasks,
                'change_percent' => $this->metrics->changePercent(
                    $stats['completed_current'],
                    $stats['completed_previous'],
                ),
            ],
            'overdue_tasks' => ['value' => $stats['overdue_tasks'], 'change_percent' => null],
            'completion_rate' => [
                'value' => $totalTasks === 0 ? 0 : (int) round($completedTasks / $totalTasks * 100),
                'change_percent' => null,
            ],
        ]);
    }

    public function overview(DashboardFilters $filters, int $userId, int $workspaceId): ProjectOverviewData
    {
        $now = CarbonImmutable::now();

        if ($filters->range === 'year') {
            $start = $now->startOfYear();
            $end = $now->endOfYear();
        } else {
            $start = $now->startOfDay()->subDays(6);
            $end = $now->endOfDay();
        }

        $created = $this->dashboard->createdTimestamps($filters, $userId, $workspaceId, $start, $end);
        $completed = $this->dashboard->completedTimestamps($filters, $userId, $workspaceId, $start, $end);

        return new ProjectOverviewData(
            range: $filters->range,
            created: $this->metrics->bucketTimestamps($created, $start, $end, $filters->range),
            completed: $this->metrics->bucketTimestamps($completed, $start, $end, $filters->range),
        );
    }

    /**
     * @return Collection<int, Task>
     */
    public function deadlines(DashboardFilters $filters, int $userId, int $workspaceId): Collection
    {
        return $this->dashboard->deadlines($filters, $userId, $workspaceId);
    }

    /**
     * @return Collection<int, Team>
     */
    public function teams(DashboardFilters $filters, int $userId, int $workspaceId): Collection
    {
        return $this->dashboard->teams($filters, $userId, $workspaceId);
    }

    /**
     * @return Collection<int, File>
     */
    public function recentFiles(
        DashboardFilters $filters,
        int $userId,
        int $workspaceId,
    ): Collection {
        return $this->files->recentInWorkspace(
            $workspaceId,
            $userId,
            $filters->projectId,
            $filters->mine,
            $filters->limit,
        );
    }
}

<?php

namespace App\Repositories\Contracts;

use App\Data\Reports\ReportOverviewData;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReportsRepositoryInterface
{
    public function getOverview(
        int $workspaceId,
        User $user,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $projectId = null,
    ): ReportOverviewData;

    /**
     * @return list<array{label: string, on_time: int, late: int}>
     */
    public function getTaskCompletionVsDeadline(
        int $workspaceId,
        User $user,
        string $range,
        ?int $projectId = null,
    ): array;

    /**
     * @return list<array{id: int, name: string, status: string, deadline: string|null, tasks_total: int, tasks_completed: int, progress_percent: int}>
     */
    public function getProjectsProgress(
        int $workspaceId,
        User $user,
        ?int $projectId = null,
    ): array;

    /**
     * @return array{members: list<array{id: int, name: string, email: string, tasks_completed: int, hours_logged: float, activities_count: int}>, series: list<array{label: string, tasks_completed: int, hours_logged: float, activities_count: int}>}
     */
    public function getWorkerActivity(
        int $workspaceId,
        User $user,
        string $period,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $projectId = null,
    ): array;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createReport(array $attributes): Report;

    public function findInCurrentWorkspaceOrFail(int $id): Report;

    /**
     * @return LengthAwarePaginator<int, Report>
     */
    public function paginateUserReports(int $workspaceId, int $userId, int $perPage = 15): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateReport(Report $report, array $attributes): Report;

    /**
     * Data export helpers for CSV generation
     *
     * @return list<array<string, mixed>>
     */
    public function getTasksForExport(int $workspaceId, CarbonImmutable $from, CarbonImmutable $to, ?int $projectId = null): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getProjectsForExport(int $workspaceId, ?int $projectId = null): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getTimeEntriesForExport(int $workspaceId, CarbonImmutable $from, CarbonImmutable $to, ?int $projectId = null): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getWorkersForExport(int $workspaceId, CarbonImmutable $from, CarbonImmutable $to): array;
}

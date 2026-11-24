<?php

namespace App\Services\Contracts;

use App\Data\Reports\CreateReportData;
use App\Data\Reports\ReportOverviewData;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface ReportsServiceInterface
{
    public function getOverview(
        User $user,
        int $workspaceId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $projectId = null,
    ): ReportOverviewData;

    /**
     * @return list<array{label: string, on_time: int, late: int}>
     */
    public function getTaskCompletionVsDeadline(
        User $user,
        int $workspaceId,
        string $range,
        ?int $projectId = null,
    ): array;

    /**
     * @return list<array{id: int, name: string, status: string, deadline: string|null, tasks_total: int, tasks_completed: int, progress_percent: int}>
     */
    public function getProjectsProgress(
        User $user,
        int $workspaceId,
        ?int $projectId = null,
    ): array;

    /**
     * @return array{members: list<array{id: int, name: string, email: string, tasks_completed: int, hours_logged: float, activities_count: int}>, series: list<array{label: string, tasks_completed: int, hours_logged: float, activities_count: int}>}
     */
    public function getWorkerActivity(
        User $user,
        int $workspaceId,
        string $period,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $projectId = null,
    ): array;

    public function queueReport(CreateReportData $data, User $user, int $workspaceId): Report;

    /**
     * @return LengthAwarePaginator<int, Report>
     */
    public function listReports(User $user, int $workspaceId, int $perPage = 15): LengthAwarePaginator;

    public function getReport(int $id, User $user, int $workspaceId): Report;

    public function downloadReport(int $id, User $user, int $workspaceId): StreamedResponse;
}

<?php

namespace App\Services;

use App\Data\Reports\CreateReportData;
use App\Data\Reports\ReportOverviewData;
use App\Enums\ReportStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\GenerateReportJob;
use App\Models\Report;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\ReportsRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Contracts\ReportsServiceInterface;
use App\Services\Storage\Contracts\FileStorage;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportsService implements ReportsServiceInterface
{
    public function __construct(
        private ReportsRepositoryInterface $reports,
        private ProjectRepositoryInterface $projects,
        private WorkspaceRepositoryInterface $workspaces,
        private FileStorage $storage,
    ) {}

    public function getOverview(
        User $user,
        int $workspaceId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $projectId = null,
    ): ReportOverviewData {
        $this->ensureManagerOrAdmin($user, $workspaceId);
        $this->validateProjectIfProvided($projectId, $user, $workspaceId);

        return $this->reports->getOverview($workspaceId, $user, $from, $to, $projectId);
    }

    public function getTaskCompletionVsDeadline(
        User $user,
        int $workspaceId,
        string $range,
        ?int $projectId = null,
    ): array {
        $this->ensureManagerOrAdmin($user, $workspaceId);
        $this->validateProjectIfProvided($projectId, $user, $workspaceId);

        return $this->reports->getTaskCompletionVsDeadline($workspaceId, $user, $range, $projectId);
    }

    public function getProjectsProgress(
        User $user,
        int $workspaceId,
        ?int $projectId = null,
    ): array {
        $this->ensureManagerOrAdmin($user, $workspaceId);
        $this->validateProjectIfProvided($projectId, $user, $workspaceId);

        return $this->reports->getProjectsProgress($workspaceId, $user, $projectId);
    }

    public function getWorkerActivity(
        User $user,
        int $workspaceId,
        string $period,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $projectId = null,
    ): array {
        $this->ensureManagerOrAdmin($user, $workspaceId);
        $this->validateProjectIfProvided($projectId, $user, $workspaceId);

        return $this->reports->getWorkerActivity($workspaceId, $user, $period, $from, $to, $projectId);
    }

    public function queueReport(CreateReportData $data, User $user, int $workspaceId): Report
    {
        $this->ensureManagerOrAdmin($user, $workspaceId);
        $this->validateProjectIfProvided($data->projectId, $user, $workspaceId);

        $report = $this->reports->createReport([
            'workspace_id' => $workspaceId,
            'user_id' => (int) $user->getKey(),
            'type' => $data->type->value,
            'params' => [
                'start_date' => $data->startDate->toDateString(),
                'end_date' => $data->endDate->toDateString(),
                'project_id' => $data->projectId,
            ],
            'status' => ReportStatus::Pending->value,
            'note' => $data->note,
        ]);

        GenerateReportJob::dispatch($report);

        return $report;
    }

    public function listReports(User $user, int $workspaceId, int $perPage = 15): LengthAwarePaginator
    {
        $this->ensureManagerOrAdmin($user, $workspaceId);

        return $this->reports->paginateUserReports($workspaceId, (int) $user->getKey(), $perPage);
    }

    public function getReport(int $id, User $user, int $workspaceId): Report
    {
        $this->ensureManagerOrAdmin($user, $workspaceId);

        $report = $this->reports->findInCurrentWorkspaceOrFail($id);
        $this->ensureCanAccessReport($report, $user, $workspaceId);

        return $report;
    }

    public function downloadReport(int $id, User $user, int $workspaceId): StreamedResponse
    {
        $this->ensureManagerOrAdmin($user, $workspaceId);

        $report = $this->reports->findInCurrentWorkspaceOrFail($id);
        $this->ensureCanAccessReport($report, $user, $workspaceId);

        if ($report->status !== ReportStatus::Ready || ! $report->file_path) {
            throw new ConflictHttpException('The report is not ready for download yet.');
        }

        $disk = (string) config('collabspace.files.disk');
        $downloadName = "report_{$report->id}_{$report->type->value}.csv";

        return $this->storage->stream($disk, $report->file_path, $downloadName);
    }

    private function ensureManagerOrAdmin(User $user, int $workspaceId): void
    {
        $roleValue = $this->workspaces->roleForWorkspaceId($workspaceId, (int) $user->getKey());
        $role = $roleValue ? WorkspaceRole::tryFrom($roleValue) : null;

        if ($role !== WorkspaceRole::Admin && $role !== WorkspaceRole::Manager) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function ensureCanAccessReport(Report $report, User $user, int $workspaceId): void
    {
        $roleValue = $this->workspaces->roleForWorkspaceId($workspaceId, (int) $user->getKey());
        $role = $roleValue ? WorkspaceRole::tryFrom($roleValue) : null;

        if ($role === WorkspaceRole::Admin) {
            return;
        }

        if ($report->user_id === (int) $user->getKey()) {
            return;
        }

        throw new AuthorizationException('You are not authorized to access this report.');
    }

    private function validateProjectIfProvided(?int $projectId, User $user, int $workspaceId): void
    {
        if ($projectId === null) {
            return;
        }

        $project = $this->projects->findInCurrentWorkspaceOrFail($projectId);
        if (! $this->projects->isVisibleTo($project, (int) $user->getKey(), $workspaceId)) {
            throw new NotFoundHttpException('Project not found or not visible.');
        }
    }
}

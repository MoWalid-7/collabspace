<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Reports\CreateReportData;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\CreateReportRequest;
use App\Http\Requests\Reports\ProjectsProgressRequest;
use App\Http\Requests\Reports\ReportOverviewRequest;
use App\Http\Requests\Reports\TaskCompletionVsDeadlineRequest;
use App\Http\Requests\Reports\WorkerActivityRequest;
use App\Http\Resources\ReportResource;
use App\Services\Contracts\ReportsServiceInterface;
use App\Support\ApiResponse;
use App\Support\CurrentWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function overview(
        ReportOverviewRequest $request,
        ReportsServiceInterface $reports,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $from = $request->filled('from')
            ? CarbonImmutable::parse((string) $request->validated('from'))->startOfDay()
            : CarbonImmutable::now()->startOfMonth()->startOfDay();

        $to = $request->filled('to')
            ? CarbonImmutable::parse((string) $request->validated('to'))->endOfDay()
            : CarbonImmutable::now()->endOfMonth()->endOfDay();

        $projectId = $request->filled('project_id') ? (int) $request->validated('project_id') : null;

        $data = $reports->getOverview(
            $request->user(),
            $currentWorkspace->requireId(),
            $from,
            $to,
            $projectId,
        );

        return ApiResponse::success([
            'task_completion_rate' => $data->taskCompletionRate,
            'projects_completion_rate' => $data->projectsCompletionRate,
            'teams_activity_rate' => $data->teamsActivityRate,
        ]);
    }

    public function taskCompletionVsDeadline(
        TaskCompletionVsDeadlineRequest $request,
        ReportsServiceInterface $reports,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $range = (string) ($request->validated('range') ?? 'week');
        $projectId = $request->filled('project_id') ? (int) $request->validated('project_id') : null;

        $buckets = $reports->getTaskCompletionVsDeadline(
            $request->user(),
            $currentWorkspace->requireId(),
            $range,
            $projectId,
        );

        return ApiResponse::success($buckets);
    }

    public function projectsProgress(
        ProjectsProgressRequest $request,
        ReportsServiceInterface $reports,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $projectId = $request->filled('project_id') ? (int) $request->validated('project_id') : null;

        $projects = $reports->getProjectsProgress(
            $request->user(),
            $currentWorkspace->requireId(),
            $projectId,
        );

        return ApiResponse::success($projects);
    }

    public function workerActivity(
        WorkerActivityRequest $request,
        ReportsServiceInterface $reports,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $period = (string) ($request->validated('period') ?? 'monthly');
        $from = $request->filled('from')
            ? CarbonImmutable::parse((string) $request->validated('from'))->startOfDay()
            : CarbonImmutable::now()->startOfMonth()->startOfDay();

        $to = $request->filled('to')
            ? CarbonImmutable::parse((string) $request->validated('to'))->endOfDay()
            : CarbonImmutable::now()->endOfMonth()->endOfDay();

        $projectId = $request->filled('project_id') ? (int) $request->validated('project_id') : null;

        $activity = $reports->getWorkerActivity(
            $request->user(),
            $currentWorkspace->requireId(),
            $period,
            $from,
            $to,
            $projectId,
        );

        return ApiResponse::success($activity);
    }

    public function store(
        CreateReportRequest $request,
        ReportsServiceInterface $reports,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $data = new CreateReportData(
            type: ReportType::from((string) $request->validated('type')),
            startDate: CarbonImmutable::parse((string) $request->validated('start_date'))->startOfDay(),
            endDate: CarbonImmutable::parse((string) $request->validated('end_date'))->endOfDay(),
            projectId: $request->filled('project_id') ? (int) $request->validated('project_id') : null,
            note: $request->validated('note'),
        );

        $report = $reports->queueReport(
            $data,
            $request->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(new ReportResource($report), 'Report generation requested.', 202);
    }

    public function index(
        ReportsServiceInterface $reports,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $paginator = $reports->listReports(
            request()->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success([
            'reports' => ReportResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(
        int $id,
        ReportsServiceInterface $reports,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $report = $reports->getReport(
            $id,
            request()->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(new ReportResource($report));
    }

    public function download(
        int $id,
        ReportsServiceInterface $reports,
        CurrentWorkspace $currentWorkspace,
    ): StreamedResponse {
        return $reports->downloadReport(
            $id,
            request()->user(),
            $currentWorkspace->requireId(),
        );
    }
}

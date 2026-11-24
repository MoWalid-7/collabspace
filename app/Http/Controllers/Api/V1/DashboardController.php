<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Dashboard\DashboardFilters;
use App\Http\Requests\Dashboard\DashboardItemsRequest;
use App\Http\Requests\Dashboard\DashboardStatsRequest;
use App\Http\Requests\Dashboard\ProjectOverviewRequest;
use App\Http\Resources\DashboardStatsResource;
use App\Http\Resources\DeadlineResource;
use App\Http\Resources\ProjectOverviewResource;
use App\Http\Resources\RecentFileResource;
use App\Http\Resources\TeamProgressResource;
use App\Http\Resources\UpcomingMeetingResource;
use App\Models\User;
use App\Services\Contracts\MeetingServiceInterface;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController
{
    public function stats(
        DashboardStatsRequest $request,
        DashboardService $dashboard,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $stats = $dashboard->stats(
            DashboardFilters::from($request->validated()),
            (int) $request->user()->getAuthIdentifier(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(['stats' => DashboardStatsResource::make($stats)]);
    }

    public function projectOverview(
        ProjectOverviewRequest $request,
        DashboardService $dashboard,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $overview = $dashboard->overview(
            DashboardFilters::from($request->validated()),
            (int) $request->user()->getAuthIdentifier(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(['project_overview' => ProjectOverviewResource::make($overview)]);
    }

    public function deadlines(
        DashboardItemsRequest $request,
        DashboardService $dashboard,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $deadlines = $dashboard->deadlines(
            DashboardFilters::from($request->validated(), 10),
            (int) $request->user()->getAuthIdentifier(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(['deadlines' => DeadlineResource::collection($deadlines)]);
    }

    public function teams(
        DashboardItemsRequest $request,
        DashboardService $dashboard,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $teams = $dashboard->teams(
            DashboardFilters::from($request->validated(), 6),
            (int) $request->user()->getAuthIdentifier(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(['teams' => TeamProgressResource::collection($teams)]);
    }

    public function recentFiles(
        DashboardItemsRequest $request,
        DashboardService $dashboard,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $files = $dashboard->recentFiles(
            DashboardFilters::from($request->validated(), 5),
            (int) $request->user()->getAuthIdentifier(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(['files' => RecentFileResource::collection($files)]);
    }

    public function upcomingMeetings(
        Request $request,
        MeetingServiceInterface $meetingService,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $limit = max(1, min(20, (int) $request->query('limit', 5)));
        /** @var User $user */
        $user = $request->user();
        $meetings = $meetingService->getUpcomingMeetings($user, $currentWorkspace->requireId(), $limit);

        return ApiResponse::ok(UpcomingMeetingResource::collection($meetings));
    }
}

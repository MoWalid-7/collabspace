<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyStatus\GetBlockersRequest;
use App\Http\Requests\DailyStatus\GetDailyStatusesRequest;
use App\Http\Requests\DailyStatus\UpsertDailyStatusRequest;
use App\Http\Resources\DailyStatusResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Contracts\DailyStatusServiceInterface;
use App\Support\ApiResponse;
use App\Support\CurrentWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyStatusController extends Controller
{
    public function __construct(
        private readonly DailyStatusServiceInterface $dailyStatusService,
        private readonly CurrentWorkspace $currentWorkspace,
    ) {}

    public function index(GetDailyStatusesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $statuses = $this->dailyStatusService->list($user, $workspaceId, $request->toFilters());

        return ApiResponse::ok(DailyStatusResource::collection($statuses));
    }

    public function store(UpsertDailyStatusRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $status = $this->dailyStatusService->upsert($user, $workspaceId, $request->toDto());

        return ApiResponse::ok(new DailyStatusResource($status));
    }

    public function teamStatus(Request $request, int $project): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();
        $date = $request->input('date', CarbonImmutable::today()->toDateString());

        $teamStatus = $this->dailyStatusService->getTeamStatus($user, $workspaceId, $project, (string) $date);

        $data = array_map(function ($item) {
            return [
                'user' => new UserResource($item['user']),
                'submitted' => $item['submitted'],
                'status' => $item['status'] !== null ? new DailyStatusResource($item['status']) : null,
            ];
        }, $teamStatus);

        return ApiResponse::ok($data);
    }

    public function blockers(GetBlockersRequest $request, int $project): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $blockers = $this->dailyStatusService->getBlockers(
            $user,
            $workspaceId,
            $project,
            $request->fromDate(),
            $request->toDate()
        );

        return ApiResponse::ok(DailyStatusResource::collection($blockers));
    }
}

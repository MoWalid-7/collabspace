<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\TimeTracking\ManualTimeEntryData;
use App\Data\TimeTracking\TimeEntryFilters;
use App\Data\TimeTracking\UpdateTimeEntryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeTracking\CreateManualTimeEntryRequest;
use App\Http\Requests\TimeTracking\ListTimeEntriesRequest;
use App\Http\Requests\TimeTracking\StartTimeTrackingRequest;
use App\Http\Requests\TimeTracking\UpdateTimeEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Services\Contracts\TimeTrackingServiceInterface;
use App\Support\ApiResponse;
use App\Support\CurrentWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TimeTrackingController extends Controller
{
    public function start(
        int $taskId,
        StartTimeTrackingRequest $request,
        TimeTrackingServiceInterface $service,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $entry = $service->start(
            $taskId,
            (int) $request->user()->getKey(),
            $currentWorkspace->requireId(),
            $request->validated('note'),
        );

        return ApiResponse::created(new TimeEntryResource($entry), 'Timer started successfully.');
    }

    public function stop(
        TimeTrackingServiceInterface $service,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $entry = $service->stop(
            (int) request()->user()->getKey(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(new TimeEntryResource($entry), 'Timer stopped successfully.');
    }

    public function current(
        TimeTrackingServiceInterface $service,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $entry = $service->current(
            (int) request()->user()->getKey(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success($entry ? new TimeEntryResource($entry) : null);
    }

    public function index(
        ListTimeEntriesRequest $request,
        TimeTrackingServiceInterface $service,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $result = $service->list(
            $request->user(),
            $currentWorkspace->requireId(),
            TimeEntryFilters::from($request->validated()),
        );

        $paginator = $result['paginator'];

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => [
                'time_entries' => TimeEntryResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'meta' => [
                'total_seconds' => $result['total_seconds'],
            ],
        ]);
    }

    public function store(
        CreateManualTimeEntryRequest $request,
        TimeTrackingServiceInterface $service,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $data = new ManualTimeEntryData(
            taskId: (int) $request->validated('task_id'),
            startedAt: CarbonImmutable::parse((string) $request->validated('started_at')),
            endedAt: CarbonImmutable::parse((string) $request->validated('ended_at')),
            note: $request->validated('note'),
        );

        $entry = $service->createManual(
            $data,
            (int) $request->user()->getKey(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::created(new TimeEntryResource($entry), 'Time entry created successfully.');
    }

    public function update(
        int $id,
        UpdateTimeEntryRequest $request,
        TimeTrackingServiceInterface $service,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $data = new UpdateTimeEntryData(
            startedAt: $request->filled('started_at') ? CarbonImmutable::parse((string) $request->validated('started_at')) : null,
            endedAt: $request->filled('ended_at') ? CarbonImmutable::parse((string) $request->validated('ended_at')) : null,
            note: $request->validated('note'),
        );

        $entry = $service->update(
            $id,
            $data,
            $request->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(new TimeEntryResource($entry), 'Time entry updated successfully.');
    }

    public function destroy(
        int $id,
        TimeTrackingServiceInterface $service,
        CurrentWorkspace $currentWorkspace,
    ): Response {
        $service->delete(
            $id,
            request()->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::noContent();
    }
}

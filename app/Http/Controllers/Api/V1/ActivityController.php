<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Activities\ListProfileActivitiesRequest;
use App\Http\Requests\Activities\ListProjectActivitiesRequest;
use App\Http\Resources\ActivityResource;
use App\Services\Contracts\ActivityServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ActivityController
{
    public function profile(
        ListProfileActivitiesRequest $request,
        ActivityServiceInterface $activities,
    ): JsonResponse {
        $paginator = $activities->forCauser(
            (int) $request->user()->getKey(),
            $request->integer('per_page', 15),
        );

        return ApiResponse::success([
            'activities' => ActivityResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function project(
        ListProjectActivitiesRequest $request,
        ActivityServiceInterface $activities,
        int $project,
    ): JsonResponse {
        $paginator = $activities->forProject(
            $project,
            $request->integer('per_page', 15),
        );

        return ApiResponse::success([
            'activities' => ActivityResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}

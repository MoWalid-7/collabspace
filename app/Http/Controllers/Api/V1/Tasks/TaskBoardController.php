<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\TaskBoardRequest;
use App\Http\Resources\TaskBoardResource;
use App\Services\Contracts\TaskServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TaskBoardController extends Controller
{
    public function index(
        TaskBoardRequest $request,
        TaskServiceInterface $tasks,
        int $project,
    ): JsonResponse {
        return ApiResponse::success([
            'board' => TaskBoardResource::make($tasks->board($project, $request->validated())),
        ]);
    }
}

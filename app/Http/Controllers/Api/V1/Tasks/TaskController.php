<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Data\Tasks\CreateTaskData;
use App\Data\Tasks\MoveTaskData;
use App\Data\Tasks\TaskFilters;
use App\Data\Tasks\UpdateTaskData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\CreateTaskRequest;
use App\Http\Requests\Tasks\DeleteTaskRequest;
use App\Http\Requests\Tasks\ListTasksRequest;
use App\Http\Requests\Tasks\ManageTaskAssigneeRequest;
use App\Http\Requests\Tasks\MoveTaskRequest;
use App\Http\Requests\Tasks\ShowTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskProgressRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskDetailResource;
use App\Http\Resources\TaskResource;
use App\Services\Contracts\TaskServiceInterface;
use App\Support\ApiResponse;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    public function index(ListTasksRequest $request, TaskServiceInterface $tasks): JsonResponse
    {
        $paginator = $tasks->list(
            TaskFilters::from($request->validated()),
            (int) $request->user()->getKey(),
        );

        return ApiResponse::success([
            'tasks' => TaskResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(
        CreateTaskRequest $request,
        TaskServiceInterface $tasks,
        CurrentWorkspace $currentWorkspace,
        int $project,
    ): JsonResponse {
        $task = $tasks->create(CreateTaskData::from(
            $request->validated(),
            $currentWorkspace->requireId(),
            $project,
            (int) $request->user()->getKey(),
        ));

        return ApiResponse::created([
            'task' => TaskDetailResource::make($task),
        ], 'Task created successfully.');
    }

    public function show(ShowTaskRequest $request, TaskServiceInterface $tasks, int $task): JsonResponse
    {
        return ApiResponse::success([
            'task' => TaskDetailResource::make($tasks->show($task)),
        ]);
    }

    public function update(
        UpdateTaskRequest $request,
        TaskServiceInterface $tasks,
        int $task,
    ): JsonResponse {
        return ApiResponse::success([
            'task' => TaskDetailResource::make(
                $tasks->update(UpdateTaskData::from(
                    $task,
                    $request->validated(),
                    (int) $request->user()->getKey(),
                )),
            ),
        ], 'Task updated successfully.');
    }

    public function destroy(DeleteTaskRequest $request, TaskServiceInterface $tasks, int $task): Response
    {
        $tasks->delete($task);

        return ApiResponse::noContent();
    }

    public function move(
        MoveTaskRequest $request,
        TaskServiceInterface $tasks,
        int $task,
    ): JsonResponse {
        return ApiResponse::success([
            'task' => TaskDetailResource::make(
                $tasks->move(MoveTaskData::from(
                    $task,
                    $request->validated(),
                    (int) $request->user()->getKey(),
                )),
            ),
        ], 'Task moved successfully.');
    }

    public function progress(
        UpdateTaskProgressRequest $request,
        TaskServiceInterface $tasks,
        int $task,
    ): JsonResponse {
        return ApiResponse::success([
            'task' => TaskDetailResource::make(
                $tasks->updateProgress($task, (int) $request->validated('progress')),
            ),
        ], 'Task progress updated successfully.');
    }

    public function addAssignee(
        ManageTaskAssigneeRequest $request,
        TaskServiceInterface $tasks,
        int $task,
    ): JsonResponse {
        return ApiResponse::success([
            'task' => TaskDetailResource::make(
                $tasks->addAssignee(
                    $task,
                    (int) $request->validated('user_id'),
                    (int) $request->user()->getKey(),
                ),
            ),
        ], 'Task assignee added successfully.');
    }

    public function removeAssignee(
        ManageTaskAssigneeRequest $request,
        TaskServiceInterface $tasks,
        int $task,
        int $user,
    ): Response {
        $tasks->removeAssignee($task, $user);

        return ApiResponse::noContent();
    }
}

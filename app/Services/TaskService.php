<?php

namespace App\Services;

use App\Data\Tasks\CreateTaskData;
use App\Data\Tasks\MoveTaskData;
use App\Data\Tasks\TaskFilters;
use App\Data\Tasks\UpdateTaskData;
use App\Enums\TaskStatus;
use App\Events\TaskAssigned;
use App\Events\TaskCreated;
use App\Events\TaskStatusChanged;
use App\Models\Project;
use App\Models\Task;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Contracts\TaskServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService implements TaskServiceInterface
{
    public function __construct(
        private TaskRepositoryInterface $tasks,
        private ProjectRepositoryInterface $projects,
    ) {}

    public function list(TaskFilters $filters, int $userId): LengthAwarePaginator
    {
        return $this->tasks->paginateFiltered($filters, $userId);
    }

    public function board(int $projectId, array $filters): array
    {
        $project = $this->projects->findInCurrentWorkspaceOrFail($projectId);

        return $this->tasks->board($project, $filters);
    }

    public function create(CreateTaskData $data): Task
    {
        return DB::transaction(function () use ($data): Task {
            $project = $this->projects->findInCurrentWorkspaceOrFail($data->projectId);
            $this->validateProjectMembers($project, $data->assigneeIds, $data->reviewerId);

            $task = $this->tasks->createTask([
                'workspace_id' => $data->workspaceId,
                'project_id' => $data->projectId,
                'title' => $data->title,
                'description' => $data->description,
                'priority' => $data->priority,
                'status' => $data->status,
                'due_date' => $data->dueDate,
                'reviewer_id' => $data->reviewerId,
                'created_by' => $data->createdBy,
                'position' => $this->tasks->nextPosition($project, $data->status),
                'progress' => $data->progress,
            ]);

            $assigneeIds = array_values(array_unique($data->assigneeIds));
            $this->tasks->syncAssignees($task, $assigneeIds);

            $task = $this->tasks->findInCurrentWorkspaceOrFail((int) $task->getKey());

            if ($task->exists) {
                TaskCreated::dispatch($task);

                foreach ($assigneeIds as $assigneeId) {
                    TaskAssigned::dispatch(
                        $task,
                        $this->projects->findMemberOrFail($project, $assigneeId),
                        $data->createdBy,
                    );
                }
            }

            return $task;
        });
    }

    public function show(int $taskId): Task
    {
        return $this->tasks->findInCurrentWorkspaceOrFail($taskId);
    }

    public function update(UpdateTaskData $data): Task
    {
        return DB::transaction(function () use ($data): Task {
            $task = $this->tasks->findInCurrentWorkspaceOrFail($data->taskId);
            $attributes = $data->attributes;
            $assigneeIds = isset($attributes['assignee_ids'])
                ? array_values(array_unique(array_map('intval', $attributes['assignee_ids'])))
                : null;
            $reviewerIdWasProvided = array_key_exists('reviewer_id', $attributes);
            $reviewerId = $reviewerIdWasProvided && $attributes['reviewer_id'] !== null
                ? (int) $attributes['reviewer_id']
                : null;

            unset($attributes['assignee_ids']);

            if ($reviewerIdWasProvided) {
                $attributes['reviewer_id'] = $reviewerId;
            }

            if ($assigneeIds !== null || $reviewerIdWasProvided) {
                $this->validateProjectMembers(
                    $task->project,
                    $assigneeIds ?? array_map('intval', $task->assignees->modelKeys()),
                    $reviewerIdWasProvided ? $reviewerId : $task->reviewer_id,
                );
            }

            if ($data->status !== null && $data->status !== $task->status) {
                $this->moveWithinTransaction(
                    $task,
                    $data->status,
                    $this->tasks->nextPosition($task->project, $data->status),
                    $data->actorId ?? (int) $task->created_by,
                );
            }

            if ($attributes !== []) {
                $task = $this->tasks->updateTask($task, $attributes);
            }

            if ($assigneeIds !== null) {
                $existingAssigneeIds = array_map('intval', $task->assignees->modelKeys());
                $this->tasks->syncAssignees($task, $assigneeIds);

                if ($task->exists) {
                    foreach (array_diff($assigneeIds, $existingAssigneeIds) as $assigneeId) {
                        TaskAssigned::dispatch(
                            $task,
                            $this->projects->findMemberOrFail($task->project, (int) $assigneeId),
                            $data->actorId ?? (int) $task->created_by,
                        );
                    }
                }
            }

            return $this->tasks->findInCurrentWorkspaceOrFail((int) $task->getKey());
        });
    }

    public function delete(int $taskId): void
    {
        $this->tasks->softDelete($this->tasks->findInCurrentWorkspaceOrFail($taskId));
    }

    public function move(MoveTaskData $data): Task
    {
        return DB::transaction(function () use ($data): Task {
            $task = $this->tasks->findInCurrentWorkspaceOrFail($data->taskId);

            $this->moveWithinTransaction(
                $task,
                $data->status,
                $data->position,
                $data->actorId ?? (int) $task->created_by,
            );

            return $this->tasks->findInCurrentWorkspaceOrFail($data->taskId);
        });
    }

    public function updateProgress(int $taskId, int $progress): Task
    {
        $task = $this->tasks->findInCurrentWorkspaceOrFail($taskId);

        return $this->tasks->updateTask($task, ['progress' => $progress]);
    }

    public function addAssignee(int $taskId, int $userId, ?int $actorId = null): Task
    {
        $task = $this->tasks->findInCurrentWorkspaceOrFail($taskId);
        $project = $task->project;

        if (! $this->projects->isMember($project, $userId)) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user must be a member of the task project.'],
            ]);
        }

        if ($this->tasks->addAssignee($task, $userId) && $task->exists) {
            TaskAssigned::dispatch(
                $task,
                $this->projects->findMemberOrFail($project, $userId),
                $actorId ?? (int) $task->created_by,
            );
        }

        return $this->tasks->findInCurrentWorkspaceOrFail($taskId);
    }

    public function removeAssignee(int $taskId, int $userId): void
    {
        $task = $this->tasks->findInCurrentWorkspaceOrFail($taskId);

        if (! $this->projects->isMember($task->project, $userId)) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user must be a member of the task project.'],
            ]);
        }

        $this->tasks->removeAssignee($task, $userId);
    }

    /**
     * @param  list<int>  $assigneeIds
     */
    private function validateProjectMembers(Project $project, array $assigneeIds, ?int $reviewerId): void
    {
        foreach ($assigneeIds as $index => $assigneeId) {
            if (! $this->projects->isMember($project, $assigneeId)) {
                throw ValidationException::withMessages([
                    'assignee_ids.'.$index => ['Each assignee must be a member of the task project.'],
                ]);
            }
        }

        if ($reviewerId !== null && ! $this->projects->isMember($project, $reviewerId)) {
            throw ValidationException::withMessages([
                'reviewer_id' => ['The reviewer must be a member of the task project.'],
            ]);
        }
    }

    private function moveWithinTransaction(Task $task, TaskStatus $status, int $position, int $actorId): void
    {
        $previousStatus = $task->status;
        $project = $task->project;
        $previousColumnIds = $this->tasks->taskIdsInColumn($project, $previousStatus, true);
        $nextColumnIds = $previousStatus === $status
            ? $previousColumnIds
            : $this->tasks->taskIdsInColumn($project, $status, true);

        $previousColumnIds = array_values(array_filter(
            $previousColumnIds,
            fn (int $id): bool => $id !== (int) $task->getKey(),
        ));
        $nextColumnIds = array_values(array_filter(
            $nextColumnIds,
            fn (int $id): bool => $id !== (int) $task->getKey(),
        ));
        array_splice($nextColumnIds, min(max($position, 0), count($nextColumnIds)), 0, [(int) $task->getKey()]);

        if ($previousStatus !== $status) {
            $attributes = [
                'status' => $status,
                'completed_at' => $status === TaskStatus::Completed ? now() : null,
            ];

            if ($status === TaskStatus::Completed) {
                $attributes['progress'] = 100;
            }

            $task = $this->tasks->updateTask($task, $attributes);
            $this->tasks->setColumnPositions($previousColumnIds);
            if ($task->exists) {
                TaskStatusChanged::dispatch($task, $previousStatus, $status, $actorId);
            }
        }

        $this->tasks->setColumnPositions($nextColumnIds);
    }
}

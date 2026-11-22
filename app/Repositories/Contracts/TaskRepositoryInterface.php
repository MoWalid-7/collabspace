<?php

namespace App\Repositories\Contracts;

use App\Data\Tasks\TaskFilters;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TaskRepositoryInterface
{
    public function findInCurrentWorkspaceOrFail(int $taskId): Task;

    /**
     * @param  array{search?: string|null, priority?: string|null, assignee_id?: int|null}  $filters
     * @return array<string, Collection<int, Task>>
     */
    public function board(Project $project, array $filters): array;

    public function paginateFiltered(TaskFilters $filters, int $userId): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTask(array $attributes): Task;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateTask(Task $task, array $attributes): Task;

    public function softDelete(Task $task): void;

    public function nextPosition(Project $project, TaskStatus $status): int;

    /**
     * @return list<int>
     */
    public function taskIdsInColumn(Project $project, TaskStatus $status, bool $lockForUpdate = false): array;

    /**
     * @param  list<int>  $taskIds
     */
    public function setColumnPositions(array $taskIds): void;

    /**
     * @param  list<int>  $userIds
     */
    public function syncAssignees(Task $task, array $userIds): void;

    public function addAssignee(Task $task, int $userId): bool;

    public function removeAssignee(Task $task, int $userId): void;
}

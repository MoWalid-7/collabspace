<?php

namespace App\Services\Contracts;

use App\Data\Tasks\CreateTaskData;
use App\Data\Tasks\MoveTaskData;
use App\Data\Tasks\TaskFilters;
use App\Data\Tasks\UpdateTaskData;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TaskServiceInterface
{
    public function list(TaskFilters $filters, int $userId): LengthAwarePaginator;

    /**
     * @param  array{search?: string|null, priority?: string|null, assignee_id?: int|null}  $filters
     * @return array<string, Collection<int, Task>>
     */
    public function board(int $projectId, array $filters): array;

    public function create(CreateTaskData $data): Task;

    public function show(int $taskId): Task;

    public function update(UpdateTaskData $data): Task;

    public function delete(int $taskId): void;

    public function move(MoveTaskData $data): Task;

    public function updateProgress(int $taskId, int $progress): Task;

    public function addAssignee(int $taskId, int $userId): Task;

    public function removeAssignee(int $taskId, int $userId): void;
}

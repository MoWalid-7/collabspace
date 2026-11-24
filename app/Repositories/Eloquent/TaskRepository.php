<?php

namespace App\Repositories\Eloquent;

use App\Data\Tasks\TaskFilters;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TaskRepository implements TaskRepositoryInterface
{
    public function __construct(private ProjectRepositoryInterface $projects) {}

    public function findInCurrentWorkspaceOrFail(int $taskId): Task
    {
        return Task::query()
            ->with(['project', 'assignees', 'reviewer'])
            ->withCount('comments')
            ->findOrFail($taskId);
    }

    public function board(Project $project, array $filters): array
    {
        $columns = [];

        foreach (TaskStatus::cases() as $status) {
            $columns[$status->value] = Task::query()
                ->where('project_id', $project->getKey())
                ->where('status', $status->value)
                ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                    $query->where(function (Builder $searchQuery) use ($search): void {
                        $searchQuery
                            ->where('title', 'like', '%'.$search.'%')
                            ->orWhere('description', 'like', '%'.$search.'%');
                    });
                })
                ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
                ->when($filters['assignee_id'] ?? null, fn (Builder $query, int $assigneeId) => $query->whereHas(
                    'assignees',
                    fn (Builder $assignees) => $assignees->whereKey($assigneeId),
                ))
                ->with(['assignees', 'reviewer', 'project'])
                ->orderBy('position')
                ->orderBy('id')
                ->get();
        }

        return $columns;
    }

    public function paginateFiltered(TaskFilters $filters, int $userId, int $workspaceId): LengthAwarePaginator
    {
        $today = today()->toDateString();

        return Task::query()
            ->where('tasks.workspace_id', $workspaceId)
            ->whereHas('project', fn (Builder $projects) => $this->projects->applyVisibility($projects, $workspaceId, $userId))
            ->when($filters->mine === true, fn (Builder $query) => $query->whereHas(
                'assignees',
                fn (Builder $assignees) => $assignees->whereKey($userId),
            ))
            ->when($filters->status !== null, fn (Builder $query) => $query->where('status', $filters->status))
            ->when($filters->priority !== null, fn (Builder $query) => $query->where('priority', $filters->priority))
            ->when($filters->projectId !== null, fn (Builder $query) => $query->where('project_id', $filters->projectId))
            ->when($filters->search !== null, function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('title', 'like', '%'.$filters->search.'%')
                        ->orWhere('description', 'like', '%'.$filters->search.'%');
                });
            })
            ->when($filters->dueBefore !== null, fn (Builder $query) => $query->whereDate('due_date', '<=', $filters->dueBefore))
            ->when($filters->dueAfter !== null, fn (Builder $query) => $query->whereDate('due_date', '>=', $filters->dueAfter))
            ->when($filters->overdue === true, fn (Builder $query) => $query
                ->whereDate('due_date', '<', $today)
                ->where('status', '!=', TaskStatus::Completed->value))
            ->when($filters->overdue === false, fn (Builder $query) => $query->where(
                fn (Builder $notOverdue) => $notOverdue
                    ->whereNull('due_date')
                    ->orWhereDate('due_date', '>=', $today)
                    ->orWhere('status', TaskStatus::Completed->value),
            ))
            ->with(['assignees', 'reviewer', 'project'])
            ->withCount('comments')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage);
    }

    public function searchVisible(string $term, int $limit, int $userId, int $workspaceId): Collection
    {
        return Task::query()
            ->where('tasks.workspace_id', $workspaceId)
            ->whereHas('project', fn (Builder $projects) => $this->projects->applyVisibility($projects, $workspaceId, $userId))
            ->where(function (Builder $query) use ($term): void {
                $pattern = LikePattern::contains($term);

                $query
                    ->whereRaw('LOWER(tasks.title) LIKE ? ESCAPE ?', [$pattern, '\\'])
                    ->orWhereRaw('LOWER(tasks.description) LIKE ? ESCAPE ?', [$pattern, '\\']);
            })
            ->with('project:id,name')
            ->orderBy('title')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function createTask(array $attributes): Task
    {
        return Task::query()->create($attributes);
    }

    public function updateTask(Task $task, array $attributes): Task
    {
        $task->update($attributes);

        return $this->findInCurrentWorkspaceOrFail((int) $task->getKey());
    }

    public function softDelete(Task $task): void
    {
        $task->delete();
    }

    public function nextPosition(Project $project, TaskStatus $status): int
    {
        $maxPosition = Task::query()
            ->where('project_id', $project->getKey())
            ->where('status', $status->value)
            ->max('position');

        return $maxPosition === null ? 0 : (int) $maxPosition + 1;
    }

    public function taskIdsInColumn(Project $project, TaskStatus $status, bool $lockForUpdate = false): array
    {
        $query = Task::query()
            ->where('project_id', $project->getKey())
            ->where('status', $status->value)
            ->orderBy('position')
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public function setColumnPositions(array $taskIds): void
    {
        foreach ($taskIds as $position => $taskId) {
            Task::query()
                ->whereKey($taskId)
                ->update(['position' => $position]);
        }
    }

    public function syncAssignees(Task $task, array $userIds): void
    {
        $task->assignees()->sync($userIds);
    }

    public function addAssignee(Task $task, int $userId): bool
    {
        return $task->assignees()->syncWithoutDetaching([$userId])['attached'] !== [];
    }

    public function removeAssignee(Task $task, int $userId): void
    {
        $task->assignees()->detach($userId);
    }

    public function countCompletedTasksForUser(int $workspaceId, int $userId): int
    {
        return Task::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', TaskStatus::Completed->value)
            ->whereHas('assignees', fn (Builder $q) => $q->whereKey($userId))
            ->count();
    }
}

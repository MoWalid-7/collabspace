<?php

namespace App\Repositories\Eloquent;

use App\Data\Dashboard\DashboardFilters;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Team;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function __construct(private ProjectRepositoryInterface $projects) {}

    public function stats(DashboardFilters $filters, int $userId, int $workspaceId): array
    {
        $currentMonth = CarbonImmutable::now()->startOfMonth();
        $nextMonth = $currentMonth->addMonth();
        $previousMonth = $currentMonth->subMonth();

        $stats = $this->filteredTasks($filters, $userId, $workspaceId)
            ->selectRaw('COUNT(*) AS total_tasks')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS pending_tasks', [
                TaskStatus::Todo->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END), 0) AS active_tasks', [
                TaskStatus::InProgress->value,
                TaskStatus::InReview->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS completed_tasks', [
                TaskStatus::Completed->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN due_date < ? AND status != ? THEN 1 ELSE 0 END), 0) AS overdue_tasks', [
                today()->toDateString(),
                TaskStatus::Completed->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) AS created_current', [
                $currentMonth,
                $nextMonth,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) AS created_previous', [
                $previousMonth,
                $currentMonth,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN completed_at >= ? AND completed_at < ? THEN 1 ELSE 0 END), 0) AS completed_current', [
                $currentMonth,
                $nextMonth,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN completed_at >= ? AND completed_at < ? THEN 1 ELSE 0 END), 0) AS completed_previous', [
                $previousMonth,
                $currentMonth,
            ])
            ->firstOrFail();

        return [
            'total_tasks' => (int) $stats->getAttribute('total_tasks'),
            'pending_tasks' => (int) $stats->getAttribute('pending_tasks'),
            'active_tasks' => (int) $stats->getAttribute('active_tasks'),
            'completed_tasks' => (int) $stats->getAttribute('completed_tasks'),
            'overdue_tasks' => (int) $stats->getAttribute('overdue_tasks'),
            'created_current' => (int) $stats->getAttribute('created_current'),
            'created_previous' => (int) $stats->getAttribute('created_previous'),
            'completed_current' => (int) $stats->getAttribute('completed_current'),
            'completed_previous' => (int) $stats->getAttribute('completed_previous'),
        ];
    }

    public function createdTimestamps(
        DashboardFilters $filters,
        int $userId,
        int $workspaceId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        return $this->timestamps('created_at', $filters, $userId, $workspaceId, $start, $end);
    }

    public function completedTimestamps(
        DashboardFilters $filters,
        int $userId,
        int $workspaceId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        return $this->timestamps('completed_at', $filters, $userId, $workspaceId, $start, $end);
    }

    public function deadlines(DashboardFilters $filters, int $userId, int $workspaceId): Collection
    {
        return $this->filteredTasks($filters, $userId, $workspaceId)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', today()->addDays(14)->toDateString())
            ->where('status', '!=', TaskStatus::Completed->value)
            ->with(['project:id,name', 'assignees:id,name,avatar'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit($filters->limit)
            ->get();
    }

    public function teams(DashboardFilters $filters, int $userId, int $workspaceId): Collection
    {
        $teams = Team::query()
            ->where('workspace_id', $workspaceId)
            ->withCount('members')
            ->orderBy('name')
            ->orderBy('id')
            ->limit($filters->limit)
            ->get();

        if ($teams->isEmpty()) {
            return $teams;
        }

        $taskCounts = $this->filteredTasks($filters, $userId, $workspaceId)
            ->join('task_assignees', 'tasks.id', '=', 'task_assignees.task_id')
            ->join('team_user', 'task_assignees.user_id', '=', 'team_user.user_id')
            ->select('team_user.team_id')
            ->selectRaw('COUNT(DISTINCT tasks.id) AS tasks_total')
            ->selectRaw('COUNT(DISTINCT CASE WHEN tasks.status = ? THEN tasks.id END) AS tasks_completed', [
                TaskStatus::Completed->value,
            ])
            ->groupBy('team_user.team_id')
            ->get()
            ->keyBy('team_id');

        return $teams->each(function (Team $team) use ($taskCounts): void {
            $counts = $taskCounts->get($team->getKey());
            $total = (int) ($counts?->getAttribute('tasks_total') ?? 0);
            $completed = (int) ($counts?->getAttribute('tasks_completed') ?? 0);

            $team->setAttribute('tasks_total', $total);
            $team->setAttribute('tasks_completed', $completed);
            $team->setAttribute('completion_percent', $total === 0 ? 0 : (int) round($completed / $total * 100));
        });
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function timestamps(
        string $column,
        DashboardFilters $filters,
        int $userId,
        int $workspaceId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        return $this->filteredTasks($filters, $userId, $workspaceId)
            ->whereNotNull($column)
            ->whereBetween($column, [$start, $end])
            ->orderBy($column)
            ->pluck($column)
            ->map(static function (mixed $timestamp): CarbonImmutable {
                return $timestamp instanceof CarbonInterface
                    ? CarbonImmutable::instance($timestamp)
                    : CarbonImmutable::parse($timestamp);
            })
            ->all();
    }

    /**
     * Definition: tasks assigned to at least one member of each team, inside projects
     * visible to the requesting user. Distinct task counts prevent duplicate assignments
     * within a team from inflating the team's progress.
     *
     * @return Builder<Task>
     */
    private function filteredTasks(DashboardFilters $filters, int $userId, int $workspaceId): Builder
    {
        return Task::query()
            ->where('tasks.workspace_id', $workspaceId)
            ->whereHas('project', fn (Builder $projects) => $this->projects->applyVisibility($projects, $workspaceId, $userId))
            ->when($filters->projectId !== null, fn (Builder $tasks) => $tasks->where('project_id', $filters->projectId))
            ->when($filters->mine, fn (Builder $tasks) => $tasks->whereHas(
                'assignees',
                fn (Builder $assignees) => $assignees->whereKey($userId),
            ));
    }
}

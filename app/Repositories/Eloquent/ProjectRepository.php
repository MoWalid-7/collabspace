<?php

namespace App\Repositories\Eloquent;

use App\Data\Projects\ProjectFilters;
use App\Data\Projects\ProjectStatsData;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository extends BaseRepository implements ProjectRepositoryInterface
{
    public function __construct(Project $model)
    {
        parent::__construct($model);
    }

    public function paginateFiltered(ProjectFilters $filters, int $userId): LengthAwarePaginator
    {
        return Project::query()
            ->visibleTo($userId)
            ->withCount(['members', 'teams'])
            ->when($filters->search !== null, function ($query) use ($filters): void {
                $query->where('name', 'like', '%'.$filters->search.'%');
            })
            ->when($filters->status !== null, function ($query) use ($filters): void {
                $query->where('status', $filters->status);
            })
            ->when($filters->priority !== null, function ($query) use ($filters): void {
                $query->where('priority', $filters->priority);
            })
            ->when($filters->teamId !== null, function ($query) use ($filters): void {
                $query->whereHas('teams', fn ($teams) => $teams->whereKey($filters->teamId));
            })
            ->when($filters->sort === 'oldest', fn ($query) => $query->orderBy('created_at'))
            ->when($filters->sort === 'deadline', fn ($query) => $query->orderBy('deadline')->orderByDesc('id'))
            ->when($filters->sort === 'newest', fn ($query) => $query->orderByDesc('created_at'))
            ->paginate($filters->perPage);
    }

    public function findInCurrentWorkspaceOrFail(int $projectId): Project
    {
        return Project::query()
            ->with(['creator', 'members', 'teams'])
            ->withCount(['members', 'teams'])
            ->findOrFail($projectId);
    }

    public function findVisibleToUserOrFail(int $projectId, int $userId): Project
    {
        return Project::query()
            ->visibleTo($userId)
            ->with(['creator', 'members', 'teams'])
            ->withCount(['members', 'teams'])
            ->findOrFail($projectId);
    }

    public function isVisibleTo(Project $project, int $userId): bool
    {
        return Project::query()
            ->visibleTo($userId)
            ->whereKey($project->getKey())
            ->exists();
    }

    public function createProject(array $attributes): Project
    {
        /** @var Project */
        return Project::query()->create($attributes);
    }

    public function updateProject(Project $project, array $attributes): Project
    {
        $project->update($attributes);

        return $this->findInCurrentWorkspaceOrFail((int) $project->getKey());
    }

    public function softDelete(Project $project): void
    {
        $project->delete();
    }

    public function addMembers(Project $project, array $members): void
    {
        $project->members()->syncWithoutDetaching(
            collect($members)->mapWithKeys(fn (User $member): array => [$member->getKey() => []])->all(),
        );
    }

    public function addMember(Project $project, User $user, ?string $roleTitle): User
    {
        $project->members()->syncWithoutDetaching([
            $user->getKey() => ['role_title' => $roleTitle],
        ]);

        return $this->findMemberOrFail($project, (int) $user->getKey());
    }

    public function isMember(Project $project, int $userId): bool
    {
        return $project->members()->whereKey($userId)->exists();
    }

    public function hasTeam(Project $project, Team $team): bool
    {
        return $project->teams()->whereKey($team->getKey())->exists();
    }

    public function members(Project $project): Collection
    {
        return $project->members()->orderBy('name')->get();
    }

    public function findMemberOrFail(Project $project, int $userId): User
    {
        return $project->members()->whereKey($userId)->firstOrFail();
    }

    public function removeMember(Project $project, int $userId): void
    {
        $project->members()->detach($userId);
    }

    public function attachTeam(Project $project, Team $team): void
    {
        $project->teams()->syncWithoutDetaching([$team->getKey()]);
    }

    public function detachTeam(Project $project, Team $team): void
    {
        $project->teams()->detach($team->getKey());
    }

    public function statsFor(Project $project): ProjectStatsData
    {
        $stats = Task::query()
            ->where('project_id', $project->getKey())
            ->selectRaw('COUNT(*) AS total_tasks')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS pending', [TaskStatus::Todo->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS in_progress', [TaskStatus::InProgress->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS in_review', [TaskStatus::InReview->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS completed', [TaskStatus::Completed->value])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN due_date < ? AND status != ? THEN 1 ELSE 0 END), 0) AS overdue',
                [today()->toDateString(), TaskStatus::Completed->value],
            )
            ->firstOrFail();

        $totalTasks = (int) $stats->getAttribute('total_tasks');
        $completed = (int) $stats->getAttribute('completed');

        return new ProjectStatsData(
            totalTasks: $totalTasks,
            pending: (int) $stats->getAttribute('pending'),
            inProgress: (int) $stats->getAttribute('in_progress'),
            inReview: (int) $stats->getAttribute('in_review'),
            completed: $completed,
            overdue: (int) $stats->getAttribute('overdue'),
            progressPercent: $totalTasks === 0 ? 0 : (int) round($completed / $totalTasks * 100),
        );
    }
}

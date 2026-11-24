<?php

namespace App\Repositories\Eloquent;

use App\Data\Teams\TeamFilters;
use App\Models\Team;
use App\Models\User;
use App\Repositories\Contracts\TeamRepositoryInterface;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TeamRepository extends BaseRepository implements TeamRepositoryInterface
{
    public function __construct(Team $model)
    {
        parent::__construct($model);
    }

    public function paginateFiltered(TeamFilters $filters): LengthAwarePaginator
    {
        return Team::query()
            ->with('lead')
            ->withCount('members')
            ->when($filters->search !== null, function ($query) use ($filters): void {
                $query->where('name', 'like', '%'.$filters->search.'%');
            })
            ->orderBy('name')
            ->paginate($filters->perPage);
    }

    public function findInCurrentWorkspaceOrFail(int $teamId): Team
    {
        return Team::query()
            ->with(['lead', 'members'])
            ->withCount('members')
            ->findOrFail($teamId);
    }

    public function findInWorkspaceOrFail(int $teamId, int $workspaceId): Team
    {
        return Team::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($teamId);
    }

    public function createTeam(array $attributes): Team
    {
        /** @var Team */
        return Team::query()->create($attributes);
    }

    public function updateTeam(Team $team, array $attributes): Team
    {
        $team->update($attributes);

        return $this->findInCurrentWorkspaceOrFail((int) $team->getKey());
    }

    public function deleteTeam(Team $team): void
    {
        $team->delete();
    }

    public function isMember(Team $team, int $userId): bool
    {
        return $team->members()->whereKey($userId)->exists();
    }

    public function addMember(Team $team, User $user, ?string $roleTitle): void
    {
        $team->members()->syncWithoutDetaching([
            $user->getKey() => ['role_title' => $roleTitle],
        ]);
    }

    public function findMemberOrFail(Team $team, int $userId): User
    {
        return $team->members()->whereKey($userId)->firstOrFail();
    }

    public function updateMemberRoleTitle(Team $team, int $userId, ?string $roleTitle): void
    {
        $team->members()->updateExistingPivot($userId, ['role_title' => $roleTitle]);
    }

    public function removeMember(Team $team, int $userId): void
    {
        $team->members()->detach($userId);
    }

    public function searchInWorkspace(string $term, int $limit, int $workspaceId): Collection
    {
        return Team::query()
            ->where('workspace_id', $workspaceId)
            ->whereRaw('LOWER(teams.name) LIKE ? ESCAPE ?', [LikePattern::contains($term), '\\'])
            ->orderBy('name')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }
}

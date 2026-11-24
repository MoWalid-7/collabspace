<?php

namespace App\Repositories\Eloquent;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceRepository extends BaseRepository implements WorkspaceRepositoryInterface
{
    public function __construct(Workspace $model)
    {
        parent::__construct($model);
    }

    public function forUser(int $userId): Collection
    {
        return Workspace::query()
            ->whereHas('members', fn ($query) => $query->whereKey($userId))
            ->orderBy('name')
            ->get();
    }

    public function findForMember(int $workspaceId, int $userId): ?Workspace
    {
        return Workspace::query()
            ->whereKey($workspaceId)
            ->whereHas('members', fn ($query) => $query->whereKey($userId))
            ->first();
    }

    public function findWorkspaceForMemberOrFail(int $workspaceId, int $userId): Workspace
    {
        return Workspace::query()
            ->whereKey($workspaceId)
            ->whereHas('members', fn ($query) => $query->whereKey($userId))
            ->firstOrFail();
    }

    public function slugExists(string $slug): bool
    {
        return Workspace::query()->where('slug', $slug)->exists();
    }

    public function createWorkspace(array $attributes): Workspace
    {
        return Workspace::query()->create($attributes);
    }

    public function updateWorkspace(Workspace $workspace, array $attributes): Workspace
    {
        $workspace->update($attributes);

        return $workspace->refresh();
    }

    public function roleFor(Workspace $workspace, int $userId): ?string
    {
        return $workspace->members()->whereKey($userId)->value('workspace_user.role');
    }

    public function roleForWorkspaceId(int $workspaceId, int $userId): ?string
    {
        $workspace = Workspace::query()->findOrFail($workspaceId);

        return $this->roleFor($workspace, $userId);
    }

    public function findWorkspaceMemberOrFail(int $workspaceId, int $userId): User
    {
        $workspace = Workspace::query()->findOrFail($workspaceId);

        return $this->findMemberOrFail($workspace, $userId);
    }

    public function isMember(Workspace $workspace, int $userId): bool
    {
        return $workspace->members()->whereKey($userId)->exists();
    }

    public function addMember(Workspace $workspace, User $user, WorkspaceRole $role): void
    {
        $workspace->members()->syncWithoutDetaching([
            $user->getKey() => ['role' => $role->value],
        ]);
    }

    public function updateMemberRole(Workspace $workspace, int $userId, WorkspaceRole $role): void
    {
        $workspace->members()->updateExistingPivot($userId, ['role' => $role->value]);
    }

    public function removeMember(Workspace $workspace, int $userId): void
    {
        $workspace->members()->detach($userId);
    }

    public function findMemberOrFail(Workspace $workspace, int $userId): User
    {
        return $workspace->members()->whereKey($userId)->firstOrFail();
    }

    public function memberUserIds(int $workspaceId): array
    {
        return User::query()
            ->whereHas('workspaces', function ($q) use ($workspaceId) {
                $q->where('workspaces.id', $workspaceId);
            })
            ->pluck('users.id')
            ->all();
    }
}

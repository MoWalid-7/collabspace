<?php

namespace App\Repositories\Contracts;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface WorkspaceRepositoryInterface
{
    public function find(int|string $id): ?Model;

    public function findOrFail(int|string $id): Model;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Model;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * @return Collection<int, Workspace>
     */
    public function forUser(int $userId): Collection;

    public function findForMember(int $workspaceId, int $userId): ?Workspace;

    public function findWorkspaceForMemberOrFail(int $workspaceId, int $userId): Workspace;

    public function slugExists(string $slug): bool;

    /**
     * @param  array{name: string, slug: string, owner_id: int}  $attributes
     */
    public function createWorkspace(array $attributes): Workspace;

    /**
     * @param  array{name: string}  $attributes
     */
    public function updateWorkspace(Workspace $workspace, array $attributes): Workspace;

    public function roleFor(Workspace $workspace, int $userId): ?string;

    public function roleForWorkspaceId(int $workspaceId, int $userId): ?string;

    public function findWorkspaceMemberOrFail(int $workspaceId, int $userId): User;

    public function isMember(Workspace $workspace, int $userId): bool;

    public function addMember(Workspace $workspace, User $user, WorkspaceRole $role): void;

    public function updateMemberRole(Workspace $workspace, int $userId, WorkspaceRole $role): void;

    public function removeMember(Workspace $workspace, int $userId): void;

    public function findMemberOrFail(Workspace $workspace, int $userId): User;
}

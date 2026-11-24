<?php

namespace App\Repositories\Contracts;

use App\Data\Teams\TeamFilters;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface TeamRepositoryInterface
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

    public function paginateFiltered(TeamFilters $filters): LengthAwarePaginator;

    public function findInCurrentWorkspaceOrFail(int $teamId): Team;

    public function findInWorkspaceOrFail(int $teamId, int $workspaceId): Team;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTeam(array $attributes): Team;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateTeam(Team $team, array $attributes): Team;

    public function deleteTeam(Team $team): void;

    public function isMember(Team $team, int $userId): bool;

    public function addMember(Team $team, User $user, ?string $roleTitle): void;

    public function findMemberOrFail(Team $team, int $userId): User;

    public function updateMemberRoleTitle(Team $team, int $userId, ?string $roleTitle): void;

    public function removeMember(Team $team, int $userId): void;

    /**
     * @return Collection<int, Team>
     */
    public function searchInWorkspace(string $term, int $limit, int $workspaceId): Collection;
}

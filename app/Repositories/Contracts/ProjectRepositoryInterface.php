<?php

namespace App\Repositories\Contracts;

use App\Data\Projects\ProjectFilters;
use App\Data\Projects\ProjectStatsData;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ProjectRepositoryInterface
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

    public function paginateFiltered(ProjectFilters $filters, int $userId): LengthAwarePaginator;

    public function findInCurrentWorkspaceOrFail(int $projectId): Project;

    public function findVisibleToUserOrFail(int $projectId, int $userId): Project;

    public function isVisibleTo(Project $project, int $userId): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createProject(array $attributes): Project;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateProject(Project $project, array $attributes): Project;

    public function softDelete(Project $project): void;

    /**
     * @param  list<User>  $members
     */
    public function addMembers(Project $project, array $members): void;

    public function addMember(Project $project, User $user, ?string $roleTitle): User;

    public function isMember(Project $project, int $userId): bool;

    public function hasTeam(Project $project, Team $team): bool;

    /**
     * @return Collection<int, User>
     */
    public function members(Project $project): Collection;

    public function findMemberOrFail(Project $project, int $userId): User;

    public function removeMember(Project $project, int $userId): void;

    public function attachTeam(Project $project, Team $team): void;

    public function detachTeam(Project $project, Team $team): void;

    public function statsFor(Project $project): ProjectStatsData;
}

<?php

namespace App\Services\Contracts;

use App\Data\Projects\CreateProjectData;
use App\Data\Projects\ProjectDetailsData;
use App\Data\Projects\ProjectFilters;
use App\Data\Projects\ProjectMemberData;
use App\Data\Projects\ProjectTeamData;
use App\Data\Projects\UpdateProjectData;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProjectServiceInterface
{
    public function list(ProjectFilters $filters, int $userId, int $workspaceId): LengthAwarePaginator;

    public function create(CreateProjectData $data): Project;

    public function show(int $projectId, int $userId, int $workspaceId): ProjectDetailsData;

    public function update(UpdateProjectData $data): Project;

    public function delete(int $projectId): void;

    /**
     * @return Collection<int, User>
     */
    public function members(int $projectId, int $userId, int $workspaceId): Collection;

    public function addMember(ProjectMemberData $data): User;

    public function removeMember(ProjectMemberData $data): void;

    public function attachTeam(ProjectTeamData $data): void;

    public function detachTeam(ProjectTeamData $data): void;
}

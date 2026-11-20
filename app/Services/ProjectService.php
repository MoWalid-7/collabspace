<?php

namespace App\Services;

use App\Data\Projects\CreateProjectData;
use App\Data\Projects\ProjectDetailsData;
use App\Data\Projects\ProjectFilters;
use App\Data\Projects\ProjectMemberData;
use App\Data\Projects\ProjectTeamData;
use App\Data\Projects\UpdateProjectData;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TeamRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Contracts\ProjectServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectService implements ProjectServiceInterface
{
    public function __construct(
        private ProjectRepositoryInterface $projects,
        private TeamRepositoryInterface $teams,
        private WorkspaceRepositoryInterface $workspaces,
    ) {}

    public function list(ProjectFilters $filters, int $userId): LengthAwarePaginator
    {
        return $this->projects->paginateFiltered($filters, $userId);
    }

    public function create(CreateProjectData $data): Project
    {
        return DB::transaction(function () use ($data): Project {
            $project = $this->projects->createProject([
                'workspace_id' => $data->workspaceId,
                'name' => $data->name,
                'description' => $data->description,
                'type' => $data->type,
                'priority' => $data->priority,
                'status' => $data->status,
                'start_date' => $data->startDate,
                'deadline' => $data->deadline,
                'created_by' => $data->createdBy,
            ]);

            $memberIds = array_values(array_unique([$data->createdBy, ...$data->memberIds]));
            $members = array_map(
                fn (int $userId): User => $this->workspaces->findWorkspaceMemberOrFail($data->workspaceId, $userId),
                $memberIds,
            );
            $this->projects->addMembers($project, $members);

            foreach (array_unique($data->teamIds) as $teamId) {
                $team = $this->teams->findInWorkspaceOrFail($teamId, $data->workspaceId);
                $this->projects->attachTeam($project, $team);
            }

            return $this->projects->findInCurrentWorkspaceOrFail((int) $project->getKey());
        });
    }

    public function show(int $projectId, int $userId): ProjectDetailsData
    {
        $project = $this->projects->findVisibleToUserOrFail($projectId, $userId);

        return new ProjectDetailsData($project, $this->projects->statsFor($project));
    }

    public function update(UpdateProjectData $data): Project
    {
        $project = $this->projects->findInCurrentWorkspaceOrFail($data->projectId);

        return $this->projects->updateProject($project, $data->attributes);
    }

    public function delete(int $projectId): void
    {
        $this->projects->softDelete($this->projects->findInCurrentWorkspaceOrFail($projectId));
    }

    public function members(int $projectId, int $userId): Collection
    {
        $project = $this->projects->findVisibleToUserOrFail($projectId, $userId);

        return $this->projects->members($project);
    }

    public function addMember(ProjectMemberData $data): User
    {
        $project = $this->projects->findInCurrentWorkspaceOrFail($data->projectId);
        $user = $this->workspaces->findWorkspaceMemberOrFail(
            (int) $project->workspace_id,
            $data->userId,
        );

        if ($this->projects->isMember($project, $data->userId)) {
            throw ValidationException::withMessages([
                'user_id' => ['This user is already a member of the project.'],
            ]);
        }

        return $this->projects->addMember($project, $user, $data->roleTitle);
    }

    public function removeMember(ProjectMemberData $data): void
    {
        $project = $this->projects->findInCurrentWorkspaceOrFail($data->projectId);
        $this->projects->findMemberOrFail($project, $data->userId);
        $this->projects->removeMember($project, $data->userId);
    }

    public function attachTeam(ProjectTeamData $data): void
    {
        $project = $this->projects->findInCurrentWorkspaceOrFail($data->projectId);
        $team = $this->teams->findInWorkspaceOrFail($data->teamId, (int) $project->workspace_id);

        if ($this->projects->hasTeam($project, $team)) {
            throw ValidationException::withMessages([
                'team_id' => ['This team is already attached to the project.'],
            ]);
        }

        $this->projects->attachTeam($project, $team);
    }

    public function detachTeam(ProjectTeamData $data): void
    {
        $project = $this->projects->findInCurrentWorkspaceOrFail($data->projectId);
        $team = $this->teams->findInWorkspaceOrFail($data->teamId, (int) $project->workspace_id);

        if (! $this->projects->hasTeam($project, $team)) {
            throw (new ModelNotFoundException)->setModel(Team::class, [$team->getKey()]);
        }

        $this->projects->detachTeam($project, $team);
    }
}

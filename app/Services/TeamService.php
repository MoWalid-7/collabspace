<?php

namespace App\Services;

use App\Data\Teams\CreateTeamData;
use App\Data\Teams\TeamFilters;
use App\Data\Teams\TeamMemberData;
use App\Data\Teams\UpdateTeamData;
use App\Models\Team;
use App\Models\User;
use App\Repositories\Contracts\TeamRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Contracts\TeamServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class TeamService implements TeamServiceInterface
{
    public function __construct(
        private TeamRepositoryInterface $teams,
        private WorkspaceRepositoryInterface $workspaces,
    ) {}

    public function list(TeamFilters $filters): LengthAwarePaginator
    {
        return $this->teams->paginateFiltered($filters);
    }

    public function create(CreateTeamData $data): Team
    {
        if ($data->leadId !== null) {
            $this->workspaces->findWorkspaceMemberOrFail($data->workspaceId, $data->leadId);
        }

        return $this->teams->createTeam([
            'workspace_id' => $data->workspaceId,
            'name' => $data->name,
            'description' => $data->description,
            'lead_id' => $data->leadId,
        ]);
    }

    public function show(int $teamId): Team
    {
        return $this->teams->findInCurrentWorkspaceOrFail($teamId);
    }

    public function update(UpdateTeamData $data): Team
    {
        $team = $this->teams->findInCurrentWorkspaceOrFail($data->teamId);

        if (array_key_exists('lead_id', $data->attributes) && $data->attributes['lead_id'] !== null) {
            $this->workspaces->findWorkspaceMemberOrFail(
                (int) $team->workspace_id,
                (int) $data->attributes['lead_id'],
            );
        }

        return $this->teams->updateTeam($team, $data->attributes);
    }

    public function delete(int $teamId): void
    {
        $this->teams->deleteTeam($this->teams->findInCurrentWorkspaceOrFail($teamId));
    }

    public function addMember(TeamMemberData $data): User
    {
        $team = $this->teams->findInCurrentWorkspaceOrFail($data->teamId);
        $user = $this->workspaces->findWorkspaceMemberOrFail((int) $team->workspace_id, $data->userId);

        if ($this->teams->isMember($team, $data->userId)) {
            throw ValidationException::withMessages([
                'user_id' => ['This user is already a member of the team.'],
            ]);
        }

        $this->teams->addMember($team, $user, $data->roleTitle);

        return $this->teams->findMemberOrFail($team, $data->userId);
    }

    public function updateMember(TeamMemberData $data): User
    {
        $team = $this->teams->findInCurrentWorkspaceOrFail($data->teamId);
        $this->teams->findMemberOrFail($team, $data->userId);
        $this->teams->updateMemberRoleTitle($team, $data->userId, $data->roleTitle);

        return $this->teams->findMemberOrFail($team, $data->userId);
    }

    public function removeMember(TeamMemberData $data): void
    {
        $team = $this->teams->findInCurrentWorkspaceOrFail($data->teamId);
        $this->teams->findMemberOrFail($team, $data->userId);
        $this->teams->removeMember($team, $data->userId);
    }
}

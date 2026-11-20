<?php

namespace App\Http\Controllers\Api\V1\Teams;

use App\Data\Teams\CreateTeamData;
use App\Data\Teams\TeamFilters;
use App\Data\Teams\TeamMemberData;
use App\Data\Teams\UpdateTeamData;
use App\Http\Requests\Teams\AddTeamMemberRequest;
use App\Http\Requests\Teams\CreateTeamRequest;
use App\Http\Requests\Teams\DeleteTeamRequest;
use App\Http\Requests\Teams\ListTeamsRequest;
use App\Http\Requests\Teams\RemoveTeamMemberRequest;
use App\Http\Requests\Teams\ShowTeamRequest;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Http\Resources\MemberResource;
use App\Http\Resources\TeamResource;
use App\Services\Contracts\TeamServiceInterface;
use App\Support\ApiResponse;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TeamController
{
    public function index(ListTeamsRequest $request, TeamServiceInterface $teams): JsonResponse
    {
        $paginator = $teams->list(TeamFilters::from($request->validated()));

        return ApiResponse::success([
            'teams' => TeamResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(
        CreateTeamRequest $request,
        TeamServiceInterface $teams,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $team = $teams->create(CreateTeamData::from(
            $request->validated(),
            $currentWorkspace->requireId(),
        ));

        return ApiResponse::created([
            'team' => TeamResource::make($team),
        ], 'Team created successfully.');
    }

    public function show(ShowTeamRequest $request, TeamServiceInterface $teams, int $team): JsonResponse
    {
        return ApiResponse::success([
            'team' => TeamResource::make($teams->show($team)),
        ]);
    }

    public function update(
        UpdateTeamRequest $request,
        TeamServiceInterface $teams,
        int $team,
    ): JsonResponse {
        $updatedTeam = $teams->update(UpdateTeamData::from($team, $request->validated()));

        return ApiResponse::success([
            'team' => TeamResource::make($updatedTeam),
        ], 'Team updated successfully.');
    }

    public function destroy(DeleteTeamRequest $request, TeamServiceInterface $teams, int $team): Response
    {
        $teams->delete($team);

        return ApiResponse::noContent();
    }

    public function addMember(
        AddTeamMemberRequest $request,
        TeamServiceInterface $teams,
        int $team,
    ): JsonResponse {
        $member = $teams->addMember(new TeamMemberData(
            teamId: $team,
            userId: (int) $request->validated('user_id'),
            roleTitle: $request->validated('role_title'),
        ));

        return ApiResponse::created([
            'member' => MemberResource::make($member),
        ], 'Team member added successfully.');
    }

    public function updateMember(
        UpdateTeamMemberRequest $request,
        TeamServiceInterface $teams,
        int $team,
        int $user,
    ): JsonResponse {
        $member = $teams->updateMember(new TeamMemberData(
            teamId: $team,
            userId: $user,
            roleTitle: $request->validated('role_title'),
        ));

        return ApiResponse::success([
            'member' => MemberResource::make($member),
        ], 'Team member updated successfully.');
    }

    public function removeMember(
        RemoveTeamMemberRequest $request,
        TeamServiceInterface $teams,
        int $team,
        int $user,
    ): Response {
        $teams->removeMember(new TeamMemberData(
            teamId: $team,
            userId: $user,
            roleTitle: null,
        ));

        return ApiResponse::noContent();
    }
}

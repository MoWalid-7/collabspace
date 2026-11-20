<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Data\Projects\CreateProjectData;
use App\Data\Projects\ProjectFilters;
use App\Data\Projects\ProjectMemberData;
use App\Data\Projects\ProjectTeamData;
use App\Data\Projects\UpdateProjectData;
use App\Http\Requests\Projects\AddProjectMemberRequest;
use App\Http\Requests\Projects\AttachProjectTeamRequest;
use App\Http\Requests\Projects\CreateProjectRequest;
use App\Http\Requests\Projects\DeleteProjectRequest;
use App\Http\Requests\Projects\DetachProjectTeamRequest;
use App\Http\Requests\Projects\ListProjectMembersRequest;
use App\Http\Requests\Projects\ListProjectsRequest;
use App\Http\Requests\Projects\RemoveProjectMemberRequest;
use App\Http\Requests\Projects\ShowProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\MemberResource;
use App\Http\Resources\ProjectListResource;
use App\Http\Resources\ProjectResource;
use App\Services\Contracts\ProjectServiceInterface;
use App\Support\ApiResponse;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProjectController
{
    public function index(ListProjectsRequest $request, ProjectServiceInterface $projects): JsonResponse
    {
        $paginator = $projects->list(
            ProjectFilters::from($request->validated()),
            (int) $request->user()->getKey(),
        );

        return ApiResponse::success([
            'projects' => ProjectListResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(
        CreateProjectRequest $request,
        ProjectServiceInterface $projects,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $project = $projects->create(CreateProjectData::from(
            $request->validated(),
            $currentWorkspace->requireId(),
            (int) $request->user()->getKey(),
        ));

        return ApiResponse::created([
            'project' => ProjectResource::make($project),
        ], 'Project created successfully.');
    }

    public function show(
        ShowProjectRequest $request,
        ProjectServiceInterface $projects,
        int $project,
    ): JsonResponse {
        return ApiResponse::success([
            'project' => ProjectResource::make(
                $projects->show($project, (int) $request->user()->getKey()),
            ),
        ]);
    }

    public function update(
        UpdateProjectRequest $request,
        ProjectServiceInterface $projects,
        int $project,
    ): JsonResponse {
        $updatedProject = $projects->update(UpdateProjectData::from($project, $request->validated()));

        return ApiResponse::success([
            'project' => ProjectResource::make($updatedProject),
        ], 'Project updated successfully.');
    }

    public function destroy(
        DeleteProjectRequest $request,
        ProjectServiceInterface $projects,
        int $project,
    ): Response {
        $projects->delete($project);

        return ApiResponse::noContent();
    }

    public function members(
        ListProjectMembersRequest $request,
        ProjectServiceInterface $projects,
        int $project,
    ): JsonResponse {
        return ApiResponse::success([
            'members' => MemberResource::collection(
                $projects->members($project, (int) $request->user()->getKey()),
            ),
        ]);
    }

    public function addMember(
        AddProjectMemberRequest $request,
        ProjectServiceInterface $projects,
        int $project,
    ): JsonResponse {
        $member = $projects->addMember(new ProjectMemberData(
            projectId: $project,
            userId: (int) $request->validated('user_id'),
            roleTitle: $request->validated('role_title'),
        ));

        return ApiResponse::created([
            'member' => MemberResource::make($member),
        ], 'Project member added successfully.');
    }

    public function removeMember(
        RemoveProjectMemberRequest $request,
        ProjectServiceInterface $projects,
        int $project,
        int $user,
    ): Response {
        $projects->removeMember(new ProjectMemberData(
            projectId: $project,
            userId: $user,
            roleTitle: null,
        ));

        return ApiResponse::noContent();
    }

    public function attachTeam(
        AttachProjectTeamRequest $request,
        ProjectServiceInterface $projects,
        int $project,
    ): JsonResponse {
        $projects->attachTeam(new ProjectTeamData(
            projectId: $project,
            teamId: (int) $request->validated('team_id'),
        ));

        return ApiResponse::success(message: 'Team attached to project successfully.');
    }

    public function detachTeam(
        DetachProjectTeamRequest $request,
        ProjectServiceInterface $projects,
        int $project,
    ): Response {
        $projects->detachTeam(new ProjectTeamData(
            projectId: $project,
            teamId: (int) $request->validated('team_id'),
        ));

        return ApiResponse::noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Workspaces\CreateWorkspaceData;
use App\Data\Workspaces\InviteWorkspaceMemberData;
use App\Data\Workspaces\RemoveWorkspaceMemberData;
use App\Data\Workspaces\UpdateWorkspaceData;
use App\Data\Workspaces\UpdateWorkspaceMemberRoleData;
use App\Enums\WorkspaceRole;
use App\Http\Requests\Workspaces\CreateWorkspaceRequest;
use App\Http\Requests\Workspaces\InviteWorkspaceMemberRequest;
use App\Http\Requests\Workspaces\ListWorkspacesRequest;
use App\Http\Requests\Workspaces\RemoveWorkspaceMemberRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceMemberRoleRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Http\Requests\Workspaces\ViewWorkspaceRequest;
use App\Http\Resources\WorkspaceMemberResource;
use App\Http\Resources\WorkspaceResource;
use App\Services\Contracts\WorkspaceServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class WorkspaceController
{
    public function index(ListWorkspacesRequest $request, WorkspaceServiceInterface $workspaces): JsonResponse
    {
        return ApiResponse::success([
            'workspaces' => WorkspaceResource::collection(
                $workspaces->listForUser($request->user()->getKey()),
            ),
        ]);
    }

    public function store(CreateWorkspaceRequest $request, WorkspaceServiceInterface $workspaces): JsonResponse
    {
        $workspace = $workspaces->create(new CreateWorkspaceData(
            name: $request->validated('name'),
            ownerId: $request->user()->getKey(),
        ));

        return ApiResponse::created([
            'workspace' => WorkspaceResource::make($workspace),
        ], 'Workspace created successfully.');
    }

    public function show(
        ViewWorkspaceRequest $request,
        WorkspaceServiceInterface $workspaces,
        int $workspace,
    ): JsonResponse {
        return ApiResponse::success([
            'workspace' => WorkspaceResource::make(
                $workspaces->show($workspace, $request->user()->getKey()),
            ),
        ]);
    }

    public function update(
        UpdateWorkspaceRequest $request,
        WorkspaceServiceInterface $workspaces,
        int $workspace,
    ): JsonResponse {
        $updatedWorkspace = $workspaces->update(new UpdateWorkspaceData(
            workspaceId: $workspace,
            userId: $request->user()->getKey(),
            name: $request->validated('name'),
        ));

        return ApiResponse::success([
            'workspace' => WorkspaceResource::make($updatedWorkspace),
        ], 'Workspace updated successfully.');
    }

    public function addMember(
        InviteWorkspaceMemberRequest $request,
        WorkspaceServiceInterface $workspaces,
        int $workspace,
    ): JsonResponse {
        $member = $workspaces->invite(new InviteWorkspaceMemberData(
            workspaceId: $workspace,
            invitedBy: $request->user()->getKey(),
            email: $request->validated('email'),
            role: WorkspaceRole::from($request->validated('role')),
        ));

        return ApiResponse::created([
            'member' => WorkspaceMemberResource::make($member),
        ], 'Workspace member added successfully.');
    }

    public function updateMemberRole(
        UpdateWorkspaceMemberRoleRequest $request,
        WorkspaceServiceInterface $workspaces,
        int $workspace,
        int $user,
    ): JsonResponse {
        $member = $workspaces->updateMemberRole(new UpdateWorkspaceMemberRoleData(
            workspaceId: $workspace,
            memberId: $user,
            changedBy: $request->user()->getKey(),
            role: WorkspaceRole::from($request->validated('role')),
        ));

        return ApiResponse::success([
            'member' => WorkspaceMemberResource::make($member),
        ], 'Workspace member role updated successfully.');
    }

    public function removeMember(
        RemoveWorkspaceMemberRequest $request,
        WorkspaceServiceInterface $workspaces,
        int $workspace,
        int $user,
    ): Response {
        $workspaces->removeMember(new RemoveWorkspaceMemberData(
            workspaceId: $workspace,
            memberId: $user,
            removedBy: $request->user()->getKey(),
        ));

        return ApiResponse::noContent();
    }
}

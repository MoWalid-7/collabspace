<?php

use App\Data\Projects\CreateProjectData;
use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TeamRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\ProjectService;
use Mockery;

it('adds the creator to project members while creating the project', function () {
    $creator = User::factory()->make();
    $creator->id = 21;
    $project = new Project;
    $project->id = 42;
    $projects = Mockery::mock(ProjectRepositoryInterface::class);
    $teams = Mockery::mock(TeamRepositoryInterface::class);
    $workspaces = Mockery::mock(WorkspaceRepositoryInterface::class);

    $projects->shouldReceive('createProject')
        ->once()
        ->with([
            'workspace_id' => 11,
            'name' => 'Launch',
            'description' => null,
            'type' => 'SaaS',
            'priority' => ProjectPriority::High,
            'status' => ProjectStatus::Active,
            'start_date' => '2026-03-01',
            'deadline' => '2026-04-01',
            'created_by' => 21,
        ])
        ->andReturn($project);
    $workspaces->shouldReceive('findWorkspaceMemberOrFail')
        ->once()
        ->with(11, 21)
        ->andReturn($creator);
    $projects->shouldReceive('addMembers')
        ->once()
        ->with($project, [$creator]);
    $projects->shouldReceive('findInCurrentWorkspaceOrFail')
        ->once()
        ->with(42)
        ->andReturn($project);

    $created = (new ProjectService($projects, $teams, $workspaces))->create(
        new CreateProjectData(
            workspaceId: 11,
            createdBy: 21,
            name: 'Launch',
            description: null,
            type: 'SaaS',
            priority: ProjectPriority::High,
            status: ProjectStatus::Active,
            startDate: '2026-03-01',
            deadline: '2026-04-01',
            memberIds: [],
            teamIds: [],
        ),
    );

    expect($created)->toBe($project);
});

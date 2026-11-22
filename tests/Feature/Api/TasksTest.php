<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Events\TaskAssigned;
use App\Events\TaskCreated;
use App\Events\TaskStatusChanged;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/TaskTestHelpers.php';

it('creates default task and comment factories in the same workspace as their parent records', function () {
    $task = Task::factory()->create();
    $project = Project::withoutGlobalScopes()->findOrFail($task->project_id);
    $comment = Comment::factory()->create();
    $commentTask = Task::withoutGlobalScopes()->findOrFail($comment->commentable_id);

    expect($task->workspace_id)->toBe($project->workspace_id)
        ->and($task->created_by)->toBe($project->created_by)
        ->and($comment->workspace_id)->toBe($commentTask->workspace_id)
        ->and($comment->user_id)->toBe($commentTask->created_by);
});

it('creates tasks, assigns project members, and returns their ordered board', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $workspace = taskTestWorkspace($owner);
    addTaskTestWorkspaceMember($workspace, $assignee);
    $project = taskTestProject($workspace, $owner, [$assignee]);

    Event::fake([TaskCreated::class, TaskAssigned::class]);

    $response = $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/tasks", [
            'title' => 'Prepare launch',
            'description' => 'Review the checklist',
            'priority' => TaskPriority::High->value,
            'status' => TaskStatus::Todo->value,
            'due_date' => today()->addDays(3)->toDateString(),
            'assignee_ids' => [$assignee->id],
            'reviewer_id' => $assignee->id,
            'progress' => 35,
        ])
        ->assertCreated()
        ->assertJsonPath('data.task.title', 'Prepare launch')
        ->assertJsonPath('data.task.progress', 35)
        ->assertJsonPath('data.task.assignees.0.id', $assignee->id)
        ->assertJsonPath('data.task.reviewer.id', $assignee->id)
        ->assertJsonPath('data.task.project.id', $project->id)
        ->assertJsonPath('data.task.comments_count', 0);

    $taskId = $response->json('data.task.id');
    $this->assertDatabaseHas('tasks', [
        'id' => $taskId,
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'position' => 0,
    ]);
    $this->assertDatabaseHas('task_assignees', [
        'task_id' => $taskId,
        'user_id' => $assignee->id,
    ]);
    Event::assertDispatched(TaskCreated::class);
    Event::assertDispatched(TaskAssigned::class);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/tasks/{$taskId}")
        ->assertOk()
        ->assertJsonPath('data.task.id', $taskId);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Second task'])
        ->assertCreated()
        ->assertJsonPath('data.task.position', 1);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/projects/{$project->id}/tasks/board?priority=high&assignee_id={$assignee->id}")
        ->assertOk()
        ->assertJsonPath('data.board.columns.todo.0.id', $taskId)
        ->assertJsonPath('data.board.columns.in_progress', []);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/tasks')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2);
});

it('moves tasks between and within columns while keeping positions contiguous', function () {
    $owner = User::factory()->create();
    $workspace = taskTestWorkspace($owner);
    $project = taskTestProject($workspace, $owner);
    $first = taskTestRecord($project, $owner, 'First');
    $moving = taskTestRecord($project, $owner, 'Moving', ['position' => 1]);
    $third = taskTestRecord($project, $owner, 'Third', ['position' => 2]);
    $targetFirst = taskTestRecord($project, $owner, 'Target first', [
        'status' => TaskStatus::InProgress,
    ]);
    $targetSecond = taskTestRecord($project, $owner, 'Target second', [
        'status' => TaskStatus::InProgress,
        'position' => 1,
    ]);

    Event::fake([TaskStatusChanged::class]);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/tasks/{$moving->id}/move", [
            'status' => TaskStatus::InProgress->value,
            'position' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.task.status', TaskStatus::InProgress->value);

    $this->assertDatabaseHas('tasks', ['id' => $first->id, 'position' => 0]);
    $this->assertDatabaseHas('tasks', ['id' => $third->id, 'position' => 1]);
    $this->assertDatabaseHas('tasks', ['id' => $targetFirst->id, 'position' => 0]);
    $this->assertDatabaseHas('tasks', ['id' => $moving->id, 'position' => 1]);
    $this->assertDatabaseHas('tasks', ['id' => $targetSecond->id, 'position' => 2]);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/tasks/{$moving->id}/move", [
            'status' => TaskStatus::InProgress->value,
            'position' => 2,
        ])
        ->assertOk();

    $this->assertDatabaseHas('tasks', ['id' => $targetFirst->id, 'position' => 0]);
    $this->assertDatabaseHas('tasks', ['id' => $targetSecond->id, 'position' => 1]);
    $this->assertDatabaseHas('tasks', ['id' => $moving->id, 'position' => 2]);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/tasks/{$moving->id}/move", [
            'status' => TaskStatus::Completed->value,
            'position' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('data.task.progress', 100);

    $this->assertDatabaseHas('tasks', [
        'id' => $moving->id,
        'status' => TaskStatus::Completed->value,
        'progress' => 100,
    ]);
    expect($moving->fresh()->completed_at)->not->toBeNull();

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/tasks/{$moving->id}/move", [
            'status' => TaskStatus::Todo->value,
            'position' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.task.completed_at', null);

    $this->assertDatabaseHas('tasks', [
        'id' => $first->id,
        'position' => 0,
    ]);
    $this->assertDatabaseHas('tasks', [
        'id' => $moving->id,
        'status' => TaskStatus::Todo->value,
        'position' => 1,
        'completed_at' => null,
        'progress' => 100,
    ]);
    $this->assertDatabaseHas('tasks', ['id' => $third->id, 'position' => 2]);
    Event::assertDispatchedTimes(TaskStatusChanged::class, 3);
});

it('filters tasks by assignee, status, due dates, overdue state, and paginates results', function () {
    $owner = User::factory()->create();
    $anotherMember = User::factory()->create();
    $workspace = taskTestWorkspace($owner);
    addTaskTestWorkspaceMember($workspace, $anotherMember);
    $project = taskTestProject($workspace, $owner, [$anotherMember]);
    $overdue = taskTestRecord($project, $owner, 'Overdue task', [
        'due_date' => today()->subDay(),
    ]);
    $dueToday = taskTestRecord($project, $owner, 'Due today', [
        'status' => TaskStatus::InProgress,
        'due_date' => today(),
    ]);
    $notMine = taskTestRecord($project, $owner, 'Other owner task', [
        'due_date' => today()->subDay(),
    ]);
    $completed = taskTestRecord($project, $owner, 'Completed task', [
        'status' => TaskStatus::Completed,
        'due_date' => today()->subDay(),
    ]);
    $overdue->assignees()->attach($owner->id);
    $dueToday->assignees()->attach($owner->id);
    $notMine->assignees()->attach($anotherMember->id);
    $completed->assignees()->attach($owner->id);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/tasks?mine=true&status=todo&overdue=true&due_after='.today()->subDays(2)->toDateString().'&due_before='.today()->toDateString().'&per_page=1')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.pagination.per_page', 1)
        ->assertJsonPath('data.tasks.0.id', $overdue->id)
        ->assertJsonPath('data.tasks.0.is_overdue', true);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/tasks?mine=false&overdue=false')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2);
});

it('updates tasks and progress, manages assignees, and soft deletes tasks', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = taskTestWorkspace($owner);
    addTaskTestWorkspaceMember($workspace, $member);
    $project = taskTestProject($workspace, $owner, [$member]);
    $task = taskTestRecord($project, $owner, 'Initial task');

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/tasks/{$task->id}", [
            'title' => 'Updated task',
            'assignee_ids' => [$member->id],
            'reviewer_id' => $member->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.task.title', 'Updated task')
        ->assertJsonPath('data.task.reviewer.id', $member->id);

    $this->assertDatabaseHas('task_assignees', [
        'task_id' => $task->id,
        'user_id' => $member->id,
    ]);

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/tasks/{$task->id}/progress", ['progress' => 65])
        ->assertOk()
        ->assertJsonPath('data.task.progress', 65);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'progress' => 65]);

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/tasks/{$task->id}/assignees", ['user_id' => $owner->id])
        ->assertOk()
        ->assertJsonFragment(['id' => $owner->id]);

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/tasks/{$task->id}/assignees/{$member->id}")
        ->assertNoContent();

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/tasks/{$task->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('tasks', ['id' => $task->id]);
});

it('returns validation errors for invalid task input and non-project assignees or reviewers', function () {
    $owner = User::factory()->create();
    $nonMember = User::factory()->create();
    $workspace = taskTestWorkspace($owner);
    addTaskTestWorkspaceMember($workspace, $nonMember);
    $project = taskTestProject($workspace, $owner);
    $task = taskTestRecord($project, $owner, 'Existing task');

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/tasks", [
            'title' => 'Invalid membership',
            'assignee_ids' => [$nonMember->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assignee_ids.0']);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/tasks", [
            'title' => 'Invalid reviewer',
            'reviewer_id' => $nonMember->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reviewer_id']);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/tasks/{$task->id}/progress", ['progress' => 101])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['progress']);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/tasks/{$task->id}", ['title' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/tasks?status=invalid')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/projects/{$project->id}/tasks/board?priority=invalid")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['priority']);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/tasks/{$task->id}/assignees", ['user_id' => $nonMember->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/tasks/{$task->id}/move", [
            'status' => 'unknown',
            'position' => -1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'position']);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/tasks", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('forbids non-members from reading or changing tasks and hides cross-workspace tasks', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $workspace = taskTestWorkspace($owner);
    addTaskTestWorkspaceMember($workspace, $outsider);
    $project = taskTestProject($workspace, $owner);
    $task = taskTestRecord($project, $owner, 'Private task');
    $otherWorkspace = taskTestWorkspace($outsider);
    $foreignProject = taskTestProject($otherWorkspace, $outsider);
    $foreignTask = taskTestRecord($foreignProject, $outsider, 'Foreign task');

    $this->actingAs($outsider, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertForbidden();

    $this->actingAs($outsider, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/tasks/{$task->id}", ['title' => 'Denied'])
        ->assertForbidden();

    $this->actingAs($outsider, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/tasks/{$task->id}")
        ->assertForbidden();

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/tasks/{$foreignTask->id}")
        ->assertNotFound();

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/projects/{$foreignProject->id}/tasks/board")
        ->assertNotFound();

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$foreignProject->id}/tasks", ['title' => 'Hidden'])
        ->assertNotFound();
});

it('forbids non-members from creating, moving, assigning, or commenting on a project task', function () {
    $owner = User::factory()->create();
    $nonMember = User::factory()->create();
    $workspace = taskTestWorkspace($owner);
    addTaskTestWorkspaceMember($workspace, $nonMember);
    $project = taskTestProject($workspace, $owner);
    $task = taskTestRecord($project, $owner, 'Private task');

    $this->actingAs($nonMember, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/projects/{$project->id}/tasks/board")
        ->assertForbidden();

    $this->actingAs($nonMember, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Denied'])
        ->assertForbidden();

    $requests = [
        ['PATCH', "/api/v1/tasks/{$task->id}/move", ['status' => TaskStatus::Todo->value, 'position' => 0]],
        ['PATCH', "/api/v1/tasks/{$task->id}/progress", ['progress' => 10]],
        ['POST', "/api/v1/tasks/{$task->id}/assignees", ['user_id' => $owner->id]],
        ['DELETE', "/api/v1/tasks/{$task->id}/assignees/{$owner->id}", []],
        ['GET', "/api/v1/tasks/{$task->id}/comments", []],
        ['POST', "/api/v1/tasks/{$task->id}/comments", ['body' => 'Denied']],
    ];

    foreach ($requests as [$method, $uri, $payload]) {
        $this->actingAs($nonMember, 'sanctum')
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->json($method, $uri, $payload)
            ->assertForbidden();
    }
});

it('allows project viewers to read tasks while limiting changes to project members', function () {
    $owner = User::factory()->create();
    $teamMember = User::factory()->create();
    $workspace = taskTestWorkspace($owner);
    addTaskTestWorkspaceMember($workspace, $teamMember);
    $project = taskTestProject($workspace, $owner);
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $team->members()->attach($teamMember->id);
    $project->teams()->attach($team->id);
    $task = taskTestRecord($project, $owner, 'Team-visible task');

    $this->actingAs($teamMember, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertOk();

    $this->actingAs($teamMember, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/tasks/{$task->id}", ['title' => 'Denied'])
        ->assertForbidden();
});

it('returns 404 for every task operation on a task from another workspace', function () {
    $owner = User::factory()->create();
    $foreignOwner = User::factory()->create();
    $workspace = taskTestWorkspace($owner);
    $foreignWorkspace = taskTestWorkspace($foreignOwner);
    $foreignProject = taskTestProject($foreignWorkspace, $foreignOwner);
    $foreignTask = taskTestRecord($foreignProject, $foreignOwner, 'Foreign task');

    $requests = [
        ['GET', "/api/v1/tasks/{$foreignTask->id}", []],
        ['PUT', "/api/v1/tasks/{$foreignTask->id}", ['title' => 'Hidden']],
        ['PATCH', "/api/v1/tasks/{$foreignTask->id}", ['title' => 'Hidden']],
        ['DELETE', "/api/v1/tasks/{$foreignTask->id}", []],
        ['PATCH', "/api/v1/tasks/{$foreignTask->id}/move", ['status' => TaskStatus::Todo->value, 'position' => 0]],
        ['PATCH', "/api/v1/tasks/{$foreignTask->id}/progress", ['progress' => 10]],
        ['POST', "/api/v1/tasks/{$foreignTask->id}/assignees", ['user_id' => $foreignOwner->id]],
        ['DELETE', "/api/v1/tasks/{$foreignTask->id}/assignees/{$foreignOwner->id}", []],
        ['GET', "/api/v1/tasks/{$foreignTask->id}/comments", []],
        ['POST', "/api/v1/tasks/{$foreignTask->id}/comments", ['body' => 'Hidden']],
    ];

    foreach ($requests as [$method, $uri, $payload]) {
        $this->actingAs($owner, 'sanctum')
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->json($method, $uri, $payload)
            ->assertNotFound();
    }
});

it('allows a workspace manager to delete another project member task', function () {
    $creator = User::factory()->create();
    $manager = User::factory()->create();
    $workspace = taskTestWorkspace($creator);
    addTaskTestWorkspaceMember($workspace, $manager, WorkspaceRole::Manager);
    $project = taskTestProject($workspace, $creator);
    $task = taskTestRecord($project, $creator, 'Manager cleanup');

    $this->actingAs($manager, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/tasks/{$task->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('tasks', ['id' => $task->id]);
});

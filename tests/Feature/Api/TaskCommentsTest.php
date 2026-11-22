<?php

use App\Events\CommentAdded;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/TaskTestHelpers.php';

it('adds, lists, updates, and soft deletes task comments', function () {
    $owner = User::factory()->create();
    $workspace = taskTestWorkspace($owner);
    $project = taskTestProject($workspace, $owner);
    $task = taskTestRecord($project, $owner, 'Commented task');

    Event::fake([CommentAdded::class]);

    $response = $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Initial note'])
        ->assertCreated()
        ->assertJsonPath('data.comment.body', 'Initial note')
        ->assertJsonPath('data.comment.user.id', $owner->id);

    $commentId = $response->json('data.comment.id');
    $this->assertDatabaseHas('comments', [
        'id' => $commentId,
        'workspace_id' => $workspace->id,
        'commentable_type' => Task::class,
        'commentable_id' => $task->id,
        'user_id' => $owner->id,
    ]);
    Event::assertDispatched(CommentAdded::class);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/tasks/{$task->id}/comments?per_page=1")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.comments.0.body', 'Initial note');

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/tasks/{$task->id}/comments?per_page=0")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/comments/{$commentId}", ['body' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/comments/{$commentId}", ['body' => 'Edited note'])
        ->assertOk()
        ->assertJsonPath('data.comment.body', 'Edited note');

    $this->assertDatabaseHas('comments', ['id' => $commentId, 'body' => 'Edited note']);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/comments/{$commentId}")
        ->assertNoContent();

    $this->assertSoftDeleted('comments', ['id' => $commentId]);
});

it('allows project members to comment but restricts comment edits to authors and managers', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $member = User::factory()->create();
    $workspace = taskTestWorkspace($owner);
    addTaskTestWorkspaceMember($workspace, $author);
    addTaskTestWorkspaceMember($workspace, $member);
    $project = taskTestProject($workspace, $owner, [$author, $member]);
    $task = taskTestRecord($project, $owner, 'Shared task');

    $response = $this->actingAs($author, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Author note'])
        ->assertCreated();

    $commentId = $response->json('data.comment.id');

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/comments/{$commentId}", ['body' => 'Not allowed'])
        ->assertForbidden();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/comments/{$commentId}")
        ->assertForbidden();

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/comments/{$commentId}", ['body' => 'Manager edit'])
        ->assertOk()
        ->assertJsonPath('data.comment.body', 'Manager edit');
});

it('returns validation errors for empty comments and hides cross-workspace task comments', function () {
    $owner = User::factory()->create();
    $foreignOwner = User::factory()->create();
    $workspace = taskTestWorkspace($owner);
    $project = taskTestProject($workspace, $owner);
    $task = taskTestRecord($project, $owner, 'Local task');
    $foreignWorkspace = taskTestWorkspace($foreignOwner);
    $foreignProject = taskTestProject($foreignWorkspace, $foreignOwner);
    $foreignTask = taskTestRecord($foreignProject, $foreignOwner, 'Foreign task');
    $foreignComment = Comment::factory()->create([
        'workspace_id' => $foreignWorkspace->id,
        'commentable_type' => Task::class,
        'commentable_id' => $foreignTask->id,
        'user_id' => $foreignOwner->id,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/tasks/{$task->id}/comments", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/tasks/{$foreignTask->id}/comments")
        ->assertNotFound();

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/tasks/{$foreignTask->id}/comments", ['body' => 'Attempt'])
        ->assertNotFound();

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/comments/{$foreignComment->id}", ['body' => 'Attempt'])
        ->assertNotFound();

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/comments/{$foreignComment->id}")
        ->assertNotFound();
});

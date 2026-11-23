<?php

use App\Enums\ActivityEvent;
use App\Enums\TaskStatus;
use App\Events\CommentAdded;
use App\Events\FileUploaded;
use App\Events\FileVersionAdded;
use App\Events\ProjectCreated;
use App\Events\ProjectMemberAdded;
use App\Events\TaskAssigned;
use App\Events\TaskCreated;
use App\Events\TaskStatusChanged;
use App\Listeners\Activities\RecordCommentActivity;
use App\Listeners\Activities\RecordFileUploadedActivity;
use App\Listeners\Activities\RecordFileVersionActivity;
use App\Listeners\Activities\RecordProjectCreatedActivity;
use App\Listeners\Activities\RecordProjectMemberAddedActivity;
use App\Listeners\Activities\RecordTaskAssignedActivity;
use App\Listeners\Activities\RecordTaskCreatedActivity;
use App\Listeners\Activities\RecordTaskStatusActivity;
use App\Models\Activity;
use App\Models\Comment;
use App\Models\File as ProjectFile;
use App\Models\FileVersion;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ActivityDescriber;

function activitiesTestWorkspace(User $owner, array $members = []): Workspace
{
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'admin']);

    foreach ($members as $member) {
        $workspace->members()->attach($member->id, ['role' => 'member']);
    }

    return $workspace;
}

/**
 * @param  list<User>  $members
 */
function activitiesTestProject(Workspace $workspace, User $creator, array $members = []): Project
{
    $project = Project::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $creator->id,
    ]);
    $project->members()->attach($creator->id);

    foreach ($members as $member) {
        $project->members()->attach($member->id);
    }

    return $project;
}

function createActivity(int $workspaceId, int $causerId, ?int $projectId = null): Activity
{
    return Activity::withoutGlobalScopes()->create([
        'workspace_id' => $workspaceId,
        'project_id' => $projectId,
        'causer_id' => $causerId,
        'subject_type' => Task::class,
        'subject_id' => 1,
        'event' => ActivityEvent::TaskCreated,
        'properties' => [
            'actor_name' => User::query()->findOrFail($causerId)->name,
            'task_title' => 'Login Screen',
        ],
        'created_at' => now(),
    ]);
}

it('records each domain event as a workspace activity', function () {
    $actor = User::factory()->create(['name' => 'Ali']);
    $assignee = User::factory()->create(['name' => 'Sam']);
    $member = User::factory()->create(['name' => 'Riley']);
    $workspace = activitiesTestWorkspace($actor, [$assignee, $member]);
    $project = activitiesTestProject($workspace, $actor, [$assignee, $member]);
    $task = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'created_by' => $actor->id,
    ]);
    $comment = Comment::factory()->create([
        'workspace_id' => $workspace->id,
        'commentable_type' => Task::class,
        'commentable_id' => $task->id,
        'user_id' => $actor->id,
    ]);
    $file = ProjectFile::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'task_id' => $task->id,
        'uploaded_by' => $actor->id,
    ]);
    $version = FileVersion::factory()->create([
        'file_id' => $file->id,
        'uploaded_by' => $actor->id,
        'version_number' => 2,
    ]);

    app(RecordTaskCreatedActivity::class)->handle(new TaskCreated($task));
    app(RecordTaskAssignedActivity::class)->handle(new TaskAssigned($task, $assignee, $actor->id));
    app(RecordTaskStatusActivity::class)->handle(
        new TaskStatusChanged($task, TaskStatus::Todo, TaskStatus::InReview, $actor->id),
    );
    app(RecordCommentActivity::class)->handle(new CommentAdded($task, $comment));
    app(RecordFileUploadedActivity::class)->handle(new FileUploaded($file, $version, $actor->id, $task->title));
    app(RecordFileVersionActivity::class)->handle(new FileVersionAdded($file, $version, $actor->id));
    app(RecordProjectMemberAddedActivity::class)->handle(new ProjectMemberAdded($project, $member, $actor->id));
    app(RecordProjectCreatedActivity::class)->handle(new ProjectCreated($project, $actor->id));

    $activities = Activity::withoutGlobalScopes()->orderBy('id')->get();

    expect($activities)->toHaveCount(8);
    expect($activities->pluck('event')->map(fn (ActivityEvent $event): string => $event->value)->all())
        ->toBe([
            'task_created',
            'task_assigned',
            'task_status_changed',
            'comment_added',
            'file_uploaded',
            'file_version_added',
            'project_member_added',
            'project_created',
        ]);
    expect($activities->first()->properties['actor_name'])->toBe('Ali');
});

it('limits profile activity to the causer and project activity to project members and managers', function () {
    $owner = User::factory()->create(['name' => 'Ali']);
    $member = User::factory()->create();
    $workspace = activitiesTestWorkspace($owner, [$member]);
    $project = activitiesTestProject($workspace, $owner, [$member]);
    $otherWorkspaceUser = User::factory()->create();
    $otherWorkspace = activitiesTestWorkspace($otherWorkspaceUser);
    $otherProject = activitiesTestProject($otherWorkspace, $otherWorkspaceUser);
    $ownActivity = createActivity($workspace->id, $owner->id, $project->id);
    createActivity($workspace->id, $member->id, $project->id);
    createActivity($otherWorkspace->id, $otherWorkspaceUser->id, $otherProject->id);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/profile/activities')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.activities.0.id', $ownActivity->id)
        ->assertJsonPath('data.activities.0.message', "Ali created 'Login Screen'");

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/projects/{$project->id}/activities")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2);

    $nonProjectMember = User::factory()->create();
    $workspace->members()->attach($nonProjectMember->id, ['role' => 'member']);
    $this->actingAs($nonProjectMember, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/projects/{$project->id}/activities")
        ->assertForbidden();

    $this->actingAs($otherWorkspaceUser, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $otherWorkspace->id)
        ->getJson("/api/v1/projects/{$project->id}/activities")
        ->assertNotFound();
});

it('describes task status changes with a human-readable status label', function () {
    expect(ActivityDescriber::describe(ActivityEvent::TaskStatusChanged, [
        'actor_name' => 'Ali',
        'task_title' => 'Login Screen',
        'status' => TaskStatus::InReview->value,
    ]))->toBe("Ali moved 'Login Screen' to In Review");
});

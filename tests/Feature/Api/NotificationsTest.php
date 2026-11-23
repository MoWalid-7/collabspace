<?php

use App\Data\Notifications\NotificationData;
use App\Enums\NotificationType;
use App\Enums\TaskStatus;
use App\Events\CommentAdded;
use App\Events\FileUploaded;
use App\Events\ProjectMemberAdded;
use App\Events\TaskAssigned;
use App\Events\TaskStatusChanged;
use App\Listeners\Notifications\NotifyCommentAdded;
use App\Listeners\Notifications\NotifyFileUploaded;
use App\Listeners\Notifications\NotifyProjectMemberAdded;
use App\Listeners\Notifications\NotifyTaskAssigned;
use App\Listeners\Notifications\NotifyTaskStatusChanged;
use App\Models\Comment;
use App\Models\File as ProjectFile;
use App\Models\FileVersion;
use App\Models\NotificationPreference;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\DomainNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;

function notificationsTestWorkspace(User $owner, array $members = []): Workspace
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
function notificationsTestProject(Workspace $workspace, User $creator, array $members = []): Project
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

function createDomainNotification(User $user, NotificationType $type, string $actorName = 'Actor'): DatabaseNotification
{
    $existingIds = DatabaseNotification::query()
        ->where('notifiable_id', $user->id)
        ->pluck('id');

    $user->notify(new DomainNotification(new NotificationData(
        type: $type,
        title: 'Test title',
        body: 'Test body',
        projectId: null,
        taskId: null,
        fileId: null,
        actor: ['id' => 999, 'name' => $actorName],
    )));

    return DatabaseNotification::query()
        ->where('notifiable_id', $user->id)
        ->whereNotIn('id', $existingIds)
        ->firstOrFail();
}

it('notifies only the assignee and excludes the actor', function () {
    $actor = User::factory()->create(['name' => 'Ali']);
    $assignee = User::factory()->create();
    $workspace = notificationsTestWorkspace($actor, [$assignee]);
    $project = notificationsTestProject($workspace, $actor, [$assignee]);
    $task = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'created_by' => $actor->id,
    ]);
    $task->assignees()->attach($assignee->id);

    app(NotifyTaskAssigned::class)->handle(new TaskAssigned($task, $assignee, $actor->id));

    $notification = DatabaseNotification::query()
        ->where('notifiable_id', $assignee->id)
        ->firstOrFail();

    expect($notification->data)
        ->toMatchArray([
            'type' => NotificationType::TaskAssigned->value,
            'project_id' => $project->id,
            'task_id' => $task->id,
            'actor' => ['id' => $actor->id, 'name' => 'Ali'],
        ]);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $actor->id]);
});

it('notifies the reviewer in review and the creator on completion', function () {
    $actor = User::factory()->create();
    $creator = User::factory()->create();
    $reviewer = User::factory()->create();
    $workspace = notificationsTestWorkspace($actor, [$creator, $reviewer]);
    $project = notificationsTestProject($workspace, $creator, [$actor, $reviewer]);
    $task = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'created_by' => $creator->id,
        'reviewer_id' => $reviewer->id,
    ]);

    $listener = app(NotifyTaskStatusChanged::class);
    $listener->handle(new TaskStatusChanged($task, TaskStatus::InProgress, TaskStatus::InReview, $actor->id));
    $listener->handle(new TaskStatusChanged($task, TaskStatus::InReview, TaskStatus::Completed, $reviewer->id));

    expect(DatabaseNotification::query()
        ->where('notifiable_id', $reviewer->id)
        ->firstOrFail()
        ->data['type'])->toBe(NotificationType::TaskInReview->value);
    expect(DatabaseNotification::query()
        ->where('notifiable_id', $creator->id)
        ->firstOrFail()
        ->data['type'])->toBe(NotificationType::TaskCompleted->value);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $actor->id]);
});

it('notifies task assignees and creator once when a comment is added', function () {
    $actor = User::factory()->create();
    $creator = User::factory()->create();
    $assignee = User::factory()->create();
    $workspace = notificationsTestWorkspace($actor, [$creator, $assignee]);
    $project = notificationsTestProject($workspace, $creator, [$actor, $assignee]);
    $task = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'created_by' => $creator->id,
    ]);
    $task->assignees()->sync([$actor->id, $creator->id, $assignee->id]);
    $comment = Comment::factory()->create([
        'workspace_id' => $workspace->id,
        'commentable_type' => Task::class,
        'commentable_id' => $task->id,
        'user_id' => $actor->id,
    ]);

    app(NotifyCommentAdded::class)->handle(new CommentAdded($task, $comment));

    $this->assertDatabaseCount('notifications', 2);
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $creator->id,
        'type' => DomainNotification::class,
    ]);
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $assignee->id,
        'type' => DomainNotification::class,
    ]);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $actor->id]);
});

it('notifies task assignees for linked uploads but not for project-only uploads', function () {
    $actor = User::factory()->create();
    $assignee = User::factory()->create();
    $workspace = notificationsTestWorkspace($actor, [$assignee]);
    $project = notificationsTestProject($workspace, $actor, [$assignee]);
    $task = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'created_by' => $actor->id,
    ]);
    $task->assignees()->attach($assignee->id);
    $file = ProjectFile::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'task_id' => $task->id,
        'uploaded_by' => $actor->id,
    ]);
    $version = FileVersion::factory()->create([
        'file_id' => $file->id,
        'uploaded_by' => $actor->id,
    ]);

    app(NotifyFileUploaded::class)->handle(new FileUploaded($file, $version, $actor->id, $task->title));
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $assignee->id]);

    $projectOnlyFile = ProjectFile::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'task_id' => null,
        'uploaded_by' => $actor->id,
    ]);
    $projectOnlyVersion = FileVersion::factory()->create([
        'file_id' => $projectOnlyFile->id,
        'uploaded_by' => $actor->id,
    ]);

    app(NotifyFileUploaded::class)->handle(new FileUploaded($projectOnlyFile, $projectOnlyVersion, $actor->id));

    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $actor->id]);
});

it('notifies an added project member and emits the domain event from the project service', function () {
    $actor = User::factory()->create();
    $member = User::factory()->create();
    $workspace = notificationsTestWorkspace($actor, [$member]);
    $project = notificationsTestProject($workspace, $actor);

    app(NotifyProjectMemberAdded::class)->handle(new ProjectMemberAdded($project, $member, $actor->id));

    $this->assertDatabaseHas('notifications', ['notifiable_id' => $member->id]);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $actor->id]);

    Event::fake([ProjectMemberAdded::class]);
    $this->actingAs($actor, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/members", ['user_id' => $member->id])
        ->assertCreated();

    Event::assertDispatched(
        ProjectMemberAdded::class,
        fn (ProjectMemberAdded $event): bool => $event->actorId === $actor->id
            && $event->member->is($member),
    );
});

it('honors disabled preferences and supports unread, read-all, and owner-only notification endpoints', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $assignee = User::factory()->create();
    NotificationPreference::factory()->create([
        'user_id' => $assignee->id,
        'type' => NotificationType::TaskAssigned,
        'enabled' => false,
    ]);
    $workspace = notificationsTestWorkspace($user);
    $project = notificationsTestProject($workspace, $user);
    $workspace->members()->attach($assignee->id, ['role' => 'member']);
    $project->members()->attach($assignee->id);
    $task = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'created_by' => $user->id,
    ]);

    app(NotifyTaskAssigned::class)->handle(new TaskAssigned($task, $assignee, $user->id));
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $assignee->id]);

    $unread = createDomainNotification($user, NotificationType::CommentAdded);
    $read = createDomainNotification($user, NotificationType::TaskCompleted);
    $read->markAsRead();
    $foreign = createDomainNotification($other, NotificationType::FileUploaded);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/notifications?unread=1')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.notifications.0.id', $unread->id);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 1);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/notifications/{$foreign->id}/read")
        ->assertNotFound();

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/notifications/{$unread->id}/read")
        ->assertOk();
    $this->assertDatabaseHas('notifications', [
        'id' => $unread->id,
    ]);

    $anotherUnread = createDomainNotification($user, NotificationType::TaskAssigned);
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/notifications/read-all')
        ->assertOk();
    expect(DatabaseNotification::query()
        ->where('notifiable_id', $user->id)
        ->whereNull('read_at')
        ->count())->toBe(0);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/notifications/{$anotherUnread->id}")
        ->assertNoContent();
    $this->assertDatabaseMissing('notifications', ['id' => $anotherUnread->id]);
});

it('returns and updates all notification preferences while rejecting unknown types', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/settings/notifications')
        ->assertOk()
        ->assertJsonCount(count(NotificationType::cases()), 'data.preferences')
        ->assertJsonPath('data.preferences.0.enabled', true);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/settings/notifications', [
            NotificationType::TaskAssigned->value => false,
            NotificationType::FileUploaded->value => true,
        ])
        ->assertOk()
        ->assertJsonFragment(['type' => NotificationType::TaskAssigned->value, 'enabled' => false]);

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->id,
        'type' => NotificationType::TaskAssigned->value,
        'enabled' => false,
    ]);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/settings/notifications', ['unknown_type' => false])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unknown_type']);
});

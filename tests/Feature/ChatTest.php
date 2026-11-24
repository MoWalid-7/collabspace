<?php

use App\Enums\ChannelType;
use App\Enums\WorkspaceRole;
use App\Events\MessageSent;
use App\Events\ProjectMemberAdded;
use App\Listeners\Chat\AddProjectMemberToChannel;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;

function chatWorkspace(User $owner, array $members = []): Workspace
{
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Admin->value]);

    foreach ($members as $member) {
        $workspace->members()->attach($member['user']->id, ['role' => $member['role']->value]);
    }

    return $workspace;
}

it('enforces channel access rules, allows joining public channels, and isolates tenants', function () {
    $admin = User::factory()->create(['name' => 'Admin']);
    $member = User::factory()->create(['name' => 'Member']);
    $outsider = User::factory()->create(['name' => 'Outsider']);

    $workspace = chatWorkspace($admin, [
        ['user' => $member, 'role' => WorkspaceRole::Member],
    ]);
    $otherWorkspace = chatWorkspace($outsider);

    // Admin creates public channel
    $createResp = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/channels', [
            'name' => 'general',
            'type' => 'public',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'general')
        ->assertJsonPath('data.type', 'public');

    $channelId = $createResp->json('data.id');

    // Non-member cannot read or post before joining
    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/channels/{$channelId}")
        ->assertForbidden();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/channels/{$channelId}/messages", ['body' => 'Hi'])
        ->assertForbidden();

    // Member joins public channel
    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/channels/{$channelId}/join")
        ->assertOk();

    // Now member can read and post
    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/channels/{$channelId}")
        ->assertOk();

    // Cross-tenant access -> 404
    $this->actingAs($outsider, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $otherWorkspace->id)
        ->getJson("/api/v1/channels/{$channelId}")
        ->assertNotFound();
});

it('deduplicates direct channels via direct_key and prevents joining non-public channels', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();

    $workspace = chatWorkspace($userA, [
        ['user' => $userB, 'role' => WorkspaceRole::Member],
        ['user' => $userC, 'role' => WorkspaceRole::Member],
    ]);

    // Cannot DM self
    $this->actingAs($userA, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/channels/direct', ['user_id' => $userA->id])
        ->assertUnprocessable();

    // User A creates DM with User B
    $first = $this->actingAs($userA, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/channels/direct', ['user_id' => $userB->id])
        ->assertOk();

    // User B requests DM with User A -> returns exact same channel
    $second = $this->actingAs($userB, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/channels/direct', ['user_id' => $userA->id])
        ->assertOk();

    expect($first->json('data.id'))->toBe($second->json('data.id'));

    $dmId = $first->json('data.id');

    // User C cannot join DM channel
    $this->actingAs($userC, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/channels/{$dmId}/join")
        ->assertForbidden();
});

it('creates project and team channels on demand and adds new project members via listener', function () {
    $admin = User::factory()->create();
    $member1 = User::factory()->create();
    $member2 = User::factory()->create();

    $workspace = chatWorkspace($admin, [
        ['user' => $member1, 'role' => WorkspaceRole::Member],
        ['user' => $member2, 'role' => WorkspaceRole::Member],
    ]);

    $project = Project::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $admin->id,
    ]);
    $project->members()->attach([$admin->id, $member1->id]);

    $team = Team::factory()->create([
        'workspace_id' => $workspace->id,
    ]);
    $team->members()->attach([$admin->id, $member1->id]);

    // Get-or-create project channel
    $projResp = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/channel")
        ->assertOk()
        ->assertJsonPath('data.type', 'project')
        ->assertJsonCount(2, 'data.members');

    $projectChannelId = $projResp->json('data.id');

    // Calling again returns the same channel
    $this->actingAs($member1, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/channel")
        ->assertOk()
        ->assertJsonPath('data.id', $projectChannelId);

    // Get-or-create team channel
    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/teams/{$team->id}/channel")
        ->assertOk()
        ->assertJsonPath('data.type', 'team')
        ->assertJsonCount(2, 'data.members');

    // Fire ProjectMemberAdded listener explicitly for member2
    $project->members()->attach($member2->id);
    $listener = app(AddProjectMemberToChannel::class);
    $listener->handle(new ProjectMemberAdded($project, $member2, $admin->id));

    $channel = Channel::query()->findOrFail($projectChannelId);
    expect($channel->members()->where('users.id', $member2->id)->exists())->toBeTrue();
});

it('paginates top-level messages with cursor newest first and validates threads', function () {
    $admin = User::factory()->create();
    $workspace = chatWorkspace($admin);
    $channel = Channel::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => ChannelType::Public,
        'created_by' => $admin->id,
    ]);
    $channel->members()->attach($admin->id);

    $otherChannel = Channel::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => ChannelType::Public,
        'created_by' => $admin->id,
    ]);
    $otherChannel->members()->attach($admin->id);
    $otherMsg = Message::factory()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $otherChannel->id,
        'user_id' => $admin->id,
        'body' => 'Other channel msg',
    ]);

    $m1 = Message::factory()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'user_id' => $admin->id,
        'body' => 'First',
    ]);
    $m2 = Message::factory()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'user_id' => $admin->id,
        'body' => 'Second',
    ]);

    // Reply to m1
    $replyResp = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/channels/{$channel->id}/messages", [
            'body' => 'Reply to first',
            'parent_id' => $m1->id,
        ])
        ->assertCreated();

    $replyId = $replyResp->json('data.id');

    // Cannot reply to a reply (nested reply)
    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/channels/{$channel->id}/messages", [
            'body' => 'Nested reply',
            'parent_id' => $replyId,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);

    // Cannot reply to message from another channel
    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/channels/{$channel->id}/messages", [
            'body' => 'Cross channel reply',
            'parent_id' => $otherMsg->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);

    // List top-level messages: newest first (m2 then m1), reply excluded from top-level
    $listResp = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/channels/{$channel->id}/messages")
        ->assertOk()
        ->assertJsonCount(2, 'data.messages')
        ->assertJsonPath('data.messages.0.id', $m2->id)
        ->assertJsonPath('data.messages.1.id', $m1->id)
        ->assertJsonPath('data.messages.1.replies_count', 1);

    expect($listResp->json('data.messages.1.last_reply_at'))->not->toBeNull();

    // Paginate replies
    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/messages/{$m1->id}/replies")
        ->assertOk()
        ->assertJsonCount(1, 'data.replies')
        ->assertJsonPath('data.replies.0.id', $replyId);
});

it('tracks unread counts per channel and marks channel as read', function () {
    Carbon::setTestNow('2026-11-24 10:00:00');

    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace = chatWorkspace($admin, [
        ['user' => $member, 'role' => WorkspaceRole::Member],
    ]);

    $channel = Channel::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => ChannelType::Public,
        'created_by' => $admin->id,
    ]);
    $channel->members()->attach([$admin->id, $member->id]);

    // Admin sends 2 messages
    Message::factory()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'user_id' => $admin->id,
        'body' => 'Msg 1',
    ]);
    Message::factory()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'user_id' => $admin->id,
        'body' => 'Msg 2',
    ]);

    // Member checks channels -> unread_count = 2
    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/channels')
        ->assertOk()
        ->assertJsonPath('data.0.unread_count', 2)
        ->assertJsonPath('data.0.last_message.body', 'Msg 2');

    // Member marks as read
    Carbon::setTestNow('2026-11-24 10:05:00');
    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/channels/{$channel->id}/read")
        ->assertOk();

    // Member checks channels -> unread_count = 0
    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/channels')
        ->assertOk()
        ->assertJsonPath('data.0.unread_count', 0);

    // Admin sends 1 more message after read
    Carbon::setTestNow('2026-11-24 10:10:00');
    Message::factory()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'user_id' => $admin->id,
        'body' => 'Msg 3',
    ]);

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/channels')
        ->assertOk()
        ->assertJsonPath('data.0.unread_count', 1);
});

it('enforces edit and delete permissions on messages', function () {
    $admin = User::factory()->create();
    $member1 = User::factory()->create();
    $member2 = User::factory()->create();

    $workspace = chatWorkspace($admin, [
        ['user' => $member1, 'role' => WorkspaceRole::Member],
        ['user' => $member2, 'role' => WorkspaceRole::Member],
    ]);

    $channel = Channel::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => ChannelType::Public,
        'created_by' => $admin->id,
    ]);
    $channel->members()->attach([$admin->id, $member1->id, $member2->id]);

    $msg = Message::factory()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'user_id' => $member1->id,
        'body' => 'Original text',
    ]);

    // Member 2 cannot edit or delete Member 1's message
    $this->actingAs($member2, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/messages/{$msg->id}", ['body' => 'Edited'])
        ->assertForbidden();

    $this->actingAs($member2, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/messages/{$msg->id}")
        ->assertForbidden();

    // Admin cannot edit Member 1's message (author only)
    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/messages/{$msg->id}", ['body' => 'Admin edited'])
        ->assertForbidden();

    // Author can edit -> sets edited_at
    $editResp = $this->actingAs($member1, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/messages/{$msg->id}", ['body' => 'Author edited'])
        ->assertOk()
        ->assertJsonPath('data.body', 'Author edited');

    expect($editResp->json('data.edited_at'))->not->toBeNull();

    // Admin can soft delete Member 1's message
    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/messages/{$msg->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('messages', ['id' => $msg->id]);
});

it('searches channel messages and integrates messages into global search', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace = chatWorkspace($admin, [
        ['user' => $member, 'role' => WorkspaceRole::Member],
    ]);

    $memberChannel = Channel::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => ChannelType::Public,
        'name' => 'Visible Chat',
        'created_by' => $admin->id,
    ]);
    $memberChannel->members()->attach([$admin->id, $member->id]);

    $hiddenChannel = Channel::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => ChannelType::Public,
        'name' => 'Hidden Chat',
        'created_by' => $admin->id,
    ]);
    $hiddenChannel->members()->attach($admin->id);

    Message::factory()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $memberChannel->id,
        'user_id' => $admin->id,
        'body' => 'Meeting 100% confirmed',
    ]);
    Message::factory()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $memberChannel->id,
        'user_id' => $admin->id,
        'body' => 'Regular update',
    ]);
    Message::factory()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $hiddenChannel->id,
        'user_id' => $admin->id,
        'body' => 'Secret 100% update',
    ]);

    // Channel search min 2 chars & LIKE wildcard escaping
    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/channels/{$memberChannel->id}/search?q=a")
        ->assertUnprocessable();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/channels/{$memberChannel->id}/search?q=100%25")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'Meeting 100% confirmed');

    // Global search includes messages group only from channels member belongs to
    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q=100%25&types%5B%5D=messages')
        ->assertOk()
        ->assertJsonCount(1, 'data.results.messages')
        ->assertJsonPath('data.results.messages.0.type', 'message')
        ->assertJsonPath('data.results.messages.0.channel_id', $memberChannel->id);
});

it('dispatches MessageSent broadcast event with the private channel name', function () {
    Event::fake([MessageSent::class]);

    $admin = User::factory()->create();
    $workspace = chatWorkspace($admin);
    $channel = Channel::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => ChannelType::Public,
        'created_by' => $admin->id,
    ]);
    $channel->members()->attach($admin->id);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/channels/{$channel->id}/messages", [
            'body' => 'Broadcast test message',
        ])
        ->assertCreated();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($channel): bool {
        $channels = $event->broadcastOn();

        return $event->message->body === 'Broadcast test message'
            && count($channels) === 1
            && $channels[0] instanceof PrivateChannel
            && $channels[0]->name === "private-channel.{$channel->id}";
    });
});

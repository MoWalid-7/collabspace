<?php

use App\Models\DailyStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->admin = User::factory()->create();
    $this->workspace->members()->attach($this->admin->id, ['role' => 'admin']);

    $this->member1 = User::factory()->create();
    $this->workspace->members()->attach($this->member1->id, ['role' => 'member']);

    $this->member2 = User::factory()->create();
    $this->workspace->members()->attach($this->member2->id, ['role' => 'member']);

    $this->project = Project::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $this->project->members()->attach($this->member1->id);
    $this->project->members()->attach($this->member2->id);
});

it('allows user to upsert daily status', function () {
    $today = CarbonImmutable::today()->toDateString();

    $response = $this->actingAs($this->member1, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->postJson('/api/v1/daily-statuses', [
            'project_id' => $this->project->id,
            'date' => $today,
            'done_yesterday' => 'Completed task A',
            'plan_today' => 'Working on task B',
            'blockers' => 'Waiting for design review',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.done_yesterday', 'Completed task A')
        ->assertJsonPath('data.plan_today', 'Working on task B')
        ->assertJsonPath('data.blockers', 'Waiting for design review');

    $this->assertDatabaseHas('daily_statuses', [
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->member1->id,
        'date' => $today,
        'done_yesterday' => 'Completed task A',
    ]);

    // Upsert update
    $updateResponse = $this->actingAs($this->member1, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->postJson('/api/v1/daily-statuses', [
            'project_id' => $this->project->id,
            'date' => $today,
            'done_yesterday' => 'Completed task A and A2',
            'plan_today' => 'Working on task B',
            'blockers' => null,
        ]);

    $updateResponse->assertOk()
        ->assertJsonPath('data.done_yesterday', 'Completed task A and A2')
        ->assertJsonPath('data.blockers', null);

    expect(DailyStatus::where('user_id', $this->member1->id)->count())->toBe(1);
});

it('forces members to only see their own daily statuses', function () {
    $today = CarbonImmutable::today()->toDateString();

    DailyStatus::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->member1->id,
        'project_id' => $this->project->id,
        'date' => $today,
        'done_yesterday' => 'Work 1',
        'plan_today' => 'Plan 1',
    ]);

    DailyStatus::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->member2->id,
        'project_id' => $this->project->id,
        'date' => $today,
        'done_yesterday' => 'Work 2',
        'plan_today' => 'Plan 2',
    ]);

    // Member 1 requests index
    $res1 = $this->actingAs($this->member1, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->getJson('/api/v1/daily-statuses');

    $res1->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $this->member1->id);

    // Admin requests index
    $resAdmin = $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->getJson('/api/v1/daily-statuses');

    $resAdmin->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns team status for project with submitted flag', function () {
    $today = CarbonImmutable::today()->toDateString();

    DailyStatus::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->member1->id,
        'project_id' => $this->project->id,
        'date' => $today,
        'done_yesterday' => 'Done',
        'plan_today' => 'Plan',
    ]);

    $response = $this->actingAs($this->member1, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->getJson("/api/v1/projects/{$this->project->id}/team-status?date={$today}");

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    $items = collect($response->json('data'));
    $m1 = $items->firstWhere('user.id', $this->member1->id);
    $m2 = $items->firstWhere('user.id', $this->member2->id);

    expect($m1['submitted'])->toBeTrue()
        ->and($m1['status'])->not->toBeNull()
        ->and($m2['submitted'])->toBeFalse()
        ->and($m2['status'])->toBeNull();
});

it('returns non-empty blockers for project', function () {
    $today = CarbonImmutable::today();

    DailyStatus::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->member1->id,
        'project_id' => $this->project->id,
        'date' => $today->toDateString(),
        'done_yesterday' => 'Done',
        'plan_today' => 'Plan',
        'blockers' => 'Critical blocker on API',
    ]);

    DailyStatus::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->member2->id,
        'project_id' => $this->project->id,
        'date' => $today->toDateString(),
        'done_yesterday' => 'Done',
        'plan_today' => 'Plan',
        'blockers' => null,
    ]);

    $response = $this->actingAs($this->member1, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->getJson("/api/v1/projects/{$this->project->id}/blockers?from={$today->subDays(2)->toDateString()}&to={$today->toDateString()}");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.blockers', 'Critical blocker on API');
});

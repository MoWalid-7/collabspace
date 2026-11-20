<?php

use App\Data\Auth\LoginData;
use App\Data\Auth\RegisterUserData;
use App\Data\IssuedApiTokenData;
use App\Enums\AvailabilityStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\GoogleIdentityRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\AuthService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Hash;
use Mockery;

it('registers a user, creates an admin workspace, and issues a token atomically', function () {
    $this->freezeTime();

    $user = User::factory()->make();
    $user->id = 12;
    $workspace = new Workspace;
    $workspace->id = 24;
    $token = new IssuedApiTokenData('plain-token', CarbonImmutable::now()->addDay());

    $users = Mockery::mock(UserRepositoryInterface::class);
    $workspaces = Mockery::mock(WorkspaceRepositoryInterface::class);
    $googleIdentities = Mockery::mock(GoogleIdentityRepositoryInterface::class);

    $users->shouldReceive('createUser')
        ->once()
        ->with([
            'name' => 'Alex Morgan',
            'email' => 'alex@example.com',
            'password' => 'strong-password',
            'availability_status' => AvailabilityStatus::Available,
        ])
        ->andReturn($user);
    $workspaces->shouldReceive('slugExists')->once()->with('alex-morgans-workspace')->andReturnFalse();
    $workspaces->shouldReceive('createWorkspace')
        ->once()
        ->with([
            'name' => "Alex Morgan's Workspace",
            'slug' => 'alex-morgans-workspace',
            'owner_id' => 12,
        ])
        ->andReturn($workspace);
    $workspaces->shouldReceive('addMember')
        ->once()
        ->with($workspace, $user, WorkspaceRole::Admin);
    $users->shouldReceive('createApiToken')
        ->once()
        ->with($user, Mockery::on(
            fn (DateTimeInterface $expiresAt): bool => $expiresAt == CarbonImmutable::now()->addDay(),
        ))
        ->andReturn($token);

    $result = (new AuthService($users, $workspaces, $googleIdentities))->register(
        new RegisterUserData('Alex Morgan', 'alex@example.com', 'strong-password'),
    );

    expect($result->user)->toBe($user)
        ->and($result->token)->toBe($token);
});

it('issues a thirty-day token when login requests remember me', function () {
    $this->freezeTime();

    $user = User::factory()->make();
    $user->password = Hash::make('strong-password');
    $token = new IssuedApiTokenData('long-token', CarbonImmutable::now()->addDays(30));
    $users = Mockery::mock(UserRepositoryInterface::class);
    $workspaces = Mockery::mock(WorkspaceRepositoryInterface::class);
    $googleIdentities = Mockery::mock(GoogleIdentityRepositoryInterface::class);

    $users->shouldReceive('findByEmail')
        ->once()
        ->with('alex@example.com')
        ->andReturn($user);
    $users->shouldReceive('createApiToken')
        ->once()
        ->with($user, Mockery::on(
            fn (DateTimeInterface $expiresAt): bool => $expiresAt == CarbonImmutable::now()->addDays(30),
        ))
        ->andReturn($token);

    $result = (new AuthService($users, $workspaces, $googleIdentities))->login(
        new LoginData('alex@example.com', 'strong-password', true),
    );

    expect($result->token)->toBe($token);
});

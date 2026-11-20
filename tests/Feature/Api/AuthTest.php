<?php

use App\Contracts\GoogleIdentityProvider;
use App\Data\GoogleIdentityData;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('registers a user with a default admin workspace and API token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Taylor Reed',
        'email' => 'taylor@example.com',
        'password' => 'strong-password',
        'password_confirmation' => 'strong-password',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.user.full_name', 'Taylor Reed')
        ->assertJsonPath('data.user.email', 'taylor@example.com')
        ->assertJsonPath('data.token_type', 'Bearer');

    $user = User::query()->where('email', 'taylor@example.com')->firstOrFail();
    $workspace = Workspace::query()->where('owner_id', $user->id)->firstOrFail();

    expect(Hash::check('strong-password', $user->password))->toBeTrue();
    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Admin->value,
    ]);
    $this->assertDatabaseCount('personal_access_tokens', 1);
});

it('rejects invalid registration data without creating records', function () {
    $this->postJson('/api/v1/auth/register', [
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name', 'email', 'password']);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('workspaces', 0);
});

it('logs in with a requested long-lived token', function () {
    $this->freezeTime();
    $user = User::factory()->create([
        'email' => 'taylor@example.com',
        'password' => 'strong-password',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'strong-password',
        'remember_me' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.expires_at', now()->addDays(30)->toISOString());
});

it('issues a shorter-lived token when remember me is not requested', function () {
    $this->freezeTime();
    $user = User::factory()->create(['password' => 'strong-password']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'strong-password',
    ])
        ->assertOk()
        ->assertJsonPath('data.expires_at', now()->addDay()->toISOString());
});

it('rejects incorrect login credentials', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'incorrect',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('revokes the current API token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
});

it('returns the authenticated user from the me endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.email', $user->email);
});

it('sends password reset notifications without disclosing whether the email exists', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
        ->assertOk()
        ->assertJsonPath('success', true);

    Notification::assertSentTo(
        $user,
        ResetPassword::class,
        fn (ResetPassword $notification): bool => $notification->toMail($user) instanceof MailMessage,
    );
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.com'])
        ->assertOk()
        ->assertJsonPath('message', 'If an account exists for this email, a password reset link has been sent.');

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('resets the password and revokes existing API tokens', function () {
    $user = User::factory()->create();
    $user->createToken('old-token');
    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Hash::check('new-strong-password', $user->fresh()->password))->toBeTrue();
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('returns a validation error for an invalid password reset token', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('authenticates a Google identity through the provider contract', function () {
    $this->mock(GoogleIdentityProvider::class)
        ->shouldReceive('userFromAccessToken')
        ->once()
        ->with('valid-google-token')
        ->andReturn(new GoogleIdentityData(
            id: 'google-user-1',
            email: 'google@example.com',
            name: 'Google User',
        ));

    $this->postJson('/api/v1/auth/google', ['access_token' => 'valid-google-token'])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'google@example.com')
        ->assertJsonPath('data.user.full_name', 'Google User');

    $user = User::query()->where('google_id', 'google-user-1')->firstOrFail();
    $this->assertDatabaseHas('workspaces', ['owner_id' => $user->id]);
});

it('does not silently link a Google identity to an existing password account', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->mock(GoogleIdentityProvider::class)
        ->shouldReceive('userFromAccessToken')
        ->once()
        ->andReturn(new GoogleIdentityData(
            id: 'new-google-user',
            email: 'existing@example.com',
            name: 'Existing User',
        ));

    $this->postJson('/api/v1/auth/google', ['access_token' => 'valid-google-token'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('validates the Google access token request', function () {
    $this->postJson('/api/v1/auth/google', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['access_token']);
});

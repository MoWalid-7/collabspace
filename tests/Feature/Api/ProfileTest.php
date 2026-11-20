<?php

use App\Enums\AvailabilityStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

it('returns the authenticated user profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.full_name', $user->name);
});

it('updates profile fields and validates profile data', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', [
            'full_name' => 'Jordan Lane',
            'phone' => '+1-555-0101',
            'job_title' => 'Designer',
            'years_of_experience' => 6,
            'about' => 'Product designer',
            'availability_status' => AvailabilityStatus::OpenToOffers->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.user.full_name', 'Jordan Lane')
        ->assertJsonPath('data.user.availability_status', AvailabilityStatus::OpenToOffers->value);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Jordan Lane',
        'years_of_experience' => 6,
    ]);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', [
            'years_of_experience' => -1,
            'availability_status' => 'unknown',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['years_of_experience', 'availability_status']);
});

it('requires the current password to change the profile password', function () {
    $user = User::factory()->create(['password' => 'current-password']);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'incorrect-password',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'current-password',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])
        ->assertOk();

    expect(Hash::check('new-strong-password', $user->fresh()->password))->toBeTrue();
});

it('stores a valid avatar upload and rejects a non-image file', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ]);

    $response->assertOk();
    $user->refresh();
    $response->assertJsonPath('data.user.avatar', $user->avatar);
    Storage::disk('public')->assertExists($user->avatar);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('avatar.txt', 10, 'text/plain'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['avatar']);
});

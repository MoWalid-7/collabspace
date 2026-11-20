<?php

namespace App\Repositories\Eloquent;

use App\Data\IssuedApiTokenData;
use App\Models\User;
use App\Repositories\Contracts\FileStorageRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Password;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(
        User $model,
        private FileStorageRepositoryInterface $fileStorage,
    ) {
        parent::__construct($model);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function findUserOrFail(int $userId): User
    {
        return User::query()->findOrFail($userId);
    }

    public function findByGoogleId(string $googleId): ?User
    {
        return User::query()->where('google_id', $googleId)->first();
    }

    public function emailExists(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }

    public function createUser(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    public function updateProfile(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user->refresh();
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);
    }

    public function createApiToken(User $user, DateTimeInterface $expiresAt): IssuedApiTokenData
    {
        $token = $user->createToken('api', ['*'], $expiresAt);

        return new IssuedApiTokenData(
            $token->plainTextToken,
            CarbonImmutable::instance($expiresAt),
        );
    }

    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function storeAvatar(User $user, UploadedFile $avatar): User
    {
        $oldAvatar = $user->avatar;
        $user->update(['avatar' => $this->fileStorage->storeAvatar($avatar)]);
        $this->fileStorage->delete($oldAvatar);

        return $user->refresh();
    }

    public function sendPasswordResetLink(string $email): string
    {
        return Password::sendResetLink(['email' => $email]);
    }

    public function resetPassword(array $credentials): bool
    {
        $status = Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill(['password' => $password])->save();
            $user->tokens()->delete();
        });

        return $status === Password::PasswordReset;
    }
}

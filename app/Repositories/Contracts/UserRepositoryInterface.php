<?php

namespace App\Repositories\Contracts;

use App\Data\IssuedApiTokenData;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

interface UserRepositoryInterface
{
    public function find(int|string $id): ?Model;

    public function findOrFail(int|string $id): Model;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Model;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function findByEmail(string $email): ?User;

    public function findUserOrFail(int $userId): User;

    public function findByGoogleId(string $googleId): ?User;

    public function emailExists(string $email): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createUser(array $attributes): User;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateProfile(User $user, array $attributes): User;

    public function updatePassword(User $user, string $password): void;

    public function createApiToken(User $user, DateTimeInterface $expiresAt): IssuedApiTokenData;

    public function revokeCurrentToken(User $user): void;

    public function storeAvatar(User $user, UploadedFile $avatar): User;

    public function sendPasswordResetLink(string $email): string;

    /**
     * @param  array{email: string, token: string, password: string}  $credentials
     */
    public function resetPassword(array $credentials): bool;
}

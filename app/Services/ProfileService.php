<?php

namespace App\Services;

use App\Data\Profile\UpdatePasswordData;
use App\Data\Profile\UpdateProfileData;
use App\Data\Profile\UploadAvatarData;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\ProfileServiceInterface;
use App\Services\Storage\Contracts\FileStorage;

class ProfileService implements ProfileServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
        private FileStorage $fileStorage,
    ) {}

    public function show(int $userId): User
    {
        return $this->users->findUserOrFail($userId);
    }

    public function update(int $userId, UpdateProfileData $data): User
    {
        $user = $this->users->findUserOrFail($userId);

        return $this->users->updateProfile($user, $data->userAttributes());
    }

    public function updatePassword(int $userId, UpdatePasswordData $data): void
    {
        $this->users->updatePassword(
            $this->users->findUserOrFail($userId),
            $data->password,
        );
    }

    public function uploadAvatar(int $userId, UploadAvatarData $data): User
    {
        $user = $this->users->findUserOrFail($userId);
        $oldAvatar = $user->avatar;
        $storedFile = $this->fileStorage->put($data->avatar, 'avatars');
        $updatedUser = $this->users->updateAvatar($user, $storedFile->path);

        if ($oldAvatar !== null) {
            $this->fileStorage->delete((string) config('collabspace.files.disk'), $oldAvatar);
        }

        return $updatedUser;
    }
}

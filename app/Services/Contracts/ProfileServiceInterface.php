<?php

namespace App\Services\Contracts;

use App\Data\Profile\UpdatePasswordData;
use App\Data\Profile\UpdateProfileData;
use App\Data\Profile\UploadAvatarData;
use App\Models\User;

interface ProfileServiceInterface
{
    public function show(int $userId): User;

    public function update(int $userId, UpdateProfileData $data): User;

    public function updatePassword(int $userId, UpdatePasswordData $data): void;

    public function uploadAvatar(int $userId, UploadAvatarData $data): User;
}

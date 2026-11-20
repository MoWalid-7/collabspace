<?php

namespace App\Services\Contracts;

use App\Data\Auth\ForgotPasswordData;
use App\Data\Auth\GoogleAccessTokenData;
use App\Data\Auth\LoginData;
use App\Data\Auth\RegisterUserData;
use App\Data\Auth\ResetPasswordData;
use App\Data\AuthenticatedUserData;
use App\Models\User;

interface AuthServiceInterface
{
    public function register(RegisterUserData $data): AuthenticatedUserData;

    public function login(LoginData $data): AuthenticatedUserData;

    public function google(GoogleAccessTokenData $data): AuthenticatedUserData;

    public function logout(User $user): void;

    public function currentUser(int $userId): User;

    public function forgotPassword(ForgotPasswordData $data): void;

    public function resetPassword(ResetPasswordData $data): void;
}

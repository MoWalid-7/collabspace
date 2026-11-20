<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Auth\ForgotPasswordData;
use App\Data\Auth\GoogleAccessTokenData;
use App\Data\Auth\LoginData;
use App\Data\Auth\RegisterUserData;
use App\Data\Auth\ResetPasswordData;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\GoogleAuthRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\AuthenticatedUserRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Http\Resources\UserResource;
use App\Services\Contracts\AuthServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AuthController
{
    public function register(RegisterRequest $request, AuthServiceInterface $auth): JsonResponse
    {
        $input = $request->validated();
        $result = $auth->register(new RegisterUserData(
            fullName: $input['full_name'],
            email: $input['email'],
            password: $input['password'],
        ));

        return ApiResponse::created(
            AuthenticatedUserResource::make($result),
            'Registration successful.',
        );
    }

    public function login(LoginRequest $request, AuthServiceInterface $auth): JsonResponse
    {
        $input = $request->validated();
        $result = $auth->login(new LoginData(
            email: $input['email'],
            password: $input['password'],
            rememberMe: $input['remember_me'] ?? false,
        ));

        return ApiResponse::success(
            AuthenticatedUserResource::make($result),
            'Login successful.',
        );
    }

    public function logout(AuthenticatedUserRequest $request, AuthServiceInterface $auth): Response
    {
        $auth->logout($request->user());

        return ApiResponse::noContent();
    }

    public function me(AuthenticatedUserRequest $request, AuthServiceInterface $auth): JsonResponse
    {
        return ApiResponse::success([
            'user' => UserResource::make($auth->currentUser($request->user()->getKey())),
        ]);
    }

    public function forgotPassword(
        ForgotPasswordRequest $request,
        AuthServiceInterface $auth,
    ): JsonResponse {
        $auth->forgotPassword(new ForgotPasswordData($request->validated('email')));

        return ApiResponse::success(
            null,
            'If an account exists for this email, a password reset link has been sent.',
        );
    }

    public function resetPassword(
        ResetPasswordRequest $request,
        AuthServiceInterface $auth,
    ): JsonResponse {
        $input = $request->validated();
        $auth->resetPassword(new ResetPasswordData(
            email: $input['email'],
            token: $input['token'],
            password: $input['password'],
        ));

        return ApiResponse::success(null, 'Password reset successfully.');
    }

    public function google(
        GoogleAuthRequest $request,
        AuthServiceInterface $auth,
    ): JsonResponse {
        $result = $auth->google(new GoogleAccessTokenData($request->validated('access_token')));

        return ApiResponse::success(
            AuthenticatedUserResource::make($result),
            'Google sign-in successful.',
        );
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Profile\UpdatePasswordData;
use App\Data\Profile\UpdateProfileData;
use App\Data\Profile\UploadAvatarData;
use App\Http\Requests\AuthenticatedUserRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Contracts\ProfileServiceInterface;
use App\Services\Contracts\TimeTrackingServiceInterface;
use App\Support\ApiResponse;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;

class ProfileController
{
    public function show(
        AuthenticatedUserRequest $request,
        ProfileServiceInterface $profile,
    ): JsonResponse {
        return ApiResponse::success([
            'user' => UserResource::make($profile->show($request->user()->getKey())),
        ]);
    }

    public function update(UpdateProfileRequest $request, ProfileServiceInterface $profile): JsonResponse
    {
        $user = $profile->update(
            $request->user()->getKey(),
            UpdateProfileData::from($request->validated()),
        );

        return ApiResponse::success([
            'user' => UserResource::make($user),
        ], 'Profile updated successfully.');
    }

    public function updatePassword(
        UpdatePasswordRequest $request,
        ProfileServiceInterface $profile,
    ): JsonResponse {
        $profile->updatePassword(
            $request->user()->getKey(),
            new UpdatePasswordData($request->validated('password')),
        );

        return ApiResponse::success(null, 'Password updated successfully.');
    }

    public function uploadAvatar(
        UploadAvatarRequest $request,
        ProfileServiceInterface $profile,
    ): JsonResponse {
        $user = $profile->uploadAvatar(
            $request->user()->getKey(),
            new UploadAvatarData($request->avatar()),
        );

        return ApiResponse::success([
            'user' => UserResource::make($user),
        ], 'Avatar uploaded successfully.');
    }

    public function stats(
        TaskRepositoryInterface $tasks,
        TimeTrackingServiceInterface $timeTracking,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $userId = (int) request()->user()->getKey();
        $workspaceId = $currentWorkspace->requireId();

        $completedTasks = $tasks->countCompletedTasksForUser($workspaceId, $userId);
        $hoursWorked = $timeTracking->profileHoursWorked($userId, $workspaceId);

        return ApiResponse::success([
            'completed_tasks' => $completedTasks,
            'hours_worked' => $hoursWorked,
        ]);
    }
}

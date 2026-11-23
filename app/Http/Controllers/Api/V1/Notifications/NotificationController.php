<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Requests\Notifications\ListNotificationsRequest;
use App\Http\Requests\Notifications\UpdateNotificationPreferencesRequest;
use App\Http\Resources\NotificationPreferenceResource;
use App\Http\Resources\NotificationResource;
use App\Services\Contracts\NotificationServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController
{
    public function index(ListNotificationsRequest $request, NotificationServiceInterface $notifications): JsonResponse
    {
        $attributes = $request->validated();
        $paginator = $notifications->list(
            (int) $request->user()->getKey(),
            $request->boolean('unread'),
            $request->integer('per_page', 15),
        );

        return ApiResponse::success([
            'notifications' => NotificationResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request, NotificationServiceInterface $notifications): JsonResponse
    {
        return ApiResponse::success([
            'unread_count' => $notifications->unreadCount((int) $request->user()->getKey()),
        ]);
    }

    public function markRead(
        Request $request,
        NotificationServiceInterface $notifications,
        string $notification,
    ): JsonResponse {
        $notifications->markRead($notification, (int) $request->user()->getKey());

        return ApiResponse::success(message: 'Notification marked as read.');
    }

    public function markAllRead(Request $request, NotificationServiceInterface $notifications): JsonResponse
    {
        $notifications->markAllRead((int) $request->user()->getKey());

        return ApiResponse::success(message: 'Notifications marked as read.');
    }

    public function destroy(Request $request, NotificationServiceInterface $notifications, string $notification): Response
    {
        $notifications->delete($notification, (int) $request->user()->getKey());

        return ApiResponse::noContent();
    }

    public function preferences(Request $request, NotificationServiceInterface $notifications): JsonResponse
    {
        return ApiResponse::success([
            'preferences' => NotificationPreferenceResource::collection(
                $notifications->preferences((int) $request->user()->getKey()),
            ),
        ]);
    }

    public function updatePreferences(
        UpdateNotificationPreferencesRequest $request,
        NotificationServiceInterface $notifications,
    ): JsonResponse {
        return ApiResponse::success([
            'preferences' => NotificationPreferenceResource::collection(
                $notifications->updatePreferences(
                    (int) $request->user()->getKey(),
                    $request->validated(),
                ),
            ),
        ], 'Notification preferences updated.');
    }
}

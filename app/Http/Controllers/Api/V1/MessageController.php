<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Services\Contracts\ChatServiceInterface;
use App\Support\ApiResponse;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MessageController extends Controller
{
    public function replies(
        int $id,
        Request $request,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $page = max(1, (int) $request->query('page', 1));
        $paginator = $chat->listReplies(
            $id,
            $page,
            $request->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success([
            'replies' => MessageResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function update(
        int $id,
        UpdateMessageRequest $request,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $message = $chat->updateMessage(
            $id,
            (string) $request->validated('body'),
            $request->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(new MessageResource($message), 'Message updated successfully.');
    }

    public function destroy(
        int $id,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): Response {
        $chat->deleteMessage(
            $id,
            request()->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::noContent();
    }
}

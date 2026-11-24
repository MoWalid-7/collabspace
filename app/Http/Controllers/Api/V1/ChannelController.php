<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Chat\ChannelFilters;
use App\Data\Chat\CreateChannelData;
use App\Data\Chat\SendMessageData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\CreateDirectChannelRequest;
use App\Http\Requests\Chat\CreatePublicChannelRequest;
use App\Http\Requests\Chat\ListChannelsRequest;
use App\Http\Requests\Chat\ManageChannelMembersRequest;
use App\Http\Requests\Chat\SearchChannelMessagesRequest;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Resources\ChannelResource;
use App\Http\Resources\MessageResource;
use App\Services\Contracts\ChatServiceInterface;
use App\Support\ApiResponse;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(
        ListChannelsRequest $request,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $channels = $chat->listMyChannels(
            $request->user(),
            $currentWorkspace->requireId(),
            ChannelFilters::from($request->validated()),
        );

        return ApiResponse::success(ChannelResource::collection($channels));
    }

    public function store(
        CreatePublicChannelRequest $request,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $channel = $chat->createPublicChannel(
            new CreateChannelData(
                name: (string) $request->validated('name'),
                memberIds: (array) ($request->validated('member_ids') ?? []),
            ),
            $request->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::created(new ChannelResource($channel));
    }

    public function direct(
        CreateDirectChannelRequest $request,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $channel = $chat->getOrCreateDirectChannel(
            (int) $request->validated('user_id'),
            $request->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(new ChannelResource($channel));
    }

    public function projectChannel(
        int $projectId,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $channel = $chat->getOrCreateProjectChannel(
            $projectId,
            request()->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(new ChannelResource($channel));
    }

    public function teamChannel(
        int $teamId,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $channel = $chat->getOrCreateTeamChannel(
            $teamId,
            request()->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(new ChannelResource($channel));
    }

    public function show(
        int $id,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $channel = $chat->getChannel(
            $id,
            request()->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(new ChannelResource($channel));
    }

    public function join(
        int $id,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $chat->joinPublicChannel(
            $id,
            request()->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(null, 'Joined channel successfully.');
    }

    public function addMembers(
        int $id,
        ManageChannelMembersRequest $request,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $chat->addMembers(
            $id,
            (array) $request->validated('member_ids'),
            $request->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(null, 'Members added successfully.');
    }

    public function removeMember(
        int $id,
        int $userId,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $chat->removeMember(
            $id,
            $userId,
            request()->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(null, 'Member removed successfully.');
    }

    public function messages(
        int $id,
        Request $request,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $paginator = $chat->listMessages(
            $id,
            $request->query('cursor'),
            $request->user(),
            $currentWorkspace->requireId(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => [
                'messages' => MessageResource::collection($paginator->items()),
                'pagination' => [
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }

    public function sendMessage(
        int $id,
        SendMessageRequest $request,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $message = $chat->sendMessage(
            $id,
            new SendMessageData(
                body: (string) $request->validated('body'),
                parentId: $request->filled('parent_id') ? (int) $request->validated('parent_id') : null,
            ),
            $request->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::created(new MessageResource($message));
    }

    public function read(
        int $id,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $chat->markAsRead(
            $id,
            request()->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(null, 'Marked channel as read.');
    }

    public function search(
        int $id,
        SearchChannelMessagesRequest $request,
        ChatServiceInterface $chat,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $messages = $chat->searchChannelMessages(
            $id,
            (string) $request->validated('q'),
            $request->user(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(MessageResource::collection($messages));
    }
}

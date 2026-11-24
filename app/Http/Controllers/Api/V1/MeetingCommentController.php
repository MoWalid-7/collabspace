<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\User;
use App\Services\Contracts\CommentServiceInterface;
use App\Support\ApiResponse;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingCommentController extends Controller
{
    public function __construct(
        private readonly CommentServiceInterface $commentService,
        private readonly CurrentWorkspace $currentWorkspace,
    ) {}

    public function index(Request $request, int $meeting): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $comments = $this->commentService->listForMeeting(
            $this->currentWorkspace->requireId(),
            $meeting,
            $perPage
        );

        return ApiResponse::ok(CommentResource::collection($comments));
    }

    public function store(StoreCommentRequest $request, int $meeting): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $comment = $this->commentService->createForMeeting(
            $this->currentWorkspace->requireId(),
            $meeting,
            $user->id,
            $request->string('body')->toString()
        );

        return ApiResponse::created(new CommentResource($comment));
    }
}

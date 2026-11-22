<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Data\Tasks\TaskCommentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\CreateTaskCommentRequest;
use App\Http\Requests\Tasks\ListTaskCommentsRequest;
use App\Http\Resources\CommentResource;
use App\Services\Contracts\CommentServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TaskCommentController extends Controller
{
    public function index(
        ListTaskCommentsRequest $request,
        CommentServiceInterface $comments,
        int $task,
    ): JsonResponse {
        $paginator = $comments->listForTask(
            $task,
            (int) $request->validated('per_page', 15),
        );

        return ApiResponse::success([
            'comments' => CommentResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(
        CreateTaskCommentRequest $request,
        CommentServiceInterface $comments,
        int $task,
    ): JsonResponse {
        $comment = $comments->create(new TaskCommentData(
            taskId: $task,
            userId: (int) $request->user()->getKey(),
            body: $request->validated('body'),
        ));

        return ApiResponse::created([
            'comment' => CommentResource::make($comment),
        ], 'Comment added successfully.');
    }
}

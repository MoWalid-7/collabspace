<?php

namespace App\Http\Controllers\Api\V1\Comments;

use App\Data\Tasks\UpdateCommentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\DeleteCommentRequest;
use App\Http\Requests\Comments\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Services\Contracts\CommentServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    public function update(
        UpdateCommentRequest $request,
        CommentServiceInterface $comments,
        int $comment,
    ): JsonResponse {
        return ApiResponse::success([
            'comment' => CommentResource::make($comments->update(
                new UpdateCommentData($comment, $request->validated('body')),
            )),
        ], 'Comment updated successfully.');
    }

    public function destroy(
        DeleteCommentRequest $request,
        CommentServiceInterface $comments,
        int $comment,
    ): Response {
        $comments->delete($comment);

        return ApiResponse::noContent();
    }
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\Comment;
use App\Models\Meeting;
use App\Models\Task;
use App\Repositories\Contracts\CommentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommentRepository implements CommentRepositoryInterface
{
    public function paginateForTask(Task $task, int $perPage): LengthAwarePaginator
    {
        return $task->comments()
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function paginateForMeeting(Meeting $meeting, int $perPage): LengthAwarePaginator
    {
        return $meeting->comments()
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function createForTask(Task $task, int $userId, string $body): Comment
    {
        $comment = $task->comments()->create([
            'workspace_id' => $task->workspace_id,
            'user_id' => $userId,
            'body' => $body,
        ]);

        return $comment->load('user');
    }

    public function createForMeeting(Meeting $meeting, int $userId, string $body): Comment
    {
        /** @var Comment $comment */
        $comment = $meeting->comments()->create([
            'workspace_id' => $meeting->workspace_id,
            'user_id' => $userId,
            'body' => $body,
        ]);

        return $comment->load('user');
    }

    public function findInCurrentWorkspaceOrFail(int $commentId): Comment
    {
        return Comment::query()->with('user')->findOrFail($commentId);
    }

    public function updateComment(Comment $comment, string $body): Comment
    {
        $comment->update(['body' => $body]);

        return $comment->refresh()->load('user');
    }

    public function softDelete(Comment $comment): void
    {
        $comment->delete();
    }
}

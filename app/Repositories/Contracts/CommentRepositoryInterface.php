<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Comment;
use App\Models\Meeting;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CommentRepositoryInterface
{
    public function paginateForTask(Task $task, int $perPage): LengthAwarePaginator;

    public function paginateForMeeting(Meeting $meeting, int $perPage): LengthAwarePaginator;

    public function createForTask(Task $task, int $userId, string $body): Comment;

    public function createForMeeting(Meeting $meeting, int $userId, string $body): Comment;

    public function findInCurrentWorkspaceOrFail(int $commentId): Comment;

    public function updateComment(Comment $comment, string $body): Comment;

    public function softDelete(Comment $comment): void;
}

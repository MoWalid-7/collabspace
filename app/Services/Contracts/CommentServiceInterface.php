<?php

namespace App\Services\Contracts;

use App\Data\Tasks\TaskCommentData;
use App\Data\Tasks\UpdateCommentData;
use App\Models\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CommentServiceInterface
{
    public function listForTask(int $taskId, int $perPage): LengthAwarePaginator;

    public function listForMeeting(int $workspaceId, int $meetingId, int $perPage): LengthAwarePaginator;

    public function create(TaskCommentData $data): Comment;

    public function createForMeeting(int $workspaceId, int $meetingId, int $userId, string $body): Comment;

    public function update(UpdateCommentData $data): Comment;

    public function delete(int $commentId): void;
}

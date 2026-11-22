<?php

namespace App\Services;

use App\Data\Tasks\TaskCommentData;
use App\Data\Tasks\UpdateCommentData;
use App\Events\CommentAdded;
use App\Models\Comment;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Contracts\CommentServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommentService implements CommentServiceInterface
{
    public function __construct(
        private CommentRepositoryInterface $comments,
        private TaskRepositoryInterface $tasks,
    ) {}

    public function listForTask(int $taskId, int $perPage): LengthAwarePaginator
    {
        $task = $this->tasks->findInCurrentWorkspaceOrFail($taskId);

        return $this->comments->paginateForTask($task, $perPage);
    }

    public function create(TaskCommentData $data): Comment
    {
        $task = $this->tasks->findInCurrentWorkspaceOrFail($data->taskId);
        $comment = $this->comments->createForTask($task, $data->userId, $data->body);

        CommentAdded::dispatch($task, $comment);

        return $comment;
    }

    public function update(UpdateCommentData $data): Comment
    {
        $comment = $this->comments->findInCurrentWorkspaceOrFail($data->commentId);

        return $this->comments->updateComment($comment, $data->body);
    }

    public function delete(int $commentId): void
    {
        $this->comments->softDelete($this->comments->findInCurrentWorkspaceOrFail($commentId));
    }
}

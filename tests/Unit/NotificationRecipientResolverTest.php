<?php

use App\Enums\TaskStatus;
use App\Events\CommentAdded;
use App\Events\TaskStatusChanged;
use App\Models\Comment;
use App\Models\Task;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Services\NotificationRecipientResolver;
use Mockery;

it('deduplicates task participants and never resolves the comment actor', function () {
    $task = new Task;
    $task->setAttribute('id', 5);
    $comment = new Comment;
    $comment->setAttribute('user_id', 1);
    $repository = Mockery::mock(NotificationRepositoryInterface::class);
    $repository->shouldReceive('taskAssigneeIds')->once()->with($task)->andReturn([1, 2, 3, 2]);
    $repository->shouldReceive('taskCreatorId')->once()->with($task)->andReturn(2);

    $recipients = (new NotificationRecipientResolver($repository))
        ->forCommentAdded(new CommentAdded($task, $comment));

    expect($recipients)->toBe([2, 3]);
});

it('resolves status notifications to reviewer or creator', function () {
    $task = new Task;
    $task->setAttribute('reviewer_id', 7);
    $task->setAttribute('created_by', 8);
    $repository = Mockery::mock(NotificationRepositoryInterface::class);
    $repository->shouldReceive('taskReviewerId')->once()->with($task)->andReturn(7);
    $repository->shouldReceive('taskCreatorId')->once()->with($task)->andReturn(8);
    $resolver = new NotificationRecipientResolver($repository);

    $reviewer = $resolver->forTaskStatusChanged(
        new TaskStatusChanged($task, TaskStatus::InProgress, TaskStatus::InReview, 4),
    );
    $creator = $resolver->forTaskStatusChanged(
        new TaskStatusChanged($task, TaskStatus::InReview, TaskStatus::Completed, 4),
    );
    $noRecipient = $resolver->forTaskStatusChanged(
        new TaskStatusChanged($task, TaskStatus::Todo, TaskStatus::InProgress, 4),
    );

    expect($reviewer)->toBe([7])
        ->and($creator)->toBe([8])
        ->and($noRecipient)->toBe([]);
});

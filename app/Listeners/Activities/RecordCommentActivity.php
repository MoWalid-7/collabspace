<?php

namespace App\Listeners\Activities;

use App\Enums\ActivityEvent;
use App\Events\CommentAdded;
use App\Models\Comment;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordCommentActivity implements ShouldQueue
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(CommentAdded $event): void
    {
        $this->logger->record(
            ActivityEvent::CommentAdded,
            (int) $event->task->workspace_id,
            (int) $event->task->project_id,
            (int) $event->comment->user_id,
            Comment::class,
            (int) $event->comment->getKey(),
            ['task_title' => $event->task->title],
        );
    }
}

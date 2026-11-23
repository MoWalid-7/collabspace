<?php

namespace App\Events;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Task $task,
        public TaskStatus $previousStatus,
        public TaskStatus $status,
        public int $actorId,
    ) {}
}

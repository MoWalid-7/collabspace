<?php

namespace App\Data\Tasks;

readonly class TaskCommentData
{
    public function __construct(
        public int $taskId,
        public int $userId,
        public string $body,
    ) {}
}

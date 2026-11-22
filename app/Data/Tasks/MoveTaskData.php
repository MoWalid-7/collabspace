<?php

namespace App\Data\Tasks;

use App\Enums\TaskStatus;

readonly class MoveTaskData
{
    public function __construct(
        public int $taskId,
        public TaskStatus $status,
        public int $position,
    ) {}

    /**
     * @param  array{status: string, position: int}  $attributes
     */
    public static function from(int $taskId, array $attributes): self
    {
        return new self(
            taskId: $taskId,
            status: TaskStatus::from($attributes['status']),
            position: $attributes['position'],
        );
    }
}

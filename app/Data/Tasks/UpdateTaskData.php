<?php

namespace App\Data\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;

readonly class UpdateTaskData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public int $taskId,
        public array $attributes,
        public ?TaskStatus $status,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function from(int $taskId, array $attributes): self
    {
        $status = isset($attributes['status'])
            ? TaskStatus::from($attributes['status'])
            : null;

        unset($attributes['status']);

        if (isset($attributes['priority'])) {
            $attributes['priority'] = TaskPriority::from($attributes['priority']);
        }

        return new self($taskId, $attributes, $status);
    }
}

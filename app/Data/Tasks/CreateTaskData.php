<?php

namespace App\Data\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;

readonly class CreateTaskData
{
    /**
     * @param  list<int>  $assigneeIds
     */
    public function __construct(
        public int $workspaceId,
        public int $projectId,
        public int $createdBy,
        public string $title,
        public ?string $description,
        public TaskPriority $priority,
        public TaskStatus $status,
        public ?string $dueDate,
        public array $assigneeIds,
        public ?int $reviewerId,
        public int $progress,
    ) {}

    /**
     * @param  array{title: string, description?: string|null, priority?: string, status?: string, due_date?: string|null, assignee_ids?: list<int>, reviewer_id?: int|null, progress?: int}  $attributes
     */
    public static function from(array $attributes, int $workspaceId, int $projectId, int $createdBy): self
    {
        return new self(
            workspaceId: $workspaceId,
            projectId: $projectId,
            createdBy: $createdBy,
            title: $attributes['title'],
            description: $attributes['description'] ?? null,
            priority: TaskPriority::from($attributes['priority'] ?? TaskPriority::Medium->value),
            status: TaskStatus::from($attributes['status'] ?? TaskStatus::Todo->value),
            dueDate: $attributes['due_date'] ?? null,
            assigneeIds: array_map('intval', $attributes['assignee_ids'] ?? []),
            reviewerId: isset($attributes['reviewer_id']) ? (int) $attributes['reviewer_id'] : null,
            progress: $attributes['progress'] ?? 0,
        );
    }
}

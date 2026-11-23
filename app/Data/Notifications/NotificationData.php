<?php

namespace App\Data\Notifications;

use App\Enums\NotificationType;

readonly class NotificationData
{
    /**
     * @param  array{id: int, name: string}  $actor
     */
    public function __construct(
        public NotificationType $type,
        public string $title,
        public string $body,
        public ?int $projectId,
        public ?int $taskId,
        public ?int $fileId,
        public array $actor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            'project_id' => $this->projectId,
            'task_id' => $this->taskId,
            'file_id' => $this->fileId,
            'actor' => $this->actor,
        ];
    }
}

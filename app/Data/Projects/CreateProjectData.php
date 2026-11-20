<?php

namespace App\Data\Projects;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;

readonly class CreateProjectData
{
    /**
     * @param  list<int>  $memberIds
     * @param  list<int>  $teamIds
     */
    public function __construct(
        public int $workspaceId,
        public int $createdBy,
        public string $name,
        public ?string $description,
        public string $type,
        public ProjectPriority $priority,
        public ProjectStatus $status,
        public string $startDate,
        public string $deadline,
        public array $memberIds,
        public array $teamIds,
    ) {}

    /**
     * @param  array{name: string, description?: string|null, type: string, priority: string, status: string, start_date: string, deadline: string, member_ids?: list<int|string>, team_ids?: list<int|string>}  $validated
     */
    public static function from(array $validated, int $workspaceId, int $createdBy): self
    {
        return new self(
            workspaceId: $workspaceId,
            createdBy: $createdBy,
            name: $validated['name'],
            description: $validated['description'] ?? null,
            type: $validated['type'],
            priority: ProjectPriority::from($validated['priority']),
            status: ProjectStatus::from($validated['status']),
            startDate: $validated['start_date'],
            deadline: $validated['deadline'],
            memberIds: array_map('intval', $validated['member_ids'] ?? []),
            teamIds: array_map('intval', $validated['team_ids'] ?? []),
        );
    }
}

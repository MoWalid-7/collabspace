<?php

namespace App\Data\Teams;

readonly class CreateTeamData
{
    public function __construct(
        public int $workspaceId,
        public string $name,
        public ?string $description,
        public ?int $leadId,
    ) {}

    /**
     * @param  array{name: string, description?: string|null, lead_id?: int|null}  $validated
     */
    public static function from(array $validated, int $workspaceId): self
    {
        return new self(
            workspaceId: $workspaceId,
            name: $validated['name'],
            description: $validated['description'] ?? null,
            leadId: isset($validated['lead_id']) ? (int) $validated['lead_id'] : null,
        );
    }
}

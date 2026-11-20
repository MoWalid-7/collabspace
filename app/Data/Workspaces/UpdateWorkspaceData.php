<?php

namespace App\Data\Workspaces;

readonly class UpdateWorkspaceData
{
    public function __construct(
        public int $workspaceId,
        public int $userId,
        public string $name,
    ) {}
}

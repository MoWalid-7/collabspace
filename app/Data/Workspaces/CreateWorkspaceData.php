<?php

namespace App\Data\Workspaces;

readonly class CreateWorkspaceData
{
    public function __construct(
        public string $name,
        public int $ownerId,
    ) {}
}

<?php

namespace App\Data\Workspaces;

readonly class RemoveWorkspaceMemberData
{
    public function __construct(
        public int $workspaceId,
        public int $memberId,
        public int $removedBy,
    ) {}
}

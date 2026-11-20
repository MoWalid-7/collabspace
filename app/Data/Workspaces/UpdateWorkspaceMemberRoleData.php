<?php

namespace App\Data\Workspaces;

use App\Enums\WorkspaceRole;

readonly class UpdateWorkspaceMemberRoleData
{
    public function __construct(
        public int $workspaceId,
        public int $memberId,
        public int $changedBy,
        public WorkspaceRole $role,
    ) {}
}

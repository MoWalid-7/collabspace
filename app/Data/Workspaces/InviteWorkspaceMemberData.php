<?php

namespace App\Data\Workspaces;

use App\Enums\WorkspaceRole;

readonly class InviteWorkspaceMemberData
{
    public function __construct(
        public int $workspaceId,
        public int $invitedBy,
        public string $email,
        public WorkspaceRole $role,
    ) {}
}

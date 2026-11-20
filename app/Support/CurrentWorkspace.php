<?php

namespace App\Support;

use App\Models\Workspace;
use LogicException;

class CurrentWorkspace
{
    private ?Workspace $workspace = null;

    public function set(Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function get(): ?Workspace
    {
        return $this->workspace;
    }

    public function id(): ?int
    {
        return $this->workspace?->getKey();
    }

    public function requireId(): int
    {
        return $this->id() ?? throw new LogicException('No current workspace has been set.');
    }
}

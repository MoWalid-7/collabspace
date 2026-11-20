<?php

namespace App\Http\Requests\Teams;

use App\Support\CurrentWorkspace;
use Illuminate\Validation\Rule;

abstract class TeamMemberRequest extends TeamAccessRequest
{
    protected function workspaceMemberRule(): mixed
    {
        $workspaceId = app(CurrentWorkspace::class)->id();

        return Rule::exists('workspace_user', 'user_id')
            ->where(fn ($query) => $query->where('workspace_id', $workspaceId));
    }
}

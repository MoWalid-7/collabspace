<?php

namespace App\Http\Requests\Teams;

class RemoveTeamMemberRequest extends TeamMemberRequest
{
    public function authorize(): bool
    {
        return $this->allows('manageMembers');
    }

    public function rules(): array
    {
        return [];
    }
}

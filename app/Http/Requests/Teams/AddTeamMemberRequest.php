<?php

namespace App\Http\Requests\Teams;

class AddTeamMemberRequest extends TeamMemberRequest
{
    public function authorize(): bool
    {
        return $this->allows('manageMembers');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', $this->workspaceMemberRule()],
            'role_title' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}

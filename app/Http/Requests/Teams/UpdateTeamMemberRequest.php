<?php

namespace App\Http\Requests\Teams;

class UpdateTeamMemberRequest extends TeamMemberRequest
{
    public function authorize(): bool
    {
        return $this->allows('manageMembers');
    }

    public function rules(): array
    {
        return [
            'role_title' => ['present', 'nullable', 'string', 'max:255'],
        ];
    }
}

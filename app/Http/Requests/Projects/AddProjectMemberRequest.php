<?php

namespace App\Http\Requests\Projects;

class AddProjectMemberRequest extends ProjectMemberRequest
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

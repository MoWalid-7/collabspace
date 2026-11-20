<?php

namespace App\Http\Requests\Projects;

class RemoveProjectMemberRequest extends ProjectMemberRequest
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

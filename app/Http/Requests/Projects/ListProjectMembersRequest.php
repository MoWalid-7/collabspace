<?php

namespace App\Http\Requests\Projects;

class ListProjectMembersRequest extends ProjectAccessRequest
{
    public function authorize(): bool
    {
        return $this->allows('view');
    }

    public function rules(): array
    {
        return [];
    }
}

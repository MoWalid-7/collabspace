<?php

namespace App\Http\Requests\Projects;

class DeleteProjectRequest extends ProjectAccessRequest
{
    public function authorize(): bool
    {
        return $this->allows('delete');
    }

    public function rules(): array
    {
        return [];
    }
}

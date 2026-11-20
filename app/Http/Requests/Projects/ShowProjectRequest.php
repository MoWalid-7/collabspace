<?php

namespace App\Http\Requests\Projects;

class ShowProjectRequest extends ProjectAccessRequest
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

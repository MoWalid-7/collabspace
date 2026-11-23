<?php

namespace App\Http\Requests\Activities;

use App\Http\Requests\Projects\ProjectAccessRequest;

class ListProjectActivitiesRequest extends ProjectAccessRequest
{
    public function authorize(): bool
    {
        return $this->allows('viewActivities');
    }

    public function rules(): array
    {
        return ['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}

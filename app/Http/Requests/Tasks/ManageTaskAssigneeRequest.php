<?php

namespace App\Http\Requests\Tasks;

class ManageTaskAssigneeRequest extends TaskAccessRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->allowsTask('manageAssignees');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'integer', 'min:1'],
        ];
    }
}

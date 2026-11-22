<?php

namespace App\Http\Requests\Tasks;

class UpdateTaskProgressRequest extends TaskAccessRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->allowsTask('updateProgress');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'progress' => ['required', 'integer', 'between:0,100'],
        ];
    }
}

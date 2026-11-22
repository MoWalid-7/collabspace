<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use Illuminate\Validation\Rule;

class TaskBoardRequest extends TaskAccessRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->allowsProject('viewTasks');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'priority' => ['sometimes', 'nullable', Rule::enum(TaskPriority::class)],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}

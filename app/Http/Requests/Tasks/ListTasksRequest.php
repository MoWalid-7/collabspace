<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListTasksRequest extends TaskAccessRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['mine', 'overdue'] as $filter) {
            $value = $this->input($filter);

            if (is_string($value)) {
                $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                if ($boolean !== null) {
                    $this->merge([$filter => $boolean]);
                }
            }
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Task::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mine' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', 'nullable', Rule::enum(TaskPriority::class)],
            'project_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'due_before' => ['sometimes', 'nullable', 'date'],
            'due_after' => ['sometimes', 'nullable', 'date'],
            'overdue' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

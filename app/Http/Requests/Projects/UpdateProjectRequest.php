<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends ProjectAccessRequest
{
    public function authorize(): bool
    {
        return $this->allows('update');
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'string', 'max:255'],
            'priority' => ['sometimes', Rule::enum(ProjectPriority::class)],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'start_date' => ['sometimes', 'date'],
            'deadline' => [
                'sometimes',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || strtotime($value) === false) {
                        return;
                    }

                    $startDate = $this->input('start_date');

                    if ($startDate !== null && (! is_string($startDate) || strtotime($startDate) === false)) {
                        return;
                    }

                    $startTimestamp = $startDate === null
                        ? $this->project()->start_date?->getTimestamp()
                        : strtotime($startDate);

                    if ($startTimestamp !== null && strtotime($value) < $startTimestamp) {
                        $fail('The deadline must be a date after or equal to the start date.');
                    }
                },
            ],
        ];
    }
}

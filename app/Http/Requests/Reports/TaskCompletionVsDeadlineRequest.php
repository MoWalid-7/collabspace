<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class TaskCompletionVsDeadlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'range' => ['nullable', 'in:week,year'],
            'project_id' => ['nullable', 'integer'],
        ];
    }
}

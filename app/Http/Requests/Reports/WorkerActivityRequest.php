<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class WorkerActivityRequest extends FormRequest
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
            'period' => ['nullable', 'in:monthly,weekly'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'project_id' => ['nullable', 'integer'],
        ];
    }
}

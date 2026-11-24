<?php

declare(strict_types=1);

namespace App\Http\Requests\DailyStatus;

use App\Data\DailyStatus\DailyStatusFilters;
use Illuminate\Foundation\Http\FormRequest;

class GetDailyStatusesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'project_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
        ];
    }

    public function toFilters(): DailyStatusFilters
    {
        return new DailyStatusFilters(
            date: $this->input('date'),
            projectId: $this->filled('project_id') ? (int) $this->input('project_id') : null,
            userId: $this->filled('user_id') ? (int) $this->input('user_id') : null,
        );
    }
}

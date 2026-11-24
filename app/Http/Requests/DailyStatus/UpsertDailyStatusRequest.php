<?php

declare(strict_types=1);

namespace App\Http\Requests\DailyStatus;

use App\Data\DailyStatus\UpsertDailyStatusData;
use Illuminate\Foundation\Http\FormRequest;

class UpsertDailyStatusRequest extends FormRequest
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
            'project_id' => ['nullable', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'done_yesterday' => ['required', 'string'],
            'plan_today' => ['required', 'string'],
            'blockers' => ['nullable', 'string'],
        ];
    }

    public function toDto(): UpsertDailyStatusData
    {
        return new UpsertDailyStatusData(
            projectId: $this->filled('project_id') ? (int) $this->input('project_id') : null,
            date: $this->string('date')->toString(),
            doneYesterday: $this->string('done_yesterday')->toString(),
            planToday: $this->string('plan_today')->toString(),
            blockers: $this->filled('blockers') ? $this->string('blockers')->toString() : null,
        );
    }
}

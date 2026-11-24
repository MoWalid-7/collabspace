<?php

declare(strict_types=1);

namespace App\Http\Requests\Meetings;

use App\Data\Meetings\MeetingFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class GetMeetingsRequest extends FormRequest
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
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'week_start' => ['nullable', 'date'],
            'project_id' => ['nullable', 'integer'],
            'mine' => ['nullable', 'boolean'],
        ];
    }

    public function toFilters(): MeetingFilters
    {
        $from = $this->input('from') ? CarbonImmutable::parse($this->input('from')) : null;
        $to = $this->input('to') ? CarbonImmutable::parse($this->input('to')) : null;

        if ($this->filled('week_start')) {
            $weekStart = CarbonImmutable::parse($this->input('week_start'))->startOfDay();
            $from = $weekStart;
            $to = $weekStart->addDays(7)->endOfDay();
        }

        return new MeetingFilters(
            from: $from,
            to: $to,
            projectId: $this->filled('project_id') ? (int) $this->input('project_id') : null,
            mine: $this->boolean('mine'),
        );
    }
}

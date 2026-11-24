<?php

declare(strict_types=1);

namespace App\Http\Requests\Meetings;

use App\Data\Meetings\CreateMeetingData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class CreateMeetingRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'join_url' => ['nullable', 'url', 'max:2048'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer'],
        ];
    }

    public function toDto(): CreateMeetingData
    {
        return new CreateMeetingData(
            title: $this->string('title')->toString(),
            description: $this->filled('description') ? $this->string('description')->toString() : null,
            projectId: $this->filled('project_id') ? (int) $this->input('project_id') : null,
            startsAt: CarbonImmutable::parse($this->input('starts_at')),
            endsAt: CarbonImmutable::parse($this->input('ends_at')),
            joinUrl: $this->filled('join_url') ? $this->input('join_url') : null,
            participantIds: array_map('intval', $this->input('participant_ids', [])),
        );
    }
}

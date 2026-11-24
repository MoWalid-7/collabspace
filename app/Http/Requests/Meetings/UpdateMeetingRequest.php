<?php

declare(strict_types=1);

namespace App\Http\Requests\Meetings;

use App\Data\Meetings\UpdateMeetingData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_id' => ['nullable', 'integer'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'join_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    public function toDto(): UpdateMeetingData
    {
        return new UpdateMeetingData(
            title: $this->has('title') ? $this->string('title')->toString() : null,
            description: $this->has('description') ? ($this->input('description') ? $this->string('description')->toString() : null) : null,
            projectId: $this->has('project_id') ? ($this->input('project_id') ? (int) $this->input('project_id') : null) : null,
            startsAt: $this->has('starts_at') ? CarbonImmutable::parse($this->input('starts_at')) : null,
            endsAt: $this->has('ends_at') ? CarbonImmutable::parse($this->input('ends_at')) : null,
            joinUrl: $this->has('join_url') ? $this->input('join_url') : null,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Meetings;

use Illuminate\Foundation\Http\FormRequest;

class ManageParticipantsRequest extends FormRequest
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
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function participantIds(): array
    {
        return array_map('intval', $this->input('participant_ids', []));
    }
}

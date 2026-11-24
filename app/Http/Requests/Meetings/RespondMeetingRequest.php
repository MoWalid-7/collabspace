<?php

declare(strict_types=1);

namespace App\Http\Requests\Meetings;

use App\Enums\MeetingParticipantResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RespondMeetingRequest extends FormRequest
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
            'response' => ['required', new Enum(MeetingParticipantResponse::class)],
        ];
    }

    public function getResponseEnum(): MeetingParticipantResponse
    {
        return MeetingParticipantResponse::from($this->input('response'));
    }
}

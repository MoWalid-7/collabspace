<?php

declare(strict_types=1);

namespace App\Http\Requests\Meetings;

use Illuminate\Foundation\Http\FormRequest;

class MeetingNoteRequest extends FormRequest
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
            'body' => ['required', 'string'],
        ];
    }
}

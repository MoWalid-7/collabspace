<?php

namespace App\Http\Requests\TimeTracking;

use Illuminate\Foundation\Http\FormRequest;

class CreateManualTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'task_id' => ['required', 'integer'],
            'started_at' => ['required', 'date'],
            'ended_at' => [
                'required',
                'date',
                'after:started_at',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $started = $this->input('started_at');
                    if ($started && $value) {
                        $diff = abs(strtotime((string) $value) - strtotime((string) $started));
                        if ($diff > 86400) {
                            $fail('A single time entry cannot exceed 24 hours.');
                        }
                    }
                },
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

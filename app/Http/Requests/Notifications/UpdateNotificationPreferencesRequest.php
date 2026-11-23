<?php

namespace App\Http\Requests\Notifications;

use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];

        foreach (NotificationType::cases() as $type) {
            $rules[$type->value] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_keys($this->all()) as $key) {
                    if (NotificationType::tryFrom($key) === null) {
                        $validator->errors()->add($key, 'The selected notification type is invalid.');
                    }
                }
            },
        ];
    }
}

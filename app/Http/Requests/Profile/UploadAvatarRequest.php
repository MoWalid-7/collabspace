<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function avatar(): UploadedFile
    {
        $avatar = $this->validated('avatar');

        if (! $avatar instanceof UploadedFile) {
            throw new LogicException('A validated avatar upload is required.');
        }

        return $avatar;
    }
}

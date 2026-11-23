<?php

namespace App\Http\Requests\Files;

class ListTaskFilesRequest extends TaskFileAccessRequest
{
    protected function ability(): string
    {
        return 'viewAny';
    }

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

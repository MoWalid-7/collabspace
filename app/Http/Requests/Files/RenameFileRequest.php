<?php

namespace App\Http\Requests\Files;

class RenameFileRequest extends FileAccessRequest
{
    protected function ability(): string
    {
        return 'update';
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}

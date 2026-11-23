<?php

namespace App\Http\Requests\Files;

class DeleteFileRequest extends FileAccessRequest
{
    protected function ability(): string
    {
        return 'delete';
    }
}

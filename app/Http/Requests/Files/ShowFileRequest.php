<?php

namespace App\Http\Requests\Files;

class ShowFileRequest extends FileAccessRequest
{
    protected function ability(): string
    {
        return 'view';
    }
}

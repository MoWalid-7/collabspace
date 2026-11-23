<?php

namespace App\Http\Requests\Files;

class ListFileVersionsRequest extends FileAccessRequest
{
    protected function ability(): string
    {
        return 'view';
    }
}

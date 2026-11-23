<?php

namespace App\Http\Requests\Files;

class DownloadFileRequest extends FileAccessRequest
{
    protected function ability(): string
    {
        return 'download';
    }
}

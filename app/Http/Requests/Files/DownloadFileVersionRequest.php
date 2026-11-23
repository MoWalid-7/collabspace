<?php

namespace App\Http\Requests\Files;

class DownloadFileVersionRequest extends FileAccessRequest
{
    protected function ability(): string
    {
        return 'download';
    }
}

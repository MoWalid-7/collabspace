<?php

namespace App\Http\Requests\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use LogicException;

class UploadFileVersionRequest extends FileAccessRequest
{
    protected function ability(): string
    {
        return 'uploadVersion';
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config('collabspace.files.max_upload_size_kb'),
                'mimetypes:'.implode(',', config('collabspace.files.mime_types')),
                'extensions:'.implode(',', config('collabspace.files.extensions')),
            ],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function uploadedFile(): UploadedFile
    {
        $file = $this->validated('file');

        if (! $file instanceof UploadedFile) {
            throw new LogicException('A validated file upload is required.');
        }

        return $file;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('file');

                if ($file instanceof UploadedFile && $file->getSize() === 0) {
                    $validator->errors()->add('file', 'The file must not be empty.');
                }
            },
        ];
    }
}

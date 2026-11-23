<?php

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use LogicException;

abstract class FileUploadRequest extends FormRequest
{
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

    /**
     * @return list<callable(Validator): void>
     */
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

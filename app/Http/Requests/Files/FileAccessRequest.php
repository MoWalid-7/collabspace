<?php

namespace App\Http\Requests\Files;

use App\Models\File;
use App\Repositories\Contracts\FileRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;

abstract class FileAccessRequest extends FormRequest
{
    abstract protected function ability(): string;

    public function authorize(): bool
    {
        return $this->user()?->can(
            $this->ability(),
            $this->fileModel(),
        ) ?? false;
    }

    protected function fileModel(): File
    {
        return app(FileRepositoryInterface::class)
            ->findInCurrentWorkspaceOrFail((int) $this->route('file'));
    }

    public function rules(): array
    {
        return [];
    }
}

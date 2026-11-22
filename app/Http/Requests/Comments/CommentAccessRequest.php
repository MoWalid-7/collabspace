<?php

namespace App\Http\Requests\Comments;

use App\Models\Comment;
use App\Repositories\Contracts\CommentRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;

abstract class CommentAccessRequest extends FormRequest
{
    protected function comment(): Comment
    {
        return app(CommentRepositoryInterface::class)
            ->findInCurrentWorkspaceOrFail((int) $this->route('comment'));
    }

    protected function allows(string $ability): bool
    {
        return $this->user()?->can($ability, $this->comment()) ?? false;
    }
}

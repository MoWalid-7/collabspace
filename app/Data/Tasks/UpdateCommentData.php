<?php

namespace App\Data\Tasks;

readonly class UpdateCommentData
{
    public function __construct(
        public int $commentId,
        public string $body,
    ) {}
}

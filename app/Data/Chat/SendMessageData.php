<?php

namespace App\Data\Chat;

readonly class SendMessageData
{
    public function __construct(
        public string $body,
        public ?int $parentId = null,
    ) {}
}

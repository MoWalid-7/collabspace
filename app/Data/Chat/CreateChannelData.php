<?php

namespace App\Data\Chat;

readonly class CreateChannelData
{
    /**
     * @param  list<int>  $memberIds
     */
    public function __construct(
        public string $name,
        public array $memberIds = [],
    ) {}
}

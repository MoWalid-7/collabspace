<?php

namespace App\Data\Chat;

readonly class ChannelFilters
{
    public function __construct(
        public ?string $type = null,
        public ?string $search = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            type: isset($data['type']) && is_string($data['type']) && $data['type'] !== '' ? $data['type'] : null,
            search: isset($data['search']) && is_string($data['search']) && trim($data['search']) !== '' ? trim($data['search']) : null,
        );
    }
}

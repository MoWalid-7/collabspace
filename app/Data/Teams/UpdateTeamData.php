<?php

namespace App\Data\Teams;

readonly class UpdateTeamData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public int $teamId,
        public array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function from(int $teamId, array $validated): self
    {
        return new self($teamId, $validated);
    }
}

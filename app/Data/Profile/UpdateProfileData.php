<?php

namespace App\Data\Profile;

use App\Enums\AvailabilityStatus;

readonly class UpdateProfileData
{
    public function __construct(
        public ?string $fullName,
        public ?string $phone,
        public ?string $jobTitle,
        public ?int $yearsOfExperience,
        public ?string $about,
        public ?AvailabilityStatus $availabilityStatus,
        public array $providedFields,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            fullName: $attributes['full_name'] ?? null,
            phone: $attributes['phone'] ?? null,
            jobTitle: $attributes['job_title'] ?? null,
            yearsOfExperience: $attributes['years_of_experience'] ?? null,
            about: $attributes['about'] ?? null,
            availabilityStatus: isset($attributes['availability_status'])
                ? AvailabilityStatus::from($attributes['availability_status'])
                : null,
            providedFields: array_keys($attributes),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function userAttributes(): array
    {
        $attributes = [];

        foreach ($this->providedFields as $field) {
            $attributes[match ($field) {
                'full_name' => 'name',
                'job_title' => 'job_title',
                'years_of_experience' => 'years_of_experience',
                'availability_status' => 'availability_status',
                default => $field,
            }] = match ($field) {
                'full_name' => $this->fullName,
                'phone' => $this->phone,
                'job_title' => $this->jobTitle,
                'years_of_experience' => $this->yearsOfExperience,
                'about' => $this->about,
                'availability_status' => $this->availabilityStatus,
                default => null,
            };
        }

        return $attributes;
    }
}

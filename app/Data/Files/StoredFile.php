<?php

namespace App\Data\Files;

readonly class StoredFile
{
    public function __construct(
        public string $disk,
        public string $path,
        public int $size,
        public string $mime,
        public string $checksum,
    ) {}
}

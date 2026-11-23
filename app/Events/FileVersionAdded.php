<?php

namespace App\Events;

use App\Models\File;
use App\Models\FileVersion;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileVersionAdded implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public File $file,
        public FileVersion $version,
        public int $actorId,
    ) {}
}

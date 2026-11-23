<?php

namespace App\Models;

use Database\Factories\FileVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'file_id',
    'version_number',
    'disk',
    'path',
    'original_name',
    'mime',
    'size',
    'checksum',
    'uploaded_by',
    'note',
])]
class FileVersion extends Model
{
    /** @use HasFactory<FileVersionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $meeting_id
 * @property int $user_id
 * @property string $body
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Meeting $meeting
 * @property-read User $user
 */
class MeetingNote extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'meeting_id',
        'user_id',
        'body',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

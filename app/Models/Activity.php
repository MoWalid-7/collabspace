<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property ActivityEvent $event
 * @property array<string, mixed> $properties
 * @property Carbon $created_at
 */
#[Fillable(['workspace_id', 'project_id', 'causer_id', 'subject_type', 'subject_id', 'event', 'properties', 'created_at'])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use BelongsToWorkspace, HasFactory;

    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'event' => ActivityEvent::class,
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }
}

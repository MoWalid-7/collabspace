<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonInterface;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int|null $project_id
 * @property string $title
 * @property string|null $description
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 * @property string|null $join_url
 * @property MeetingStatus $status
 * @property int $created_by
 * @property CarbonInterface|null $reminder_sent_at
 * @property CarbonInterface|null $deleted_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Workspace $workspace
 * @property-read Project|null $project
 * @property-read User $creator
 * @property-read Collection<int, User> $participants
 * @property-read Collection<int, MeetingNote> $notes
 * @property-read Collection<int, Comment> $comments
 */
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'project_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'join_url',
        'status',
        'created_by',
        'reminder_sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'status' => MeetingStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants')
            ->withPivot('response')
            ->withTimestamps();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(MeetingNote::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

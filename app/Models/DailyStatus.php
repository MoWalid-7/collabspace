<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonInterface;
use Database\Factories\DailyStatusFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $user_id
 * @property int|null $project_id
 * @property string $date
 * @property string $done_yesterday
 * @property string $plan_today
 * @property string|null $blockers
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Workspace $workspace
 * @property-read User $user
 * @property-read Project|null $project
 */
class DailyStatus extends Model
{
    /** @use HasFactory<DailyStatusFactory> */
    use BelongsToWorkspace, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'user_id',
        'project_id',
        'date',
        'done_yesterday',
        'plan_today',
        'blockers',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

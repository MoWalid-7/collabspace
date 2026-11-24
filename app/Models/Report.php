<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $user_id
 * @property ReportType $type
 * @property array<string, mixed> $params
 * @property ReportStatus $status
 * @property string|null $file_path
 * @property string|null $note
 * @property CarbonInterface|null $generated_at
 * @property string|null $error
 * @property-read Workspace $workspace
 * @property-read User $user
 */
class Report extends Model
{
    use BelongsToWorkspace, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'user_id',
        'type',
        'params',
        'status',
        'file_path',
        'note',
        'generated_at',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'status' => ReportStatus::class,
            'params' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

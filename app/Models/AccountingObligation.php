<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingObligation extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_CRITICAL = 'critical';

    public const STAGE_BACKLOG = 'backlog';

    public const STAGE_IN_PROGRESS = 'in_progress';

    public const STAGE_REVIEW = 'review';

    public const STAGE_DONE = 'done';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'client_id',
        'title',
        'obligation_type',
        'competence_date',
        'due_date',
        'status',
        'priority',
        'stage_code',
        'assigned_to_name',
        'started_at',
        'reminder_at',
        'last_reminder_at',
        'completed_at',
        'notes',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'competence_date' => 'date',
            'due_date' => 'date',
            'started_at' => 'datetime',
            'reminder_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(AccountingTaskHistory::class, 'accounting_obligation_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingDocumentRequest extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'client_id',
        'template_id',
        'title',
        'document_type',
        'due_date',
        'status',
        'protocol_code',
        'checklist_items',
        'pending_items_count',
        'last_version_number',
        'last_reminder_at',
        'received_at',
        'file_path',
        'notes',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'checklist_items' => 'array',
            'pending_items_count' => 'integer',
            'last_version_number' => 'integer',
            'last_reminder_at' => 'datetime',
            'received_at' => 'datetime',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(AccountingServiceTemplate::class, 'template_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AccountingDocumentVersion::class, 'accounting_document_request_id');
    }
}

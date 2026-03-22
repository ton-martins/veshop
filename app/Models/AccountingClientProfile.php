<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingClientProfile extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'client_id',
        'service_regime',
        'contract_number',
        'contract_start_date',
        'contract_end_date',
        'monthly_fee',
        'billing_day',
        'sla_hours',
        'responsible_name',
        'responsible_email',
        'responsible_phone',
        'reminder_email_enabled',
        'reminder_whatsapp_enabled',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'monthly_fee' => 'decimal:2',
            'billing_day' => 'integer',
            'sla_hours' => 'integer',
            'reminder_email_enabled' => 'boolean',
            'reminder_whatsapp_enabled' => 'boolean',
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
}

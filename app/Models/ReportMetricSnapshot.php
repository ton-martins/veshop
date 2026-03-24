<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportMetricSnapshot extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'plan_id',
        'profile_key',
        'metric_key',
        'niche',
        'business_type',
        'granularity',
        'period_start',
        'period_end',
        'dimension_type',
        'dimension_key',
        'dimension_label',
        'value_decimal',
        'value_integer',
        'payload',
        'captured_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'value_decimal' => 'decimal:2',
            'value_integer' => 'integer',
            'payload' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}

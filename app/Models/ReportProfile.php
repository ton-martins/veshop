<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportProfile extends Model
{
    use HasFactory;

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_NICHE = 'niche';

    public const SCOPE_BUSINESS_TYPE = 'business_type';

    public const SCOPE_PLAN = 'plan';

    public const SCOPE_CONTRACTOR = 'contractor';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'plan_id',
        'profile_key',
        'scope',
        'name',
        'niche',
        'business_type',
        'is_active',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config' => 'array',
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

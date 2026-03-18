<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentGateway extends Model
{
    use HasFactory, SoftDeletes;

    public const PROVIDER_MANUAL = 'manual';

    public const PROVIDER_MERCADO_PAGO = 'mercado_pago';

    public const SUPPORTED_PROVIDERS = [
        self::PROVIDER_MANUAL,
        self::PROVIDER_MERCADO_PAGO,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'provider',
        'name',
        'is_active',
        'is_default',
        'is_sandbox',
        'credentials',
        'settings',
        'last_health_check_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_sandbox' => 'boolean',
            'credentials' => 'array',
            'settings' => 'array',
            'last_health_check_at' => 'datetime',
        ];
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }
}

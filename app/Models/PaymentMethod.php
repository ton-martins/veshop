<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    public const CODE_PIX = 'pix';

    public const CODE_BOLETO = 'boleto';

    public const CODE_CREDIT_CARD = 'credit_card';

    public const CODE_DEBIT_CARD = 'debit_card';

    public const CODE_CASH = 'cash';

    public const CODE_INSTALLMENT = 'installment';

    /**
     * @var list<string>
     */
    public const INTEGRATED_CODES = [
        self::CODE_PIX,
        self::CODE_CREDIT_CARD,
        self::CODE_DEBIT_CARD,
        self::CODE_BOLETO,
    ];

    /**
     * @var list<string>
     */
    public const MANUAL_CODES = [
        self::CODE_PIX,
        self::CODE_BOLETO,
        self::CODE_CASH,
        self::CODE_DEBIT_CARD,
        self::CODE_CREDIT_CARD,
        self::CODE_INSTALLMENT,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'payment_gateway_id',
        'code',
        'name',
        'is_active',
        'is_default',
        'allows_installments',
        'max_installments',
        'fee_fixed',
        'fee_percent',
        'sort_order',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'allows_installments' => 'boolean',
            'max_installments' => 'integer',
            'fee_fixed' => 'decimal:2',
            'fee_percent' => 'decimal:2',
            'sort_order' => 'integer',
            'settings' => 'array',
        ];
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }
}

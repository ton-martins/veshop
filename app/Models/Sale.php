<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    public const SOURCE_PDV = 'pdv';

    public const SOURCE_ORDER = 'order';

    public const SOURCE_CATALOG = 'catalog';

    public const SOURCE_INTEGRATION = 'integration';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_NEW = 'new';

    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const SHIPPING_MODE_PICKUP = 'pickup';

    public const SHIPPING_MODE_DELIVERY = 'delivery';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'cash_session_id',
        'client_id',
        'shop_customer_id',
        'user_id',
        'code',
        'checkout_idempotency_key',
        'source',
        'status',
        'subtotal_amount',
        'discount_amount',
        'surcharge_amount',
        'total_amount',
        'paid_amount',
        'change_amount',
        'shipping_mode',
        'shipping_amount',
        'shipping_estimate_days',
        'shipping_address',
        'notes',
        'completed_at',
        'cancelled_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'surcharge_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'shipping_estimate_days' => 'integer',
            'shipping_address' => 'array',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function shopCustomer(): BelongsTo
    {
        return $this->belongsTo(ShopCustomer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }
}

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

    public const MP_STATUS_DISCONNECTED = 'disconnected';

    public const MP_STATUS_CONNECTED = 'connected';

    public const MP_STATUS_EXPIRED = 'expired';

    public const MP_STATUS_REVOKED = 'revoked';

    public const MP_STATUS_ERROR = 'error';

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
        'mp_user_id',
        'mp_public_key',
        'mp_access_token',
        'mp_refresh_token',
        'mp_token_expires_at',
        'mp_scope',
        'mp_live_mode',
        'mp_status',
        'mp_connected_at',
        'mp_last_error',
        'mp_metadata',
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
            'mp_access_token' => 'encrypted',
            'mp_refresh_token' => 'encrypted',
            'mp_token_expires_at' => 'datetime',
            'mp_live_mode' => 'boolean',
            'mp_connected_at' => 'datetime',
            'mp_metadata' => 'array',
            'settings' => 'array',
            'last_health_check_at' => 'datetime',
        ];
    }

    public function resolveMercadoPagoAccessToken(): string
    {
        $oauthToken = trim((string) ($this->mp_access_token ?? ''));
        if ($oauthToken !== '') {
            return $oauthToken;
        }

        $credentials = is_array($this->credentials) ? $this->credentials : [];

        return trim((string) ($credentials['access_token'] ?? ''));
    }

    public function resolveMercadoPagoWebhookSecret(): string
    {
        $credentials = is_array($this->credentials) ? $this->credentials : [];

        return trim((string) ($credentials['webhook_secret'] ?? ''));
    }

    public function hasMercadoPagoOAuthConnection(): bool
    {
        if ($this->provider !== self::PROVIDER_MERCADO_PAGO) {
            return false;
        }

        $token = trim((string) ($this->mp_access_token ?? ''));
        if ($token === '') {
            return false;
        }

        return trim((string) ($this->mp_status ?? self::MP_STATUS_DISCONNECTED)) === self::MP_STATUS_CONNECTED;
    }

    public function isMercadoPagoTokenExpired(): bool
    {
        if (! $this->mp_token_expires_at) {
            return false;
        }

        return $this->mp_token_expires_at->isPast();
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

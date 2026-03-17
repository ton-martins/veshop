<?php

namespace App\Models;

use App\Notifications\Shop\VerifyShopCustomerEmailNotification;
use App\Support\BrazilData;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class ShopCustomer extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory, Notifiable, SoftDeletes, MustVerifyEmail;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'client_id',
        'name',
        'email',
        'phone',
        'cep',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'password',
        'is_active',
        'email_verified_at',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
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

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(ShopCustomerFavorite::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyShopCustomerEmailNotification());
    }

    public function hasRequiredAddressForCheckout(): bool
    {
        return $this->missingRequiredAddressFieldsForCheckout() === [];
    }

    /**
     * @return list<string>
     */
    public function missingRequiredAddressFieldsForCheckout(): array
    {
        $required = [
            'cep' => trim((string) ($this->cep ?? '')),
            'street' => trim((string) ($this->street ?? '')),
            'neighborhood' => trim((string) ($this->neighborhood ?? '')),
            'city' => trim((string) ($this->city ?? '')),
            'state' => strtoupper(trim((string) ($this->state ?? ''))),
        ];

        $missing = [];

        foreach ($required as $field => $value) {
            if ($value === '') {
                $missing[] = $field;
            }
        }

        if ($required['cep'] !== '' && ! preg_match('/^\d{5}-\d{3}$/', $required['cep'])) {
            $missing[] = 'cep';
        }

        if ($required['state'] !== '' && ! in_array($required['state'], BrazilData::STATE_CODES, true)) {
            $missing[] = 'state';
        }

        return collect($missing)
            ->unique()
            ->values()
            ->all();
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_MASTER = 'master';

    public const ROLE_ADMIN = 'admin';

    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_MASTER,
            self::ROLE_ADMIN,
        ];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'cpf',
        'phone',
        'password',
        'role',
        'job_title',
        'address',
        'is_active',
        'password_changed_at',
        'preferences',
        'avatar_url',
        'two_factor_secret',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'address' => 'array',
            'preferences' => 'array',
            'is_active' => 'boolean',
            'password_changed_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function contractors(): BelongsToMany
    {
        return $this->belongsToMany(Contractor::class)->withTimestamps();
    }

    public function openedCashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class, 'opened_by_user_id');
    }

    public function closedCashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class, 'closed_by_user_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function defaultContractor(): ?Contractor
    {
        if (! $this->relationLoaded('contractors')) {
            $this->load('contractors');
        }

        return $this->contractors->first();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return (bool) ($this->two_factor_secret && $this->two_factor_confirmed_at);
    }

    public function isMaster(): bool
    {
        return $this->role === self::ROLE_MASTER;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}

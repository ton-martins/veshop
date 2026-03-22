<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'name',
        'email',
        'phone',
        'document',
        'cep',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function shopCustomers(): HasMany
    {
        return $this->hasMany(ShopCustomer::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function serviceAppointments(): HasMany
    {
        return $this->hasMany(ServiceAppointment::class);
    }

    public function accountingFeeEntries(): HasMany
    {
        return $this->hasMany(AccountingFeeEntry::class);
    }

    public function accountingObligations(): HasMany
    {
        return $this->hasMany(AccountingObligation::class);
    }

    public function accountingDocumentRequests(): HasMany
    {
        return $this->hasMany(AccountingDocumentRequest::class);
    }
}

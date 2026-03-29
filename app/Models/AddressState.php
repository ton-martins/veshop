<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddressState extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'ibge_code',
        'cities_synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cities_synced_at' => 'datetime',
        ];
    }

    public function cities(): HasMany
    {
        return $this->hasMany(AddressCity::class);
    }
}

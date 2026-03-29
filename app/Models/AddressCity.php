<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddressCity extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'address_state_id',
        'name',
        'normalized_name',
        'ibge_code',
    ];

    public function state(): BelongsTo
    {
        return $this->belongsTo(AddressState::class, 'address_state_id');
    }
}

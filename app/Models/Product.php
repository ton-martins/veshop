<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    public const UNITS = ['un', 'kg', 'lts'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'category_id',
        'name',
        'sku',
        'description',
        'cost_price',
        'sale_price',
        'stock_quantity',
        'unit',
        'image_url',
        'is_active',
        'is_pdv_active',
        'is_storefront_active',
        'is_pdv_featured',
        'pdv_featured_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
            'is_pdv_active' => 'boolean',
            'is_storefront_active' => 'boolean',
            'is_pdv_featured' => 'boolean',
            'pdv_featured_order' => 'integer',
        ];
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeVariations(): HasMany
    {
        return $this->variations()->where('is_active', true);
    }

    public function availableStockQuantity(): int
    {
        if ($this->relationLoaded('variations')) {
            $activeVariations = $this->variations->where('is_active', true);
            if ($activeVariations->isNotEmpty()) {
                return (int) $activeVariations->sum(static fn (ProductVariation $variation): int => (int) $variation->stock_quantity);
            }
        }

        return (int) $this->stock_quantity;
    }

    public function shopFavorites(): HasMany
    {
        return $this->hasMany(ShopCustomerFavorite::class);
    }
}

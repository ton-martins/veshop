<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Contractor;
use App\Models\Product;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PublicShopController extends Controller
{
    public function show(string $slug): Response
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);

        $products = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->with('category:id,name,slug')
            ->orderByDesc('is_pdv_featured')
            ->orderBy('pdv_featured_order')
            ->orderBy('name')
            ->get([
                'id',
                'category_id',
                'name',
                'sku',
                'description',
                'sale_price',
                'stock_quantity',
                'unit',
                'image_url',
            ]);

        $productsCountByCategory = $products
            ->groupBy('category_id')
            ->map(static fn (Collection $items): int => $items->count());

        $categories = Category::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(static function (Category $category) use ($productsCountByCategory): array {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'products_count' => (int) ($productsCountByCategory->get($category->id, 0)),
                ];
            })
            ->filter(static fn (array $category): bool => $category['products_count'] > 0)
            ->values()
            ->all();

        $productsPayload = $products
            ->map(fn (Product $product): array => $this->toProductPayload($product))
            ->values()
            ->all();

        return Inertia::render('Public/Shop', [
            'contractor' => $this->toContractorPayload($contractor),
            'categories' => $categories,
            'products' => $productsPayload,
        ]);
    }

    public function product(string $slug, int $product): Response
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);

        $selectedProduct = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('id', $product)
            ->where('is_active', true)
            ->with('category:id,name,slug')
            ->firstOrFail();

        $relatedProducts = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->where('id', '!=', $selectedProduct->id)
            ->where(function ($query) use ($selectedProduct): void {
                if ($selectedProduct->category_id) {
                    $query->where('category_id', $selectedProduct->category_id);
                } else {
                    $query->whereNull('category_id');
                }
            })
            ->orderByDesc('is_pdv_featured')
            ->orderBy('pdv_featured_order')
            ->orderBy('name')
            ->limit(8)
            ->get([
                'id',
                'category_id',
                'name',
                'sku',
                'description',
                'sale_price',
                'stock_quantity',
                'unit',
                'image_url',
            ])
            ->map(fn (Product $item): array => $this->toProductPayload($item))
            ->values()
            ->all();

        return Inertia::render('Public/ShopProduct', [
            'contractor' => $this->toContractorPayload($contractor),
            'product' => $this->toProductPayload($selectedProduct),
            'related_products' => $relatedProducts,
        ]);
    }

    private function resolveActiveContractorBySlug(string $slug): Contractor
    {
        return Contractor::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function toContractorPayload(Contractor $contractor): array
    {
        return [
            'id' => $contractor->id,
            'slug' => $contractor->slug,
            'name' => $contractor->name,
            'brand_name' => $contractor->brand_name,
            'phone' => $contractor->phone,
            'primary_color' => $contractor->brand_primary_color,
            'logo_url' => $this->normalizePublicAssetUrl($contractor->brand_logo_url),
            'avatar_url' => $this->normalizePublicAssetUrl($contractor->brand_avatar_url),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toProductPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->description,
            'sale_price' => round((float) $product->sale_price, 2),
            'stock_quantity' => (int) $product->stock_quantity,
            'unit' => $product->unit,
            'image_url' => $this->normalizePublicAssetUrl($product->image_url),
        ];
    }

    private function normalizePublicAssetUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);
        $normalized = is_string($path) && $path !== '' ? $path : $value;

        if (str_starts_with($normalized, '/storage/')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'storage/')) {
            return '/'.ltrim($normalized, '/');
        }

        return $value;
    }
}

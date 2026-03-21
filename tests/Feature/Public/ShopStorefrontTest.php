<?php

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\Contractor;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_shop_receives_configured_storefront_payload(): void
    {
        $contractor = $this->createContractor('loja-storefront');

        $productVisible = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Item visivel',
            'sku' => 'ITEM-001',
            'sale_price' => 49.90,
            'stock_quantity' => 10,
            'unit' => 'un',
            'is_active' => true,
        ]);

        Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Item sem estoque',
            'sku' => 'ITEM-002',
            'sale_price' => 89.90,
            'stock_quantity' => 0,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $contractor->settings = array_replace((array) $contractor->settings, [
            'shop_storefront' => [
                'template' => 'hibrido',
                'blocks' => [
                    'hero' => true,
                    'banners' => true,
                    'promotions' => true,
                    'categories' => true,
                    'catalog' => true,
                ],
                'hero' => [
                    'title' => 'Titulo personalizado',
                    'subtitle' => 'Subtitulo personalizado',
                    'cta_label' => 'Ver catalogo',
                ],
                'banners' => [
                    [
                        'title' => 'Banner de teste',
                        'subtitle' => 'Subtitulo banner',
                        'badge' => 'Oferta',
                        'image_url' => 'https://example.com/banner.jpg',
                        'cta_label' => 'Comprar agora',
                        'cta_url' => '#categorias',
                        'background_color' => '#112233',
                    ],
                ],
                'promotions' => [
                    'title' => 'Promocoes customizadas',
                    'subtitle' => 'Subtitulo promocoes',
                    'product_ids' => [$productVisible->id],
                ],
                'catalog' => [
                    'title' => 'Catalogo customizado',
                    'subtitle' => 'Subtitulo do catalogo',
                ],
            ],
        ]);
        $contractor->save();

        $response = $this->get(route('shop.show', ['slug' => $contractor->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Public/Shop')
            ->where('storefront.template', 'hibrido')
            ->where('storefront.hero.title', 'Titulo personalizado')
            ->where('storefront.promotions.title', 'Promocoes customizadas')
            ->where('storefront.promotions.product_ids.0', $productVisible->id)
        );
    }

    public function test_public_shop_uses_fallback_promotion_products_when_none_is_configured(): void
    {
        $contractor = $this->createContractor('loja-storefront-fallback');

        $firstProduct = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Primeiro item',
            'sku' => 'PRI-001',
            'sale_price' => 30,
            'stock_quantity' => 5,
            'unit' => 'un',
            'is_active' => true,
        ]);

        Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Segundo item',
            'sku' => 'SEG-001',
            'sale_price' => 45,
            'stock_quantity' => 9,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $contractor->settings = array_replace((array) $contractor->settings, [
            'shop_storefront' => [
                'template' => 'comercio',
                'promotions' => [
                    'title' => 'Promocoes',
                    'subtitle' => 'Destaques',
                    'product_ids' => [],
                ],
            ],
        ]);
        $contractor->save();

        $response = $this->get(route('shop.show', ['slug' => $contractor->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Public/Shop')
            ->where('storefront.promotions.product_ids.0', $firstProduct->id)
        );
    }

    public function test_public_shop_exposes_parent_and_subcategory_with_hierarchical_count(): void
    {
        $contractor = $this->createContractor('loja-subcategorias-vitrine');

        $parentCategory = Category::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Roupas',
            'slug' => 'roupas',
            'description' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $childCategory = Category::query()->create([
            'contractor_id' => $contractor->id,
            'parent_id' => $parentCategory->id,
            'name' => 'Camisetas',
            'slug' => 'camisetas',
            'description' => null,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Product::query()->create([
            'contractor_id' => $contractor->id,
            'category_id' => $childCategory->id,
            'name' => 'Camiseta Básica',
            'sku' => 'CAM-100',
            'sale_price' => 59.90,
            'stock_quantity' => 12,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $response = $this->get(route('shop.show', ['slug' => $contractor->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Public/Shop')
            ->where('categories', static function ($categories) use ($parentCategory, $childCategory): bool {
                $categoriesById = collect($categories)->keyBy(static fn (array $category): int => (int) $category['id']);
                $parent = $categoriesById->get((int) $parentCategory->id);
                $child = $categoriesById->get((int) $childCategory->id);

                if (! is_array($parent) || ! is_array($child)) {
                    return false;
                }

                return (int) ($parent['products_count'] ?? 0) === 1
                    && (int) ($child['products_count'] ?? 0) === 1
                    && (int) ($child['parent_id'] ?? 0) === (int) $parentCategory->id;
            })
        );
    }

    private function createContractor(string $slug): Contractor
    {
        return Contractor::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::title(str_replace('-', ' ', $slug)),
            'email' => "{$slug}@example.com",
            'slug' => $slug,
            'timezone' => 'America/Sao_Paulo',
            'brand_name' => Str::title(str_replace('-', ' ', $slug)),
            'brand_primary_color' => '#073341',
            'settings' => [
                'business_niche' => Contractor::NICHE_COMMERCIAL,
                'active_plan_name' => 'Pro',
            ],
            'is_active' => true,
        ]);
    }
}

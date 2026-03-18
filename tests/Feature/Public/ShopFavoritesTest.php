<?php

namespace Tests\Feature\Public;

use App\Models\Contractor;
use App\Models\Product;
use App\Models\ShopCustomer;
use App\Models\ShopCustomerFavorite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopFavoritesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_authenticated_shop_customer_can_favorite_and_unfavorite_product(): void
    {
        $contractor = $this->createContractor('loja-favoritos');

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Tênis Casual',
            'sku' => 'TEN-001',
            'sale_price' => 199.90,
            'stock_quantity' => 10,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $customer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Favorito',
            'email' => 'cliente-favorito@example.com',
            'phone' => '71999990002',
            'password' => 'Password@123',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $storeResponse = $this
            ->actingAs($customer, 'shop')
            ->post(route('shop.favorites.store', ['slug' => $contractor->slug, 'product' => $product->id]));

        $storeResponse
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'favorited' => true,
                'product_id' => $product->id,
            ]);

        $this->assertDatabaseHas('shop_customer_favorites', [
            'contractor_id' => $contractor->id,
            'shop_customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        $destroyResponse = $this
            ->actingAs($customer, 'shop')
            ->delete(route('shop.favorites.destroy', ['slug' => $contractor->slug, 'product' => $product->id]));

        $destroyResponse
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'favorited' => false,
                'product_id' => $product->id,
            ]);

        $this->assertDatabaseMissing('shop_customer_favorites', [
            'contractor_id' => $contractor->id,
            'shop_customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_shop_customer_cannot_favorite_product_from_another_store(): void
    {
        $contractorA = $this->createContractor('loja-a-favoritos');
        $contractorB = $this->createContractor('loja-b-favoritos');

        $productFromOtherStore = Product::query()->create([
            'contractor_id' => $contractorB->id,
            'name' => 'Produto Loja B',
            'sku' => 'B-001',
            'sale_price' => 89.90,
            'stock_quantity' => 6,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $customerA = ShopCustomer::query()->create([
            'contractor_id' => $contractorA->id,
            'name' => 'Cliente Loja A',
            'email' => 'cliente-loja-a@example.com',
            'phone' => '71999990003',
            'password' => 'Password@123',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($customerA, 'shop')
            ->post(route('shop.favorites.store', ['slug' => $contractorA->slug, 'product' => $productFromOtherStore->id]));

        $response->assertNotFound();
    }

    public function test_guest_is_redirected_to_shop_login_when_favoriting_product(): void
    {
        $contractor = $this->createContractor('loja-guest-favoritos');
        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Boné',
            'sku' => 'BON-001',
            'sale_price' => 59.90,
            'stock_quantity' => 8,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $response = $this->post(route('shop.favorites.store', [
            'slug' => $contractor->slug,
            'product' => $product->id,
        ]));

        $response->assertRedirect(route('shop.auth.login', ['slug' => $contractor->slug]));
    }

    public function test_shop_account_lists_favorite_products(): void
    {
        $contractor = $this->createContractor('loja-conta-favoritos');
        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Tênis Premium',
            'sku' => 'TEN-PRM',
            'sale_price' => 249.90,
            'stock_quantity' => 4,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $customer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Conta',
            'email' => 'cliente-conta@example.com',
            'phone' => '71999990004',
            'password' => 'Password@123',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        ShopCustomerFavorite::query()->create([
            'contractor_id' => $contractor->id,
            'shop_customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        $response = $this
            ->actingAs($customer, 'shop')
            ->get(route('shop.show', ['slug' => $contractor->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Public/Shop')
            ->where('shop_auth.favorite_product_ids.0', $product->id)
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
                'require_email_verification' => true,
            ],
            'is_active' => true,
        ]);
    }
}

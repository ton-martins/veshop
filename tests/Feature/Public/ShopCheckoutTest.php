<?php

namespace Tests\Feature\Public;

use App\Models\Contractor;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\ShopCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_authenticated_shop_customer_can_create_order_from_public_shop_checkout(): void
    {
        $contractor = $this->createContractor('loja-publica');

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Camiseta Premium',
            'sku' => 'CAM-001',
            'sale_price' => 79.90,
            'stock_quantity' => 12,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $paymentMethod = PaymentMethod::query()->create([
            'contractor_id' => $contractor->id,
            'payment_gateway_id' => null,
            'code' => PaymentMethod::CODE_PIX,
            'name' => 'Pix',
            'is_active' => true,
            'is_default' => true,
            'allows_installments' => false,
            'max_installments' => null,
            'fee_fixed' => null,
            'fee_percent' => null,
            'sort_order' => 10,
            'settings' => null,
        ]);

        $shopCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente da Loja',
            'email' => 'cliente-loja@example.com',
            'phone' => '71999990000',
            'cep' => '41810-000',
            'street' => 'Rua das Flores',
            'neighborhood' => 'Centro',
            'city' => 'Salvador',
            'state' => 'BA',
            'password' => '12345678',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($shopCustomer, 'shop')
            ->from(route('shop.show', ['slug' => $contractor->slug]))
            ->post(route('shop.checkout', ['slug' => $contractor->slug]), [
                'customer_name' => 'Cliente da Loja',
                'customer_phone' => '(71) 99999-0000',
                'customer_email' => 'cliente-loja@example.com',
                'payment_method_id' => $paymentMethod->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $response->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));

        $this->assertDatabaseHas('sales', [
            'contractor_id' => $contractor->id,
            'shop_customer_id' => $shopCustomer->id,
            'source' => Sale::SOURCE_CATALOG,
            'status' => Sale::STATUS_PENDING_CONFIRMATION,
            'subtotal_amount' => '159.80',
            'total_amount' => '159.80',
            'paid_amount' => '0.00',
        ]);

        $sale = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('source', Sale::SOURCE_CATALOG)
            ->firstOrFail();

        $this->assertDatabaseHas('sale_items', [
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'total_amount' => '159.80',
        ]);

        $this->assertDatabaseHas('sale_payments', [
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'payment_method_id' => $paymentMethod->id,
            'status' => 'pending',
            'amount' => '159.80',
        ]);

        $this->assertDatabaseHas('clients', [
            'contractor_id' => $contractor->id,
            'name' => 'Cliente da Loja',
            'email' => 'cliente-loja@example.com',
            'phone' => '71999990000',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 12,
        ]);
    }

    public function test_checkout_is_idempotent_when_same_key_is_sent_twice(): void
    {
        $contractor = $this->createContractor('loja-idempotencia');

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Moletom',
            'sku' => 'MOL-001',
            'sale_price' => 120.00,
            'stock_quantity' => 8,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $shopCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Idempotente',
            'email' => 'cliente-idempotente@example.com',
            'phone' => '71999990077',
            'cep' => '41810-000',
            'street' => 'Rua das Flores',
            'neighborhood' => 'Centro',
            'city' => 'Salvador',
            'state' => 'BA',
            'password' => '12345678',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $payload = [
            'customer_name' => 'Cliente Idempotente',
            'customer_phone' => '(71) 99999-0077',
            'customer_email' => 'cliente-idempotente@example.com',
            'idempotency_key' => 'checkout-loja-idempotencia-001',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ];

        $firstResponse = $this
            ->actingAs($shopCustomer, 'shop')
            ->from(route('shop.show', ['slug' => $contractor->slug]))
            ->post(route('shop.checkout', ['slug' => $contractor->slug]), $payload);

        $secondResponse = $this
            ->actingAs($shopCustomer, 'shop')
            ->from(route('shop.show', ['slug' => $contractor->slug]))
            ->post(route('shop.checkout', ['slug' => $contractor->slug]), $payload);

        $firstResponse->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));
        $secondResponse->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));

        $salesCount = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('source', Sale::SOURCE_CATALOG)
            ->where('checkout_idempotency_key', 'checkout-loja-idempotencia-001')
            ->count();

        $this->assertSame(1, $salesCount);
    }

    public function test_guest_is_redirected_to_shop_login_when_trying_checkout(): void
    {
        $contractor = $this->createContractor('loja-publica-login');

        $response = $this->post(route('shop.checkout', ['slug' => $contractor->slug]), [
            'customer_name' => 'Visitante',
            'customer_phone' => '71999990000',
            'items' => [],
        ]);

        $response->assertRedirect(route('shop.auth.login', ['slug' => $contractor->slug]));
    }

    public function test_unverified_shop_customer_is_redirected_to_email_verification_before_checkout(): void
    {
        $contractor = $this->createContractor('loja-publica-sem-verificacao');

        $shopCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Sem Verificar',
            'email' => 'cliente-sem-verificar@example.com',
            'phone' => '71999990099',
            'password' => '12345678',
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $response = $this
            ->actingAs($shopCustomer, 'shop')
            ->post(route('shop.checkout', ['slug' => $contractor->slug]), [
                'customer_name' => 'Cliente Sem Verificar',
                'customer_phone' => '(71) 99999-0099',
                'items' => [],
            ]);

        $response->assertRedirect(route('shop.verification.notice', ['slug' => $contractor->slug]));
    }

    public function test_checkout_with_delivery_applies_shipping_fee_and_address(): void
    {
        $contractor = $this->createContractor('loja-entrega');
        $contractor->settings = array_replace((array) $contractor->settings, [
            'shop_shipping' => [
                'pickup_enabled' => true,
                'delivery_enabled' => true,
                'fixed_fee' => 12.50,
                'free_over' => 200,
                'estimated_days' => 3,
            ],
        ]);
        $contractor->save();

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Mochila',
            'sku' => 'MOC-001',
            'sale_price' => 150.00,
            'stock_quantity' => 5,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $shopCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Entrega',
            'email' => 'cliente-entrega@example.com',
            'phone' => '71999990001',
            'cep' => '41810-000',
            'street' => 'Rua das Flores',
            'neighborhood' => 'Centro',
            'city' => 'Salvador',
            'state' => 'BA',
            'password' => '12345678',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($shopCustomer, 'shop')
            ->from(route('shop.show', ['slug' => $contractor->slug]))
            ->post(route('shop.checkout', ['slug' => $contractor->slug]), [
                'customer_name' => 'Cliente Entrega',
                'customer_phone' => '(71) 99999-0001',
                'customer_email' => 'cliente-entrega@example.com',
                'delivery_mode' => 'delivery',
                'shipping_postal_code' => '41810-000',
                'shipping_street' => 'Rua das Flores',
                'shipping_number' => '123',
                'shipping_complement' => 'Apto 12',
                'shipping_district' => 'Centro',
                'shipping_city' => 'Salvador',
                'shipping_state' => 'BA',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));

        $this->assertDatabaseHas('sales', [
            'contractor_id' => $contractor->id,
            'shop_customer_id' => $shopCustomer->id,
            'shipping_mode' => 'delivery',
            'shipping_amount' => '12.50',
            'surcharge_amount' => '12.50',
            'total_amount' => '162.50',
        ]);
    }

    public function test_checkout_requires_shop_customer_profile_address(): void
    {
        $contractor = $this->createContractor('loja-sem-endereco-cliente');

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Caneca',
            'sku' => 'CAN-001',
            'sale_price' => 29.90,
            'stock_quantity' => 20,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $shopCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Sem Endereco',
            'email' => 'cliente-sem-endereco@example.com',
            'phone' => '71999990111',
            'password' => '12345678',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($shopCustomer, 'shop')
            ->from(route('shop.show', ['slug' => $contractor->slug]))
            ->post(route('shop.checkout', ['slug' => $contractor->slug]), [
                'customer_name' => 'Cliente Sem Endereco',
                'customer_phone' => '(71) 99999-0111',
                'customer_email' => 'cliente-sem-endereco@example.com',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));
        $response->assertSessionHasErrors('order');

        $this->assertDatabaseMissing('sales', [
            'contractor_id' => $contractor->id,
            'shop_customer_id' => $shopCustomer->id,
            'source' => Sale::SOURCE_CATALOG,
        ]);
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

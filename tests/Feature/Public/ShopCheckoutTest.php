<?php

namespace Tests\Feature\Public;

use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\ShopCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
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

    public function test_checkout_pix_with_mercado_pago_creates_payment_intent_and_updates_sale_status(): void
    {
        $contractor = $this->createContractor('loja-mp');

        $gateway = PaymentGateway::query()->create([
            'contractor_id' => $contractor->id,
            'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            'name' => 'Mercado Pago',
            'is_active' => true,
            'is_default' => true,
            'is_sandbox' => true,
            'credentials' => [
                'access_token' => 'APP_USR_TEST_TOKEN',
                'webhook_secret' => 'mp-webhook-token',
            ],
            'settings' => null,
        ]);

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Combo MP',
            'sku' => 'MP-001',
            'sale_price' => 99.90,
            'stock_quantity' => 10,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $paymentMethod = PaymentMethod::query()->create([
            'contractor_id' => $contractor->id,
            'payment_gateway_id' => $gateway->id,
            'code' => PaymentMethod::CODE_PIX,
            'name' => 'Pix Mercado Pago',
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
            'name' => 'Cliente MP',
            'email' => 'cliente-mp@example.com',
            'phone' => '71999990123',
            'cep' => '41810-000',
            'street' => 'Rua Pix',
            'neighborhood' => 'Centro',
            'city' => 'Salvador',
            'state' => 'BA',
            'password' => '12345678',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Http::fake([
            'https://api.mercadopago.com/v1/payments' => Http::response([
                'id' => 'TX-MP-001',
                'status' => 'pending',
                'external_reference' => 'PED-MP',
                'date_of_expiration' => now()->addMinutes(30)->toIso8601String(),
                'point_of_interaction' => [
                    'transaction_data' => [
                        'qr_code' => '0002010102...',
                        'qr_code_base64' => 'RkFLRV9RUl9CQVNFMjY0',
                        'ticket_url' => 'https://www.mercadopago.com.br/payments/qr/TX-MP-001',
                    ],
                ],
            ], 201),
        ]);

        $response = $this
            ->actingAs($shopCustomer, 'shop')
            ->from(route('shop.show', ['slug' => $contractor->slug]))
            ->post(route('shop.checkout', ['slug' => $contractor->slug]), [
                'customer_name' => 'Cliente MP',
                'customer_phone' => '(71) 99999-0123',
                'customer_email' => 'cliente-mp@example.com',
                'payment_method_id' => $paymentMethod->id,
                'idempotency_key' => 'checkout-mp-001',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));
        $response->assertSessionHas('checkout_payment');

        $checkoutPayment = $response->getSession()->get('checkout_payment');
        $this->assertSame('pix', data_get($checkoutPayment, 'payment_method_code'));
        $this->assertSame('TX-MP-001', data_get($checkoutPayment, 'transaction_reference'));
        $this->assertSame('0002010102...', data_get($checkoutPayment, 'qr_code'));
        $this->assertSame('Aguardando pagamento', data_get($checkoutPayment, 'payment_status_label'));

        Http::assertSent(static function (\Illuminate\Http\Client\Request $request): bool {
            return str_contains($request->url(), '/v1/payments')
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization')
                && $request['payment_method_id'] === 'pix';
        });

        $sale = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('source', Sale::SOURCE_CATALOG)
            ->firstOrFail();

        $payment = \App\Models\SalePayment::query()
            ->where('contractor_id', $contractor->id)
            ->where('sale_id', $sale->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(Sale::STATUS_AWAITING_PAYMENT, (string) $sale->status);
        $this->assertSame('TX-MP-001', (string) $payment->transaction_reference);
        $this->assertSame(\App\Models\SalePayment::STATUS_PENDING, (string) $payment->status);
        $this->assertIsArray($payment->gateway_payload);
        $this->assertSame('TX-MP-001', data_get($payment->gateway_payload, 'payment_intent.transaction_reference'));
    }

    public function test_authenticated_shop_customer_can_consult_pix_payment_status_for_own_order(): void
    {
        $contractor = $this->createContractor('loja-consulta-pix');

        $gateway = PaymentGateway::query()->create([
            'contractor_id' => $contractor->id,
            'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            'name' => 'Mercado Pago',
            'is_active' => true,
            'is_default' => true,
            'is_sandbox' => true,
            'credentials' => [
                'access_token' => 'APP_USR_TEST_TOKEN',
                'webhook_secret' => 'mp-webhook-token',
            ],
            'settings' => null,
        ]);

        $paymentMethod = PaymentMethod::query()->create([
            'contractor_id' => $contractor->id,
            'payment_gateway_id' => $gateway->id,
            'code' => PaymentMethod::CODE_PIX,
            'name' => 'Pix Mercado Pago',
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
            'name' => 'Cliente Consulta Pix',
            'email' => 'cliente-consulta-pix@example.com',
            'phone' => '71999990111',
            'cep' => '41810-000',
            'street' => 'Rua Pix',
            'neighborhood' => 'Centro',
            'city' => 'Salvador',
            'state' => 'BA',
            'password' => '12345678',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $sale = Sale::query()->create([
            'contractor_id' => $contractor->id,
            'shop_customer_id' => $shopCustomer->id,
            'code' => 'PED-CONSULTA-001',
            'source' => Sale::SOURCE_CATALOG,
            'status' => Sale::STATUS_AWAITING_PAYMENT,
            'subtotal_amount' => 99.90,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'total_amount' => 99.90,
            'paid_amount' => 0,
            'change_amount' => 0,
            'metadata' => [
                'payment_intent' => [
                    'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                    'status' => 'pending',
                    'transaction_reference' => 'TX-CONSULTA-001',
                    'ticket_url' => 'https://www.mercadopago.com.br/payments/qr/TX-CONSULTA-001',
                    'qr_code' => '0002010102CONSULTA',
                    'qr_code_base64' => 'RkFLRV9DT05TVUxUQQ==',
                    'date_of_expiration' => now()->addMinutes(30)->toIso8601String(),
                ],
            ],
        ]);

        SalePayment::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_gateway_id' => $gateway->id,
            'status' => SalePayment::STATUS_PENDING,
            'amount' => 99.90,
            'transaction_reference' => 'TX-CONSULTA-001',
            'gateway_payload' => [
                'payment_intent' => [
                    'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                    'status' => 'pending',
                    'transaction_reference' => 'TX-CONSULTA-001',
                    'ticket_url' => 'https://www.mercadopago.com.br/payments/qr/TX-CONSULTA-001',
                    'qr_code' => '0002010102CONSULTA',
                    'qr_code_base64' => 'RkFLRV9DT05TVUxUQQ==',
                    'date_of_expiration' => now()->addMinutes(30)->toIso8601String(),
                ],
            ],
            'metadata' => [
                'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            ],
        ]);

        $response = $this
            ->actingAs($shopCustomer, 'shop')
            ->getJson(route('shop.checkout.payment.status', [
                'slug' => $contractor->slug,
                'sale' => $sale->id,
            ]));

        $response
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'payment' => [
                    'sale_id' => $sale->id,
                    'sale_code' => 'PED-CONSULTA-001',
                    'payment_method_code' => 'pix',
                    'transaction_reference' => 'TX-CONSULTA-001',
                    'qr_code' => '0002010102CONSULTA',
                ],
            ]);
    }

    public function test_shop_customer_cannot_consult_pix_payment_status_from_other_customer_order(): void
    {
        $contractor = $this->createContractor('loja-consulta-restrita');

        $gateway = PaymentGateway::query()->create([
            'contractor_id' => $contractor->id,
            'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            'name' => 'Mercado Pago',
            'is_active' => true,
            'is_default' => true,
            'is_sandbox' => true,
            'credentials' => [
                'access_token' => 'APP_USR_TEST_TOKEN',
                'webhook_secret' => 'mp-webhook-token',
            ],
            'settings' => null,
        ]);

        $paymentMethod = PaymentMethod::query()->create([
            'contractor_id' => $contractor->id,
            'payment_gateway_id' => $gateway->id,
            'code' => PaymentMethod::CODE_PIX,
            'name' => 'Pix Mercado Pago',
            'is_active' => true,
            'is_default' => true,
            'allows_installments' => false,
            'max_installments' => null,
            'fee_fixed' => null,
            'fee_percent' => null,
            'sort_order' => 10,
            'settings' => null,
        ]);

        $ownerCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Dono',
            'email' => 'cliente-dono@example.com',
            'phone' => '71999990121',
            'cep' => '41810-000',
            'street' => 'Rua Pix',
            'neighborhood' => 'Centro',
            'city' => 'Salvador',
            'state' => 'BA',
            'password' => '12345678',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $otherCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Sem Acesso',
            'email' => 'cliente-sem-acesso@example.com',
            'phone' => '71999990122',
            'cep' => '41810-000',
            'street' => 'Rua Pix',
            'neighborhood' => 'Centro',
            'city' => 'Salvador',
            'state' => 'BA',
            'password' => '12345678',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $sale = Sale::query()->create([
            'contractor_id' => $contractor->id,
            'shop_customer_id' => $ownerCustomer->id,
            'code' => 'PED-RESTRITO-001',
            'source' => Sale::SOURCE_CATALOG,
            'status' => Sale::STATUS_AWAITING_PAYMENT,
            'subtotal_amount' => 110,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'total_amount' => 110,
            'paid_amount' => 0,
            'change_amount' => 0,
        ]);

        SalePayment::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_gateway_id' => $gateway->id,
            'status' => SalePayment::STATUS_PENDING,
            'amount' => 110,
            'transaction_reference' => 'TX-RESTRITO-001',
            'gateway_payload' => [
                'payment_intent' => [
                    'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                    'status' => 'pending',
                    'transaction_reference' => 'TX-RESTRITO-001',
                    'qr_code' => '0002010102RESTRITO',
                ],
            ],
            'metadata' => [
                'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            ],
        ]);

        $response = $this
            ->actingAs($otherCustomer, 'shop')
            ->getJson(route('shop.checkout.payment.status', [
                'slug' => $contractor->slug,
                'sale' => $sale->id,
            ]));

        $response->assertNotFound();
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

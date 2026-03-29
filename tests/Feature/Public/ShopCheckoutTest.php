<?php

namespace Tests\Feature\Public;

use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
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
            'stock_quantity' => 10,
        ]);
    }

    public function test_checkout_with_product_variation_creates_sale_item_with_variation_snapshot(): void
    {
        $contractor = $this->createContractor('loja-variacao-checkout');

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Camiseta Dry Fit',
            'sku' => 'CAM-DRY',
            'sale_price' => 59.90,
            'stock_quantity' => 7,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $variation = ProductVariation::query()->create([
            'contractor_id' => $contractor->id,
            'product_id' => $product->id,
            'name' => 'Azul - M',
            'sku' => 'CAM-DRY-AZ-M',
            'sale_price' => 64.90,
            'stock_quantity' => 5,
            'is_active' => true,
            'sort_order' => 0,
            'attributes' => [
                'cor' => 'Azul',
                'tamanho' => 'M',
            ],
        ]);

        $shopCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Variação',
            'email' => 'cliente-variacao@example.com',
            'phone' => '71999990033',
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
                'customer_name' => 'Cliente Variação',
                'customer_phone' => '(71) 99999-0033',
                'customer_email' => 'cliente-variacao@example.com',
                'items' => [
                    ['product_id' => $product->id, 'variation_id' => $variation->id, 'quantity' => 2],
                ],
            ]);

        $response->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));

        $sale = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('shop_customer_id', $shopCustomer->id)
            ->where('source', Sale::SOURCE_CATALOG)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(129.80, (float) $sale->subtotal_amount);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_variation_id' => $variation->id,
            'quantity' => 2,
            'unit_price' => '64.90',
            'total_amount' => '129.80',
        ]);
    }

    public function test_checkout_applies_payment_fee_and_returns_manual_whatsapp_cta_when_no_gateway_is_active(): void
    {
        $contractor = $this->createContractor('loja-taxa-manual');
        $contractor->phone = '(71) 99999-8888';
        $contractor->save();

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Tênis Casual',
            'sku' => 'TEN-100',
            'sale_price' => 100.00,
            'stock_quantity' => 10,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $paymentMethod = PaymentMethod::query()->create([
            'contractor_id' => $contractor->id,
            'payment_gateway_id' => null,
            'code' => PaymentMethod::CODE_CREDIT_CARD,
            'name' => 'Cartão de crédito',
            'is_active' => true,
            'is_default' => true,
            'allows_installments' => true,
            'max_installments' => 6,
            'fee_fixed' => 2.00,
            'fee_percent' => 3.00,
            'sort_order' => 10,
            'settings' => null,
        ]);

        $shopCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Taxa',
            'email' => 'cliente-taxa@example.com',
            'phone' => '71999990055',
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
                'customer_name' => 'Cliente Taxa',
                'customer_phone' => '(71) 99999-0055',
                'customer_email' => 'cliente-taxa@example.com',
                'payment_method_id' => $paymentMethod->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));
        $response->assertSessionHas('checkout_manual');

        $manualPayload = $response->getSession()->get('checkout_manual');
        $this->assertNotEmpty((string) data_get($manualPayload, 'whatsapp_url'));
        $this->assertStringContainsString('https://wa.me/5571999998888', (string) data_get($manualPayload, 'whatsapp_url'));

        $sale = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('source', Sale::SOURCE_CATALOG)
            ->firstOrFail();

        $this->assertSame(100.00, (float) $sale->subtotal_amount);
        $this->assertSame(0.00, (float) $sale->shipping_amount);
        $this->assertSame(5.00, (float) $sale->surcharge_amount);
        $this->assertSame(105.00, (float) $sale->total_amount);
        $this->assertSame(5.00, (float) data_get($sale->metadata, 'charges.payment_fee_amount'));
        $this->assertSame(2.00, (float) data_get($sale->metadata, 'charges.payment_fee_fixed'));
        $this->assertSame(3.00, (float) data_get($sale->metadata, 'charges.payment_fee_percent'));
        $this->assertSame('manual', (string) data_get($sale->metadata, 'checkout_mode'));

        $payment = SalePayment::query()
            ->where('contractor_id', $contractor->id)
            ->where('sale_id', $sale->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(105.00, (float) $payment->amount);
        $this->assertSame('manual', (string) data_get($payment->metadata, 'checkout_mode'));
        $this->assertSame(5.00, (float) data_get($payment->metadata, 'fee_snapshot.fee_amount'));
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

    public function test_checkout_pix_enriches_qr_payload_when_create_response_has_no_qr_data(): void
    {
        $contractor = $this->createContractor('loja-mp-enrich');

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
            'name' => 'Produto Pix Enriquecido',
            'sku' => 'MP-ENRICH-001',
            'sale_price' => 59.90,
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
            'name' => 'Cliente MP Enrich',
            'email' => 'cliente-mp-enrich@example.com',
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
                'id' => 'TX-MP-ENRICH-001',
                'status' => 'pending',
                'external_reference' => 'PED-MP-ENRICH',
                'date_of_expiration' => now()->addMinutes(30)->toIso8601String(),
            ], 201),
            'https://api.mercadopago.com/v1/payments/TX-MP-ENRICH-001' => Http::response([
                'id' => 'TX-MP-ENRICH-001',
                'status' => 'pending',
                'external_reference' => 'PED-MP-ENRICH',
                'date_of_expiration' => now()->addMinutes(30)->toIso8601String(),
                'point_of_interaction' => [
                    'transaction_data' => [
                        'qr_code' => '0002010102ENRICH...',
                        'ticket_url' => 'https://www.mercadopago.com.br/payments/qr/TX-MP-ENRICH-001',
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->actingAs($shopCustomer, 'shop')
            ->from(route('shop.show', ['slug' => $contractor->slug]))
            ->post(route('shop.checkout', ['slug' => $contractor->slug]), [
                'customer_name' => 'Cliente MP Enrich',
                'customer_phone' => '(71) 99999-0123',
                'customer_email' => 'cliente-mp-enrich@example.com',
                'payment_method_id' => $paymentMethod->id,
                'idempotency_key' => 'checkout-mp-enrich-001',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));
        $response->assertSessionHas('checkout_payment');

        $checkoutPayment = $response->getSession()->get('checkout_payment');
        $this->assertSame('TX-MP-ENRICH-001', data_get($checkoutPayment, 'transaction_reference'));
        $this->assertSame('0002010102ENRICH...', data_get($checkoutPayment, 'qr_code'));

        Http::assertSent(static function (\Illuminate\Http\Client\Request $request): bool {
            return str_contains($request->url(), '/v1/payments/TX-MP-ENRICH-001')
                && $request->method() === 'GET';
        });
    }

    public function test_checkout_pix_intent_is_created_for_legacy_pix_method_codes(): void
    {
        $contractor = $this->createContractor('loja-mp-legado');

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
            'name' => 'Produto Pix Legado',
            'sku' => 'MP-LEGADO-001',
            'sale_price' => 69.90,
            'stock_quantity' => 10,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $paymentMethod = PaymentMethod::query()->create([
            'contractor_id' => $contractor->id,
            'payment_gateway_id' => $gateway->id,
            'code' => 'pix_mercadopago_legado',
            'name' => 'Pix Mercado Pago Legado',
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
            'name' => 'Cliente MP Legado',
            'email' => 'cliente-mp-legado@example.com',
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
                'id' => 'TX-MP-LEGADO-001',
                'status' => 'pending',
                'external_reference' => 'PED-MP-LEGADO',
                'date_of_expiration' => now()->addMinutes(30)->toIso8601String(),
                'point_of_interaction' => [
                    'transaction_data' => [
                        'qr_code' => '0002010102LEGADO...',
                        'ticket_url' => 'https://www.mercadopago.com.br/payments/qr/TX-MP-LEGADO-001',
                    ],
                ],
            ], 201),
        ]);

        $response = $this
            ->actingAs($shopCustomer, 'shop')
            ->from(route('shop.show', ['slug' => $contractor->slug]))
            ->post(route('shop.checkout', ['slug' => $contractor->slug]), [
                'customer_name' => 'Cliente MP Legado',
                'customer_phone' => '(71) 99999-0123',
                'customer_email' => 'cliente-mp-legado@example.com',
                'payment_method_id' => $paymentMethod->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));
        $response->assertSessionHas('checkout_payment');

        $checkoutPayment = $response->getSession()->get('checkout_payment');
        $this->assertSame('0002010102LEGADO...', data_get($checkoutPayment, 'qr_code'));
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
                'nationwide_enabled' => true,
                'nationwide_fee' => 12.50,
                'nationwide_free_over' => 200,
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
                'delivery_mode' => 'delivery',
                'shipping_postal_code' => '41810-000',
                'shipping_street' => 'Rua das Flores',
                'shipping_number' => '123',
                'shipping_complement' => '',
                'shipping_district' => 'Centro',
                'shipping_city' => 'Salvador',
                'shipping_state' => 'BA',
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

    public function test_checkout_with_statewide_shipping_blocks_other_states(): void
    {
        $contractor = $this->createContractor('loja-frete-estadual');
        $contractor->settings = array_replace((array) $contractor->settings, [
            'shop_shipping' => [
                'pickup_enabled' => false,
                'delivery_enabled' => true,
                'statewide_enabled' => true,
                'statewide_state' => 'BA',
                'statewide_fee' => 10,
                'statewide_free_over' => 0,
                'estimated_days' => 2,
            ],
        ]);
        $contractor->save();

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Produto Estadual',
            'sku' => 'EST-BA-001',
            'sale_price' => 80.00,
            'stock_quantity' => 10,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $shopCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Estadual',
            'email' => 'cliente-estadual@example.com',
            'phone' => '71999990031',
            'cep' => '41810-000',
            'street' => 'Rua das Flores',
            'number' => '123',
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
                'customer_name' => 'Cliente Estadual',
                'customer_phone' => '(71) 99999-0031',
                'customer_email' => 'cliente-estadual@example.com',
                'delivery_mode' => 'delivery',
                'shipping_postal_code' => '01001-000',
                'shipping_street' => 'Praça da Sé',
                'shipping_number' => '1',
                'shipping_complement' => '',
                'shipping_district' => 'Sé',
                'shipping_city' => 'São Paulo',
                'shipping_state' => 'SP',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));
        $response->assertSessionHasErrors('delivery_mode');

        $this->assertDatabaseMissing('sales', [
            'contractor_id' => $contractor->id,
            'shop_customer_id' => $shopCustomer->id,
            'source' => Sale::SOURCE_CATALOG,
        ]);
    }

    public function test_checkout_with_city_shipping_free_flag_sets_zero_shipping_amount(): void
    {
        $contractor = $this->createContractor('loja-frete-cidade-gratis');
        $contractor->settings = array_replace((array) $contractor->settings, [
            'shop_shipping' => [
                'pickup_enabled' => true,
                'delivery_enabled' => true,
                'estimated_days' => 2,
                'city_rates' => [
                    [
                        'city' => 'Salvador',
                        'state' => 'BA',
                        'fee' => 12.50,
                        'free_over' => 120,
                        'estimated_days' => 2,
                        'active' => true,
                        'is_free' => true,
                    ],
                ],
            ],
        ]);
        $contractor->save();

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Produto Frete Grátis Cidade',
            'sku' => 'CIT-001',
            'sale_price' => 70.00,
            'stock_quantity' => 10,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $shopCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Cidade',
            'email' => 'cliente-cidade@example.com',
            'phone' => '71999990032',
            'cep' => '41810-000',
            'street' => 'Rua das Flores',
            'number' => '123',
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
                'customer_name' => 'Cliente Cidade',
                'customer_phone' => '(71) 99999-0032',
                'customer_email' => 'cliente-cidade@example.com',
                'delivery_mode' => 'delivery',
                'shipping_postal_code' => '41810-000',
                'shipping_street' => 'Rua das Flores',
                'shipping_number' => '123',
                'shipping_complement' => '',
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
            'shipping_amount' => '0.00',
            'surcharge_amount' => '0.00',
            'total_amount' => '70.00',
        ]);
    }

    public function test_checkout_is_blocked_when_store_is_offline(): void
    {
        $contractor = $this->createContractor('loja-offline-checkout');
        $contractor->settings = array_replace((array) $contractor->settings, [
            'shop_storefront' => [
                'store_online' => false,
                'offline_message' => 'Loja em manutenção.',
            ],
        ]);
        $contractor->save();

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Caderno',
            'sku' => 'CAD-001',
            'sale_price' => 19.90,
            'stock_quantity' => 20,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $shopCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Offline',
            'email' => 'cliente-offline@example.com',
            'phone' => '71999990221',
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
                'customer_name' => 'Cliente Offline',
                'customer_phone' => '(71) 99999-0221',
                'customer_email' => 'cliente-offline@example.com',
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

    public function test_checkout_is_blocked_when_store_is_closed_by_business_hours(): void
    {
        $contractor = $this->createContractor('loja-fechada-checkout');
        $contractor->settings = array_replace((array) $contractor->settings, [
            'shop_storefront' => [
                'store_online' => true,
                'business_hours' => $this->closedBusinessHours(),
            ],
        ]);
        $contractor->save();

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Agenda',
            'sku' => 'AGD-001',
            'sale_price' => 24.90,
            'stock_quantity' => 20,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $shopCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Horário',
            'email' => 'cliente-horario@example.com',
            'phone' => '71999990222',
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
                'customer_name' => 'Cliente Horário',
                'customer_phone' => '(71) 99999-0222',
                'customer_email' => 'cliente-horario@example.com',
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

    public function test_checkout_rejects_quantity_above_available_stock(): void
    {
        $contractor = $this->createContractor('loja-estoque-limite');

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Produto com Estoque Curto',
            'sku' => 'EST-001',
            'sale_price' => 50.00,
            'stock_quantity' => 2,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $shopCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Estoque',
            'email' => 'cliente-estoque@example.com',
            'phone' => '71999990223',
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
                'customer_name' => 'Cliente Estoque',
                'customer_phone' => '(71) 99999-0223',
                'customer_email' => 'cliente-estoque@example.com',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3],
                ],
            ]);

        $response->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));
        $response->assertSessionHasErrors('items');

        $this->assertDatabaseMissing('sales', [
            'contractor_id' => $contractor->id,
            'shop_customer_id' => $shopCustomer->id,
            'source' => Sale::SOURCE_CATALOG,
        ]);
        $this->assertSame(2, (int) Product::query()->findOrFail($product->id)->stock_quantity);
    }

    public function test_checkout_payment_status_keeps_existing_pix_qr_when_provider_response_has_no_qr(): void
    {
        $contractor = $this->createContractor('loja-qr-persistente');

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
            'name' => 'Cliente QR Persistente',
            'email' => 'cliente-qr-persistente@example.com',
            'phone' => '71999990224',
            'password' => '12345678',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $sale = Sale::query()->create([
            'contractor_id' => $contractor->id,
            'shop_customer_id' => $shopCustomer->id,
            'code' => 'PED-QR-PERSIST-001',
            'source' => Sale::SOURCE_CATALOG,
            'status' => Sale::STATUS_AWAITING_PAYMENT,
            'subtotal_amount' => 89.90,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'total_amount' => 89.90,
            'paid_amount' => 0,
            'change_amount' => 0,
            'metadata' => [
                'stock_reduced' => true,
                'stock_restored' => false,
                'stock_reservation' => true,
                'stock_reservation_expires_at' => now()->addMinutes(5)->toIso8601String(),
                'payment_intent' => [
                    'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                    'status' => 'pending',
                    'transaction_reference' => 'TX-QR-PERSIST-001',
                    'ticket_url' => 'https://www.mercadopago.com.br/payments/qr/TX-QR-PERSIST-001',
                    'qr_code' => '000201PERSISTENTE',
                    'qr_code_base64' => 'RkFLRV9RUl9QRVJTSVNURU5URQ==',
                    'payment_method_code' => 'pix',
                    'date_of_expiration' => now()->addMinutes(5)->toIso8601String(),
                ],
            ],
        ]);

        SalePayment::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_gateway_id' => $gateway->id,
            'status' => SalePayment::STATUS_PENDING,
            'amount' => 89.90,
            'transaction_reference' => 'TX-QR-PERSIST-001',
            'gateway_payload' => [
                'payment_intent' => [
                    'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                    'status' => 'pending',
                    'transaction_reference' => 'TX-QR-PERSIST-001',
                    'ticket_url' => 'https://www.mercadopago.com.br/payments/qr/TX-QR-PERSIST-001',
                    'qr_code' => '000201PERSISTENTE',
                    'qr_code_base64' => 'RkFLRV9RUl9QRVJTSVNURU5URQ==',
                    'payment_method_code' => 'pix',
                    'date_of_expiration' => now()->addMinutes(5)->toIso8601String(),
                ],
            ],
            'metadata' => [
                'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            ],
        ]);

        Http::fake([
            'https://api.mercadopago.com/v1/payments/TX-QR-PERSIST-001' => Http::response([
                'id' => 'TX-QR-PERSIST-001',
                'status' => 'pending',
                'external_reference' => 'PED-QR-PERSIST-001',
            ], 200),
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
                    'payment_method_code' => 'pix',
                    'transaction_reference' => 'TX-QR-PERSIST-001',
                    'qr_code' => '000201PERSISTENTE',
                ],
            ]);

        $updatedPayment = SalePayment::query()
            ->where('sale_id', $sale->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('000201PERSISTENTE', (string) data_get($updatedPayment->gateway_payload, 'payment_intent.qr_code'));
    }

    public function test_expired_stock_reservation_cancels_sale_and_restores_stock(): void
    {
        $contractor = $this->createContractor('loja-reserva-expirada');

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Produto Reservado',
            'sku' => 'RES-001',
            'sale_price' => 25.00,
            'stock_quantity' => 3,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $shopCustomer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Reserva',
            'email' => 'cliente-reserva@example.com',
            'phone' => '71999990225',
            'cep' => '41810-000',
            'street' => 'Rua das Flores',
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
            'code' => 'PED-RESERVA-001',
            'source' => Sale::SOURCE_CATALOG,
            'status' => Sale::STATUS_AWAITING_PAYMENT,
            'subtotal_amount' => 50.00,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'total_amount' => 50.00,
            'paid_amount' => 0,
            'change_amount' => 0,
            'metadata' => [
                'stock_reduced' => true,
                'stock_restored' => false,
                'stock_reservation' => true,
                'stock_reservation_expires_at' => now()->subMinutes(2)->toIso8601String(),
                'stock_reservation_timeout_minutes' => 5,
            ],
        ]);

        SaleItem::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_variation_id' => null,
            'description' => 'Produto Reservado',
            'sku' => 'RES-001',
            'quantity' => 2,
            'unit_price' => 25.00,
            'discount_amount' => 0,
            'total_amount' => 50.00,
            'metadata' => null,
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

        SalePayment::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_gateway_id' => null,
            'status' => SalePayment::STATUS_PENDING,
            'amount' => 50.00,
            'transaction_reference' => 'TX-RESERVA-001',
            'gateway_payload' => null,
            'metadata' => null,
        ]);

        $response = $this
            ->actingAs($shopCustomer, 'shop')
            ->get(route('shop.show', ['slug' => $contractor->slug]));

        $response->assertOk();

        $updatedSale = Sale::query()->findOrFail($sale->id);
        $updatedPayment = SalePayment::query()
            ->where('sale_id', $sale->id)
            ->latest('id')
            ->firstOrFail();
        $updatedProduct = Product::query()->findOrFail($product->id);

        $this->assertSame(Sale::STATUS_CANCELLED, (string) $updatedSale->status);
        $this->assertTrue((bool) data_get($updatedSale->metadata, 'stock_restored'));
        $this->assertTrue((bool) data_get($updatedSale->metadata, 'stock_reservation_expired'));
        $this->assertSame(SalePayment::STATUS_CANCELLED, (string) $updatedPayment->status);
        $this->assertSame(5, (int) $updatedProduct->stock_quantity);
    }

    /**
     * @return array<string, array{enabled: bool, open: string, close: string}>
     */
    private function closedBusinessHours(): array
    {
        return [
            'monday' => ['enabled' => false, 'open' => '09:00', 'close' => '18:00'],
            'tuesday' => ['enabled' => false, 'open' => '09:00', 'close' => '18:00'],
            'wednesday' => ['enabled' => false, 'open' => '09:00', 'close' => '18:00'],
            'thursday' => ['enabled' => false, 'open' => '09:00', 'close' => '18:00'],
            'friday' => ['enabled' => false, 'open' => '09:00', 'close' => '18:00'],
            'saturday' => ['enabled' => false, 'open' => '09:00', 'close' => '18:00'],
            'sunday' => ['enabled' => false, 'open' => '09:00', 'close' => '18:00'],
        ];
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

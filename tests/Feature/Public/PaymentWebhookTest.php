<?php

namespace Tests\Feature\Public;

use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_updates_payment_and_sale_status(): void
    {
        $contractor = $this->createContractor('loja-webhook');

        $gateway = PaymentGateway::query()->create([
            'contractor_id' => $contractor->id,
            'provider' => PaymentGateway::PROVIDER_MANUAL,
            'name' => 'Gateway Teste',
            'is_active' => true,
            'is_default' => true,
            'is_sandbox' => true,
            'credentials' => ['webhook_secret' => 'segredo-webhook'],
            'settings' => null,
        ]);

        $method = PaymentMethod::query()->create([
            'contractor_id' => $contractor->id,
            'payment_gateway_id' => $gateway->id,
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

        $sale = Sale::query()->create([
            'contractor_id' => $contractor->id,
            'code' => 'PED-WEBHOOK-001',
            'source' => Sale::SOURCE_CATALOG,
            'status' => Sale::STATUS_AWAITING_PAYMENT,
            'subtotal_amount' => 100,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'total_amount' => 100,
            'paid_amount' => 0,
            'change_amount' => 0,
        ]);

        $payment = SalePayment::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'payment_method_id' => $method->id,
            'payment_gateway_id' => $gateway->id,
            'status' => SalePayment::STATUS_PENDING,
            'amount' => 100,
            'transaction_reference' => 'TX-12345',
        ]);

        $response = $this
            ->withHeader('X-Webhook-Secret', 'segredo-webhook')
            ->postJson(route('shop.payments.webhook', [
                'slug' => $contractor->slug,
                'provider' => PaymentGateway::PROVIDER_MANUAL,
            ]), [
                'transaction_reference' => 'TX-12345',
                'status' => 'paid',
            ]);

        $response
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('sale_payments', [
            'id' => $payment->id,
            'status' => SalePayment::STATUS_PAID,
        ]);

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => Sale::STATUS_PAID,
            'paid_amount' => '100.00',
        ]);
    }

    public function test_webhook_with_invalid_secret_is_rejected(): void
    {
        $contractor = $this->createContractor('loja-webhook-secret');

        PaymentGateway::query()->create([
            'contractor_id' => $contractor->id,
            'provider' => PaymentGateway::PROVIDER_MANUAL,
            'name' => 'Gateway Teste',
            'is_active' => true,
            'is_default' => true,
            'is_sandbox' => true,
            'credentials' => ['webhook_secret' => 'segredo-webhook'],
            'settings' => null,
        ]);

        $response = $this->postJson(route('shop.payments.webhook', [
            'slug' => $contractor->slug,
            'provider' => PaymentGateway::PROVIDER_MANUAL,
        ]), [
            'transaction_reference' => 'TX-FAIL',
            'status' => 'paid',
            'webhook_secret' => 'segredo-incorreto',
        ]);

        $response->assertForbidden();
    }

    public function test_webhook_deduplicates_repeated_event_payload(): void
    {
        $contractor = $this->createContractor('loja-webhook-dedupe');

        $gateway = PaymentGateway::query()->create([
            'contractor_id' => $contractor->id,
            'provider' => PaymentGateway::PROVIDER_MANUAL,
            'name' => 'Gateway Dedupe',
            'is_active' => true,
            'is_default' => true,
            'is_sandbox' => true,
            'credentials' => ['webhook_secret' => 'segredo-webhook'],
            'settings' => null,
        ]);

        $method = PaymentMethod::query()->create([
            'contractor_id' => $contractor->id,
            'payment_gateway_id' => $gateway->id,
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

        $sale = Sale::query()->create([
            'contractor_id' => $contractor->id,
            'code' => 'PED-WEBHOOK-DEDUPE-001',
            'source' => Sale::SOURCE_CATALOG,
            'status' => Sale::STATUS_AWAITING_PAYMENT,
            'subtotal_amount' => 100,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'total_amount' => 100,
            'paid_amount' => 0,
            'change_amount' => 0,
        ]);

        SalePayment::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'payment_method_id' => $method->id,
            'payment_gateway_id' => $gateway->id,
            'status' => SalePayment::STATUS_PENDING,
            'amount' => 100,
            'transaction_reference' => 'TX-DEDUPE-001',
        ]);

        $payload = [
            'event_id' => 'evt-001',
            'transaction_reference' => 'TX-DEDUPE-001',
            'status' => 'paid',
        ];

        $firstResponse = $this
            ->withHeader('X-Webhook-Secret', 'segredo-webhook')
            ->postJson(route('shop.payments.webhook', [
                'slug' => $contractor->slug,
                'provider' => PaymentGateway::PROVIDER_MANUAL,
            ]), $payload);

        $secondResponse = $this
            ->withHeader('X-Webhook-Secret', 'segredo-webhook')
            ->postJson(route('shop.payments.webhook', [
                'slug' => $contractor->slug,
                'provider' => PaymentGateway::PROVIDER_MANUAL,
            ]), $payload);

        $firstResponse->assertOk()->assertJson([
            'ok' => true,
        ]);

        $secondResponse->assertOk()->assertJson([
            'ok' => true,
            'deduplicated' => true,
        ]);

        $this->assertSame(1, \App\Models\PaymentWebhookReceipt::query()
            ->where('contractor_id', $contractor->id)
            ->where('payment_gateway_id', $gateway->id)
            ->count());
    }

    public function test_mercado_pago_webhook_enriches_payload_by_api_and_confirms_payment(): void
    {
        $contractor = $this->createContractor('loja-webhook-mp');

        $gateway = PaymentGateway::query()->create([
            'contractor_id' => $contractor->id,
            'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            'name' => 'Mercado Pago',
            'is_active' => true,
            'is_default' => true,
            'is_sandbox' => true,
            'credentials' => [
                'access_token' => 'APP_USR_TEST_TOKEN',
                'webhook_secret' => 'token-webhook-mp',
            ],
            'settings' => null,
        ]);

        $method = PaymentMethod::query()->create([
            'contractor_id' => $contractor->id,
            'payment_gateway_id' => $gateway->id,
            'code' => PaymentMethod::CODE_PIX,
            'name' => 'Pix MP',
            'is_active' => true,
            'is_default' => true,
            'allows_installments' => false,
            'max_installments' => null,
            'fee_fixed' => null,
            'fee_percent' => null,
            'sort_order' => 10,
            'settings' => null,
        ]);

        $sale = Sale::query()->create([
            'contractor_id' => $contractor->id,
            'code' => 'PED-MP-WEBHOOK-001',
            'source' => Sale::SOURCE_CATALOG,
            'status' => Sale::STATUS_AWAITING_PAYMENT,
            'subtotal_amount' => 100,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'total_amount' => 100,
            'paid_amount' => 0,
            'change_amount' => 0,
        ]);

        $payment = SalePayment::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'payment_method_id' => $method->id,
            'payment_gateway_id' => $gateway->id,
            'status' => SalePayment::STATUS_PENDING,
            'amount' => 100,
            'transaction_reference' => null,
        ]);

        Http::fake([
            'https://api.mercadopago.com/v1/payments/123456' => Http::response([
                'id' => '123456',
                'status' => 'approved',
                'external_reference' => 'PED-MP-WEBHOOK-001',
                'point_of_interaction' => [
                    'transaction_data' => [
                        'ticket_url' => 'https://www.mercadopago.com.br/payments/qr/123456',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('shop.payments.webhook', [
            'slug' => $contractor->slug,
            'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            'token' => 'token-webhook-mp',
        ]), [
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => [
                'id' => '123456',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'data' => [
                    'payment_id' => $payment->id,
                    'payment_status' => SalePayment::STATUS_PAID,
                    'sale_status' => Sale::STATUS_PAID,
                ],
            ]);

        $this->assertDatabaseHas('sale_payments', [
            'id' => $payment->id,
            'status' => SalePayment::STATUS_PAID,
            'transaction_reference' => '123456',
        ]);

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => Sale::STATUS_PAID,
            'paid_amount' => '100.00',
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

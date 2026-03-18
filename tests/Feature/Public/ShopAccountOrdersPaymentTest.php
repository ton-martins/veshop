<?php

namespace Tests\Feature\Public;

use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\ShopCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopAccountOrdersPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_account_includes_pix_payment_payload_for_customer_order(): void
    {
        $contractor = $this->createContractor('loja-conta-pix');

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
            'name' => 'Cliente Conta Pix',
            'email' => 'cliente-conta-pix@example.com',
            'phone' => '71999990111',
            'password' => '12345678',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $sale = Sale::query()->create([
            'contractor_id' => $contractor->id,
            'shop_customer_id' => $shopCustomer->id,
            'code' => 'PED-CONTA-PIX-001',
            'source' => Sale::SOURCE_CATALOG,
            'status' => Sale::STATUS_AWAITING_PAYMENT,
            'subtotal_amount' => 89.9,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'total_amount' => 89.9,
            'paid_amount' => 0,
            'change_amount' => 0,
            'metadata' => [
                'payment_intent' => [
                    'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                    'status' => 'pending',
                    'transaction_reference' => 'TX-CONTA-PIX-001',
                    'ticket_url' => 'https://www.mercadopago.com.br/payments/qr/TX-CONTA-PIX-001',
                    'qr_code' => '0002010102CONTA',
                    'qr_code_base64' => 'RkFLRV9DT05UQQ==',
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
            'amount' => 89.9,
            'transaction_reference' => 'TX-CONTA-PIX-001',
            'gateway_payload' => [
                'payment_intent' => [
                    'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                    'status' => 'pending',
                    'transaction_reference' => 'TX-CONTA-PIX-001',
                    'ticket_url' => 'https://www.mercadopago.com.br/payments/qr/TX-CONTA-PIX-001',
                    'qr_code' => '0002010102CONTA',
                    'qr_code_base64' => 'RkFLRV9DT05UQQ==',
                    'date_of_expiration' => now()->addMinutes(30)->toIso8601String(),
                ],
            ],
            'metadata' => [
                'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            ],
        ]);

        $response = $this
            ->actingAs($shopCustomer, 'shop')
            ->get(route('shop.show', ['slug' => $contractor->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Public/Shop')
            ->where('shop_account.orders.0.id', $sale->id)
            ->where('shop_account.orders.0.payment.method_code', 'pix')
            ->where('shop_account.orders.0.payment.status', 'pending')
            ->where('shop_account.orders.0.payment.status_label', 'Aguardando pagamento')
            ->where('shop_account.orders.0.payment.qr_code', '0002010102CONTA')
            ->where('shop_account.orders.0.payment.is_pix', true)
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

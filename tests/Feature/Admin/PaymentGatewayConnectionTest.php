<?php

namespace Tests\Feature\Admin;

use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentGatewayConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_admin_can_validate_mercado_pago_connection(): void
    {
        Http::fake([
            'https://api.mercadopago.com/users/me' => Http::response([
                'id' => 123456,
                'nickname' => 'veshop_seller',
                'email' => 'seller@example.com',
            ], 200),
        ]);

        $contractor = $this->createContractor('gateway-connection');
        $user = $this->createAdminUser([$contractor]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->postJson(route('admin.finance.gateways.test'), [
                'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                'is_sandbox' => true,
                'mercado_pago_access_token' => 'APP_USR_TEST_TOKEN',
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'details' => [
                    'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                    'nickname' => 'veshop_seller',
                ],
            ]);

        Http::assertSent(static function (\Illuminate\Http\Client\Request $request): bool {
            return str_contains($request->url(), '/users/me')
                && $request->method() === 'GET'
                && $request->hasHeader('Authorization');
        });
    }

    public function test_admin_receives_error_when_mercado_pago_token_is_invalid(): void
    {
        Http::fake([
            'https://api.mercadopago.com/users/me' => Http::response([
                'message' => 'invalid_token',
            ], 401),
        ]);

        $contractor = $this->createContractor('gateway-connection-invalid');
        $user = $this->createAdminUser([$contractor]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->postJson(route('admin.finance.gateways.test'), [
                'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                'mercado_pago_access_token' => 'APP_USR_INVALID',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJson([
                'ok' => false,
                'message' => 'Access token do Mercado Pago invalido ou sem permissao.',
            ]);
    }

    public function test_connection_test_uses_stored_gateway_token_and_updates_health_check(): void
    {
        Http::fake([
            'https://api.mercadopago.com/users/me' => Http::response([
                'id' => 987654,
                'nickname' => 'stored_token_user',
                'email' => 'stored@example.com',
            ], 200),
        ]);

        $contractor = $this->createContractor('gateway-connection-stored-token');
        $user = $this->createAdminUser([$contractor]);

        $gateway = PaymentGateway::query()->create([
            'contractor_id' => $contractor->id,
            'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            'name' => 'Mercado Pago Principal',
            'is_active' => true,
            'is_default' => true,
            'is_sandbox' => false,
            'credentials' => [
                'access_token' => 'APP_USR_STORED_TOKEN',
                'webhook_secret' => 'stored-secret',
            ],
        ]);

        $this->assertNull($gateway->last_health_check_at);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->postJson(route('admin.finance.gateways.test'), [
                'gateway_id' => $gateway->id,
                'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                'is_sandbox' => false,
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $gateway->refresh();
        $this->assertNotNull($gateway->last_health_check_at);

        Http::assertSent(static function (\Illuminate\Http\Client\Request $request): bool {
            return str_contains($request->url(), '/users/me')
                && $request->method() === 'GET'
                && $request->hasHeader('Authorization');
        });
    }

    public function test_admin_cannot_test_gateway_from_another_contractor(): void
    {
        $contractorA = $this->createContractor('gateway-connection-a');
        $contractorB = $this->createContractor('gateway-connection-b');
        $user = $this->createAdminUser([$contractorA]);

        $gatewayFromOtherContractor = PaymentGateway::query()->create([
            'contractor_id' => $contractorB->id,
            'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            'name' => 'Gateway Externo',
            'is_active' => true,
            'is_default' => false,
            'is_sandbox' => true,
            'credentials' => [
                'access_token' => 'APP_USR_OTHER',
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractorA->id,
                'two_factor_passed' => true,
            ])
            ->postJson(route('admin.finance.gateways.test'), [
                'gateway_id' => $gatewayFromOtherContractor->id,
                'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            ]);

        $response->assertNotFound();
    }

    /**
     * @param array<int, Contractor> $contractors
     */
    private function createAdminUser(array $contractors): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'two_factor_secret' => 'fake-secret',
            'two_factor_confirmed_at' => now(),
        ]);

        $user->contractors()->sync(collect($contractors)->pluck('id')->all());

        return $user;
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
                'require_2fa' => true,
                'require_email_verification' => true,
            ],
            'is_active' => true,
        ]);
    }
}


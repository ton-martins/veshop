<?php

namespace Tests\Feature\Admin;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentGatewayValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_admin_cannot_create_unsupported_gateway_provider(): void
    {
        $contractor = $this->createContractor('gateway-validation');
        $user = $this->createAdminUser([$contractor]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->from(route('admin.finance.index', ['tab' => 'payments']))
            ->post(route('admin.finance.gateways.store'), [
                'provider' => 'stripe',
                'name' => 'Stripe',
                'is_active' => true,
                'is_default' => false,
                'is_sandbox' => true,
            ]);

        $response->assertRedirect(route('admin.finance.index', ['tab' => 'payments']));
        $response->assertSessionHasErrors(['provider']);

        $this->assertDatabaseMissing('payment_gateways', [
            'contractor_id' => $contractor->id,
            'provider' => 'stripe',
        ]);
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
        ]);
    }
}

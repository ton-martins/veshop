<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InertiaValidationResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_invalid_login_with_inertia_headers_does_not_return_plain_json_validation_payload(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->from('/login')
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json, text/plain, */*',
            ])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_invalid_shop_login_with_inertia_headers_does_not_return_plain_json_validation_payload(): void
    {
        $contractor = \App\Models\Contractor::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Loja Teste',
            'email' => 'loja-teste@example.com',
            'slug' => 'loja-teste',
            'timezone' => 'America/Sao_Paulo',
            'brand_name' => 'Loja Teste',
            'brand_primary_color' => '#073341',
            'settings' => [
                'business_niche' => \App\Models\Contractor::NICHE_COMMERCIAL,
                'active_plan_name' => 'Pro',
                'require_email_verification' => true,
            ],
            'is_active' => true,
        ]);

        \App\Models\ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Teste',
            'email' => 'cliente-teste@example.com',
            'phone' => '11999999999',
            'password' => 'Password@123',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->from("/shop/{$contractor->slug}/entrar")
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json, text/plain, */*',
            ])
            ->post("/shop/{$contractor->slug}/entrar", [
                'email' => 'cliente-teste@example.com',
                'password' => 'wrong-password',
            ]);

        $response->assertRedirect("/shop/{$contractor->slug}/entrar");
        $response->assertSessionHasErrors('email');
    }
}

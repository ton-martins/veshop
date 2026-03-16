<?php

namespace Tests\Feature\Public;

use App\Models\Contractor;
use App\Models\ShopCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopAccountProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_shop_customer_can_update_phone_and_address_from_account_page(): void
    {
        $contractor = $this->createContractor('loja-conta-perfil');

        $customer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Perfil',
            'email' => 'cliente-perfil@example.com',
            'phone' => '(11) 99999-0000',
            'password' => 'Password@123',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($customer, 'shop')
            ->patch(route('shop.account.update', ['slug' => $contractor->slug]), [
                'phone' => '(11) 98888-7777',
                'cep' => '01001-000',
                'street' => 'Praca da Se',
                'number' => '100',
                'complement' => 'Sala 12',
                'neighborhood' => 'Se',
                'city' => 'Sao Paulo',
                'state' => 'SP',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Dados atualizados com sucesso.');

        $this->assertDatabaseHas('shop_customers', [
            'id' => $customer->id,
            'phone' => '(11) 98888-7777',
            'cep' => '01001-000',
            'street' => 'Praca da Se',
            'number' => '100',
            'complement' => 'Sala 12',
            'neighborhood' => 'Se',
            'city' => 'Sao Paulo',
            'state' => 'SP',
        ]);

        $customer->refresh();
        $this->assertNotNull($customer->client_id);

        $this->assertDatabaseHas('clients', [
            'id' => $customer->client_id,
            'contractor_id' => $contractor->id,
            'phone' => '(11) 98888-7777',
            'cep' => '01001-000',
            'street' => 'Praca da Se',
            'number' => '100',
            'complement' => 'Sala 12',
            'neighborhood' => 'Se',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'is_active' => 1,
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
                'require_email_verification' => true,
            ],
            'is_active' => true,
        ]);
    }
}

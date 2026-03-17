<?php

namespace Tests\Feature\Public;

use App\Models\Contractor;
use App\Models\ShopCustomer;
use App\Notifications\Shop\VerifyShopCustomerEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_customer_must_verify_email_after_registering_in_store(): void
    {
        $contractor = $this->createContractor('loja-auth');
        Notification::fake();

        $response = $this->post(route('shop.auth.register.store', ['slug' => $contractor->slug]), [
            'name' => 'Cliente Loja',
            'email' => 'cliente-auth@example.com',
            'phone' => '(11) 99999-0000',
            'cep' => '01001-000',
            'street' => 'Praca da Se',
            'neighborhood' => 'Se',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertRedirect(route('shop.verification.notice', ['slug' => $contractor->slug]));

        $this->assertDatabaseHas('shop_customers', [
            'contractor_id' => $contractor->id,
            'email' => 'cliente-auth@example.com',
            'is_active' => 1,
            'email_verified_at' => null,
            'cep' => '01001-000',
            'street' => 'Praca da Se',
            'neighborhood' => 'Se',
            'city' => 'Sao Paulo',
            'state' => 'SP',
        ]);

        $customer = ShopCustomer::query()
            ->where('contractor_id', $contractor->id)
            ->where('email', 'cliente-auth@example.com')
            ->firstOrFail();

        Notification::assertSentTo($customer, VerifyShopCustomerEmailNotification::class);
        $this->assertAuthenticatedAs($customer, 'shop');
    }

    public function test_customer_can_register_and_access_account_when_email_verification_is_disabled(): void
    {
        $contractor = $this->createContractor('loja-sem-verificacao', false);
        Notification::fake();

        $response = $this->post(route('shop.auth.register.store', ['slug' => $contractor->slug]), [
            'name' => 'Cliente Sem Verificacao',
            'email' => 'cliente-sem-verificacao@example.com',
            'phone' => '(11) 98888-0000',
            'cep' => '01001-000',
            'street' => 'Praca da Se',
            'neighborhood' => 'Se',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertRedirect(route('shop.account', ['slug' => $contractor->slug]));

        $customer = ShopCustomer::query()
            ->where('contractor_id', $contractor->id)
            ->where('email', 'cliente-sem-verificacao@example.com')
            ->firstOrFail();

        $this->assertNotNull($customer->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_customer_account_from_one_store_cannot_login_into_another_store(): void
    {
        $contractorA = $this->createContractor('loja-a');
        $contractorB = $this->createContractor('loja-b');

        ShopCustomer::query()->create([
            'contractor_id' => $contractorA->id,
            'name' => 'Cliente A',
            'email' => 'cliente-a@example.com',
            'phone' => '71999990000',
            'password' => 'Password@123',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('shop.auth.store', ['slug' => $contractorB->slug]), [
            'email' => 'cliente-a@example.com',
            'password' => 'Password@123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_unverified_customer_is_redirected_to_shop_verification_notice_on_login(): void
    {
        $contractor = $this->createContractor('loja-login-verificacao');

        ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Pendente',
            'email' => 'cliente-pendente@example.com',
            'phone' => '71999990000',
            'password' => 'Password@123',
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $response = $this->post(route('shop.auth.store', ['slug' => $contractor->slug]), [
            'email' => 'cliente-pendente@example.com',
            'password' => 'Password@123',
        ]);

        $response->assertRedirect(route('shop.verification.notice', ['slug' => $contractor->slug]));
        $response->assertSessionHas('status', 'Confirme seu e-mail para continuar.');
    }

    public function test_customer_can_verify_email_using_signed_link(): void
    {
        $contractor = $this->createContractor('loja-link-verificacao');

        $customer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente Link',
            'email' => 'cliente-link@example.com',
            'phone' => '71999990001',
            'password' => 'Password@123',
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'shop.verification.verify',
            now()->addMinutes(60),
            [
                'slug' => $contractor->slug,
                'id' => $customer->id,
                'hash' => sha1($customer->getEmailForVerification()),
            ],
        );

        $response = $this->get($signedUrl);

        $response->assertRedirect(route('shop.account', ['slug' => $contractor->slug]));
        $this->assertNotNull($customer->fresh()->email_verified_at);
        $this->assertAuthenticatedAs($customer->fresh(), 'shop');
    }

    public function test_store_registration_requires_required_address_fields(): void
    {
        $contractor = $this->createContractor('loja-endereco-obrigatorio');

        $response = $this->post(route('shop.auth.register.store', ['slug' => $contractor->slug]), [
            'name' => 'Cliente Endereco',
            'email' => 'cliente-endereco@example.com',
            'phone' => '(11) 97777-0000',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertSessionHasErrors([
            'cep',
            'street',
            'neighborhood',
            'city',
            'state',
        ]);
    }

    private function createContractor(string $slug, bool $requireEmailVerification = true): Contractor
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
                'require_email_verification' => $requireEmailVerification,
            ],
            'is_active' => true,
        ]);
    }
}

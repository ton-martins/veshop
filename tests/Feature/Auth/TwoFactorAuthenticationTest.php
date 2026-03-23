<?php

namespace Tests\Feature\Auth;

use App\Models\Contractor;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_login_redirects_to_setup_when_two_factor_is_not_configured(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('two-factor.setup', absolute: false));
    }

    public function test_login_redirects_to_challenge_when_two_factor_is_enabled(): void
    {
        $twoFactorService = app(TwoFactorService::class);
        $secret = $twoFactorService->generateSecret();

        $user = User::factory()->create([
            'two_factor_secret' => $twoFactorService->encryptSecret($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('two-factor.challenge', absolute: false));
    }

    public function test_user_can_confirm_two_factor_setup_and_access_dashboard(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.setup', absolute: false));

        $this->get(route('two-factor.setup'))->assertOk();

        $user->refresh();
        $twoFactorService = app(TwoFactorService::class);
        $plainSecret = $twoFactorService->decryptSecret($user->two_factor_secret);
        $this->assertNotNull($plainSecret);
        $otp = app(Google2FA::class)->getCurrentOtp($plainSecret);

        $response = $this->post(route('two-factor.confirm'), [
            'code' => $otp,
        ]);

        $response->assertRedirect(route('home', absolute: false));
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertTrue((bool) session('two_factor_passed'));
    }

    public function test_master_two_factor_challenge_ignores_admin_intended_url(): void
    {
        $twoFactorService = app(TwoFactorService::class);
        $secret = $twoFactorService->generateSecret();
        $otp = app(Google2FA::class)->getCurrentOtp($secret);

        $master = User::factory()->create([
            'role' => User::ROLE_MASTER,
            'two_factor_secret' => $twoFactorService->encryptSecret($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this
            ->actingAs($master)
            ->withSession([
                'two_factor_passed' => false,
                'url.intended' => '/app/home',
            ])
            ->post(route('two-factor.verify'), [
                'code' => $otp,
            ]);

        $response->assertRedirect(route('home', absolute: false));
        $this->assertTrue((bool) session('two_factor_passed'));
        $this->assertNull(session('url.intended'));
    }

    public function test_admin_two_factor_challenge_ignores_master_intended_url(): void
    {
        $twoFactorService = app(TwoFactorService::class);
        $secret = $twoFactorService->generateSecret();
        $otp = app(Google2FA::class)->getCurrentOtp($secret);

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'two_factor_secret' => $twoFactorService->encryptSecret($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->withSession([
                'two_factor_passed' => false,
                'url.intended' => '/master/home',
            ])
            ->post(route('two-factor.verify'), [
                'code' => $otp,
            ]);

        $response->assertRedirect(route('home', absolute: false));
        $this->assertTrue((bool) session('two_factor_passed'));
        $this->assertNull(session('url.intended'));
    }

    public function test_dashboard_redirect_uses_normalized_master_role(): void
    {
        $twoFactorService = app(TwoFactorService::class);
        $secret = $twoFactorService->generateSecret();

        $user = User::factory()->create([
            'role' => ' MASTER ',
            'email_verified_at' => now(),
            'two_factor_secret' => $twoFactorService->encryptSecret($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['two_factor_passed' => true])
            ->get(route('home'));

        $response->assertRedirect(route('master.home'));
    }

    public function test_role_middleware_accepts_normalized_role_value(): void
    {
        $twoFactorService = app(TwoFactorService::class);
        $secret = $twoFactorService->generateSecret();

        $user = User::factory()->create([
            'role' => 'MASTER',
            'email_verified_at' => now(),
            'two_factor_secret' => $twoFactorService->encryptSecret($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['two_factor_passed' => true])
            ->get(route('master.home'));

        $response->assertOk();
    }

    public function test_admin_role_normalization_allows_admin_home_access(): void
    {
        $twoFactorService = app(TwoFactorService::class);
        $secret = $twoFactorService->generateSecret();

        $contractor = Contractor::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Contratante Role Normalizado',
            'email' => 'role-normalizado@example.com',
            'slug' => 'role-normalizado',
            'timezone' => 'America/Sao_Paulo',
            'brand_name' => 'Role Normalizado',
            'brand_primary_color' => '#073341',
            'settings' => [
                'business_niche' => Contractor::NICHE_COMMERCIAL,
                'active_plan_name' => 'Pro',
            ],
            'business_type' => Contractor::BUSINESS_TYPE_STORE,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => ' ADMIN ',
            'email_verified_at' => now(),
            'two_factor_secret' => $twoFactorService->encryptSecret($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $user->contractors()->sync([$contractor->id]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'two_factor_passed' => true,
                'current_contractor_id' => $contractor->id,
            ])
            ->get(route('admin.home'));

        $response->assertOk();
    }

    public function test_two_factor_challenge_does_not_expose_auth_context_before_validation(): void
    {
        $twoFactorService = app(TwoFactorService::class);
        $secret = $twoFactorService->generateSecret();

        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'two_factor_secret' => $twoFactorService->encryptSecret($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $contractor = Contractor::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Contratante Sigiloso',
            'email' => 'contratante-sigiloso@example.com',
            'slug' => 'contratante-sigiloso',
            'timezone' => 'America/Sao_Paulo',
            'brand_name' => 'Contratante Sigiloso',
            'brand_primary_color' => '#073341',
            'settings' => [
                'business_niche' => Contractor::NICHE_COMMERCIAL,
                'active_plan_name' => 'Pro',
            ],
            'business_type' => Contractor::BUSINESS_TYPE_STORE,
            'is_active' => true,
        ]);

        $user->contractors()->sync([$contractor->id]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.challenge', absolute: false));

        $response = $this
            ->withSession(['current_contractor_id' => $contractor->id, 'two_factor_passed' => false])
            ->get(route('two-factor.challenge'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/TwoFactorChallenge')
            ->where('auth.user', null)
            ->where('contractorContext.current', null)
            ->where('contractorContext.available', [])
            ->where('notifications.unread_count', 0)
            ->where('notifications.items', [])
        );
    }
}

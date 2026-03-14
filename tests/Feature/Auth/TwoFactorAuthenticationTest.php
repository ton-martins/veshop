<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

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
}

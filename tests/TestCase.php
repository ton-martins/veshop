<?php

namespace Tests;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsWithTwoFactor(User $user): static
    {
        $twoFactorService = app(TwoFactorService::class);
        $secret = $twoFactorService->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $twoFactorService->encryptSecret($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)->withSession([
            'two_factor_passed' => true,
        ]);

        return $this;
    }
}

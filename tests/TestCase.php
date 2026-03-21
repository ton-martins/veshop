<?php

namespace Tests;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private const REQUIRED_TEST_CONNECTION = 'sqlite';

    private const REQUIRED_TEST_DATABASE = ':memory:';

    protected function setUp(): void
    {
        $this->assertProcessEnvironmentIsSafe();

        parent::setUp();

        $this->assertLaravelDatabaseConfigIsSafe();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

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

    private function assertProcessEnvironmentIsSafe(): void
    {
        $connection = $this->getEnvValue('DB_CONNECTION');
        $database = $this->getEnvValue('DB_DATABASE');

        if ($connection !== self::REQUIRED_TEST_CONNECTION || $database !== self::REQUIRED_TEST_DATABASE) {
            throw new RuntimeException(
                'Execucao de testes bloqueada: somente DB_CONNECTION=sqlite e DB_DATABASE=:memory: sao permitidos.'
            );
        }
    }

    private function assertLaravelDatabaseConfigIsSafe(): void
    {
        $defaultConnection = (string) config('database.default');
        $database = (string) config("database.connections.{$defaultConnection}.database");

        if ($defaultConnection !== self::REQUIRED_TEST_CONNECTION || $database !== self::REQUIRED_TEST_DATABASE) {
            throw new RuntimeException(
                'Execucao de testes bloqueada: a configuracao carregada do Laravel nao esta em sqlite :memory:.'
            );
        }
    }

    private function getEnvValue(string $key): ?string
    {
        $value = getenv($key);
        if (is_string($value)) {
            return $value;
        }

        if (isset($_ENV[$key]) && is_string($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return null;
    }
}

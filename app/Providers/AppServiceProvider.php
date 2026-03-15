<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    private const TEST_DB_CONNECTION = 'sqlite';

    private const TEST_DB_DATABASE = ':memory:';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! $this->isTestProcess()) {
            return;
        }

        // Hard lock: tests must always use in-memory SQLite.
        config()->set('database.default', self::TEST_DB_CONNECTION);
        config()->set('database.connections.sqlite.database', self::TEST_DB_DATABASE);
        config()->set('database.connections.sqlite.foreign_key_constraints', true);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->assertSafeTestingDatabase();

        if ((bool) config('app.force_https', false)) {
            URL::forceScheme('https');

            $appUrl = rtrim((string) config('app.url', ''), '/');
            if ($appUrl !== '') {
                URL::forceRootUrl($appUrl);
            }
        }

        Vite::prefetch(concurrency: 3);
    }

    private function assertSafeTestingDatabase(): void
    {
        if (! $this->isTestProcess()) {
            return;
        }

        // Reinforce test connection even when config cache exists.
        config()->set('database.default', self::TEST_DB_CONNECTION);
        config()->set('database.connections.sqlite.database', self::TEST_DB_DATABASE);
        config()->set('database.connections.sqlite.foreign_key_constraints', true);

        $defaultConnection = (string) config('database.default');
        $database = (string) config("database.connections.{$defaultConnection}.database");

        if ($defaultConnection !== self::TEST_DB_CONNECTION || $database !== self::TEST_DB_DATABASE) {
            throw new RuntimeException(
                'Safety check failed: test execution is blocked unless DB_CONNECTION=sqlite and DB_DATABASE=:memory:.'
            );
        }
    }

    private function isTestProcess(): bool
    {
        if ($this->app->runningUnitTests()) {
            return true;
        }

        if (defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__')) {
            return true;
        }

        if (! $this->app->runningInConsole()) {
            return false;
        }

        $argv = $_SERVER['argv'] ?? [];
        if (! is_array($argv) || $argv === []) {
            return false;
        }

        $command = strtolower(implode(' ', $argv));

        return str_contains($command, 'phpunit')
            || str_contains($command, 'pest')
            || str_contains($command, 'artisan test')
            || str_contains($command, ' test ');
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! $this->app->runningUnitTests()) {
            return;
        }

        // Hard lock: tests must always use in-memory SQLite.
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.foreign_key_constraints', true);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->assertSafeTestingDatabase();

        Vite::prefetch(concurrency: 3);
    }

    private function assertSafeTestingDatabase(): void
    {
        if (! $this->app->runningUnitTests()) {
            return;
        }

        // Reinforce test connection even when config cache exists.
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.foreign_key_constraints', true);

        $defaultConnection = (string) config('database.default');
        $database = (string) config("database.connections.{$defaultConnection}.database");

        if ($defaultConnection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                'Safety check failed: test execution is blocked unless DB_CONNECTION=sqlite and DB_DATABASE=:memory:.'
            );
        }
    }
}

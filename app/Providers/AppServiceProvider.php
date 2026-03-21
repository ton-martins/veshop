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
        $this->clearStaleViteHotFile();

        if ((bool) config('app.force_https', false)) {
            URL::forceScheme('https');

            // Avoid cross-origin Inertia visits caused by forced host mismatches
            // (e.g. veshop.com.br -> www.veshop.com.br). Force root URL only
            // when explicitly requested.
            $forceRootUrl = filter_var(env('APP_FORCE_ROOT_URL', false), FILTER_VALIDATE_BOOL);
            if ($forceRootUrl) {
                $appUrl = rtrim((string) config('app.url', ''), '/');
                if ($appUrl !== '') {
                    URL::forceRootUrl($appUrl);
                }
            }
        }

        Vite::prefetch(concurrency: 3);
    }

    private function clearStaleViteHotFile(): void
    {
        if (! $this->app->isLocal()) {
            return;
        }

        $hotFile = public_path('hot');
        if (! is_file($hotFile)) {
            return;
        }

        $hotUrl = trim((string) @file_get_contents($hotFile));
        if ($hotUrl === '') {
            @unlink($hotFile);

            return;
        }

        $parts = parse_url($hotUrl);
        if (! is_array($parts)) {
            @unlink($hotFile);

            return;
        }

        $host = (string) ($parts['host'] ?? '');
        $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
        $port = isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https' ? 443 : 80);

        if ($host === '' || $port <= 0) {
            @unlink($hotFile);

            return;
        }

        $target = str_contains($host, ':')
            ? "tcp://[{$host}]:{$port}"
            : "tcp://{$host}:{$port}";

        $socket = @stream_socket_client($target, $errno, $error, 0.25, STREAM_CLIENT_CONNECT);

        if (! is_resource($socket)) {
            @unlink($hotFile);

            return;
        }

        fclose($socket);
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

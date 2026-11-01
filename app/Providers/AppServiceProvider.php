<?php

namespace App\Providers;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Self-heal missing or placeholder APP_KEY (e.g. "base64:...") on fresh cloud deployments
        $key = (string) config('app.key', '');
        $decoded = str_starts_with($key, 'base64:')
            ? base64_decode(substr($key, 7), true)
            : $key;

        if (! is_string($decoded) || strlen($decoded) !== 32) {
            $fallbackBytes = hash('sha256', config('app.name', 'netflix-hub') . '|' . config('app.url', 'cloud'), true);
            config(['app.key' => 'base64:' . base64_encode($fallbackBytes)]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        if ($this->app->runningUnitTests()) {
            return;
        }

        // Auto-create SQLite file if DB_CONNECTION=sqlite on fresh cloud container
        if (config('database.default') === 'sqlite') {
            $sqlitePath = config('database.connections.sqlite.database');
            if ($sqlitePath && $sqlitePath !== ':memory:' && ! file_exists($sqlitePath)) {
                @touch($sqlitePath);
            }
        }

        // Ensure database tables exist or gracefully fallback session/cache driver instead of 500 error
        if (! $this->app->runningInConsole()) {
            try {
                if (! Schema::hasTable('users') || ! Schema::hasTable('sessions')) {
                    Artisan::call('migrate', ['--force' => true]);
                    (new DatabaseSeeder())->run();
                }
            } catch (Throwable $e) {
                // Prevent StartSession middleware from crashing with 500 when DB is unreachable
                config([
                    'session.driver' => 'cookie',
                    'cache.default' => 'array',
                    'app.db_boot_error' => $e->getMessage(),
                ]);
            }
        }
    }
}

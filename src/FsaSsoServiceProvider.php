<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use PutheaKhem\FsaSso\Http\Middleware\AuthenticateFsaSsoToken;
use PutheaKhem\FsaSso\Services\FsaSsoManager;
use PutheaKhem\FsaSso\Services\FsaSsoTokenVerifier;
use PutheaKhem\FsaSso\Services\FsaSsoUserProvisioner;

final class FsaSsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fsa-sso.php', 'fsa-sso');

        $this->app->singleton(FsaSsoTokenVerifier::class, fn () => new FsaSsoTokenVerifier());
        $this->app->singleton(FsaSsoUserProvisioner::class, fn () => new FsaSsoUserProvisioner());

        $this->app->singleton(FsaSsoManager::class, fn ($app) => new FsaSsoManager(
            $app->make(FsaSsoTokenVerifier::class),
            $app->make(FsaSsoUserProvisioner::class),
        ));
    }

    public function boot(): void
    {
        $this->app->make(Router::class)->aliasMiddleware('fsa-sso.auth', AuthenticateFsaSsoToken::class);

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/fsa-sso.php' => config_path('fsa-sso.php'),
            ], 'fsa-sso-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'fsa-sso-migrations');
        }
    }
}

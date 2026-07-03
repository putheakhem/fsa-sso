<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use PutheaKhem\FsaSso\Auth\FsaSsoApiGuard;
use PutheaKhem\FsaSso\Auth\FsaSsoTokenValidator;
use PutheaKhem\FsaSso\Auth\FsaSsoUserResolver;
use PutheaKhem\FsaSso\Http\Middleware\AuthenticateFsaSsoToken;
use PutheaKhem\FsaSso\Http\Middleware\EnsureFsaSsoClientCode;
use PutheaKhem\FsaSso\Http\Middleware\EnsureFsaSsoTokenIsActive;
use PutheaKhem\FsaSso\Services\FsaSsoManager;
use PutheaKhem\FsaSso\Services\FsaSsoTokenVerifier;
use PutheaKhem\FsaSso\Services\FsaSsoUserProvisioner;

final class FsaSsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fsa-sso.php', 'fsa-sso');
        $this->registerDefaultGuardConfig();

        $this->app->singleton(FsaSsoTokenVerifier::class, fn () => new FsaSsoTokenVerifier());
        $this->app->singleton(FsaSsoUserProvisioner::class, fn () => new FsaSsoUserProvisioner());
        $this->app->singleton(FsaSsoTokenValidator::class, fn () => new FsaSsoTokenValidator());
        $this->app->singleton(FsaSsoUserResolver::class, fn () => new FsaSsoUserResolver());

        $this->app->singleton(FsaSsoManager::class, fn ($app) => new FsaSsoManager(
            $app->make(FsaSsoTokenVerifier::class),
            $app->make(FsaSsoUserProvisioner::class),
        ));
    }

    public function boot(): void
    {
        Auth::extend('fsa-sso-api', function (Application $app, string $name, array $config): FsaSsoApiGuard {
            return new FsaSsoApiGuard(
                request: $app['request'],
                validator: $app->make(FsaSsoTokenValidator::class),
                resolver: $app->make(FsaSsoUserResolver::class),
            );
        });

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('fsa-sso.auth', AuthenticateFsaSsoToken::class);
        $router->aliasMiddleware('fsa-sso.client-code', EnsureFsaSsoClientCode::class);
        $router->aliasMiddleware('fsa-sso.introspect', EnsureFsaSsoTokenIsActive::class);

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $publishableConfig = [
                __DIR__.'/../config/fsa-sso.php' => config_path('fsa-sso.php'),
            ];

            $this->publishes($publishableConfig, 'fsa-sso-config');
            $this->publishes($publishableConfig, 'config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'fsa-sso-migrations');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'migrations');
        }
    }

    private function registerDefaultGuardConfig(): void
    {
        $guardName = (string) config('fsa-sso.api_auth.guard', 'fsa-sso-api');

        if ($guardName === '' || config("auth.guards.{$guardName}") !== null) {
            return;
        }

        $defaultProvider = (string) config('auth.defaults.provider', 'users');

        config()->set("auth.guards.{$guardName}", [
            'driver' => 'fsa-sso-api',
            'provider' => $defaultProvider,
        ]);
    }
}

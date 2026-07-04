<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use PutheaKhem\FsaSso\Auth\EdDsaJwtTokenValidator;
use PutheaKhem\FsaSso\Auth\FsaSsoApiGuard;
use PutheaKhem\FsaSso\Auth\FsaSsoAuthFailureLogger;
use PutheaKhem\FsaSso\Auth\FsaSsoClaimMapper;
use PutheaKhem\FsaSso\Auth\FsaSsoTokenIntrospector;
use PutheaKhem\FsaSso\Auth\FsaSsoTokenValidator;
use PutheaKhem\FsaSso\Auth\FsaSsoTokenValidatorInterface;
use PutheaKhem\FsaSso\Auth\FsaSsoUserResolver;
use PutheaKhem\FsaSso\Auth\IntrospectionTokenValidator;
use PutheaKhem\FsaSso\Http\Middleware\AuthenticateFsaSsoToken;
use PutheaKhem\FsaSso\Http\Middleware\EnsureFsaSsoClientCode;
use PutheaKhem\FsaSso\Http\Middleware\EnsureFsaSsoTokenIsActive;
use PutheaKhem\FsaSso\Services\FsaSsoManager;
use PutheaKhem\FsaSso\Services\FsaSsoStoredTokenManager;
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
        $this->app->singleton(FsaSsoStoredTokenManager::class, fn () => new FsaSsoStoredTokenManager());
        $this->app->singleton(FsaSsoAuthFailureLogger::class, fn () => new FsaSsoAuthFailureLogger());
        $this->app->singleton(FsaSsoClaimMapper::class, fn () => new FsaSsoClaimMapper());
        $this->app->singleton(FsaSsoTokenIntrospector::class, fn ($app) => new FsaSsoTokenIntrospector(
            $app->make(FsaSsoClaimMapper::class),
        ));
        $this->app->singleton(EdDsaJwtTokenValidator::class, fn ($app) => new EdDsaJwtTokenValidator(
            $app->make(FsaSsoClaimMapper::class),
        ));
        $this->app->singleton(IntrospectionTokenValidator::class, fn ($app) => new IntrospectionTokenValidator(
            $app->make(FsaSsoTokenIntrospector::class),
            $app->make(FsaSsoClaimMapper::class),
        ));
        $this->app->singleton(FsaSsoTokenValidator::class, fn ($app) => new FsaSsoTokenValidator(
            $app->make(EdDsaJwtTokenValidator::class),
            $app->make(IntrospectionTokenValidator::class),
        ));
        $this->app->singleton(FsaSsoTokenValidatorInterface::class, fn ($app) => $app->make(FsaSsoTokenValidator::class));
        $this->app->singleton(FsaSsoUserResolver::class, fn () => new FsaSsoUserResolver());

        $this->app->singleton(FsaSsoManager::class, fn ($app) => new FsaSsoManager(
            $app->make(FsaSsoTokenVerifier::class),
            $app->make(FsaSsoUserProvisioner::class),
            $app->make(FsaSsoStoredTokenManager::class),
        ));
    }

    public function boot(): void
    {
        Auth::extend('fsa-sso-api', function (Application $app, string $name, array $config): FsaSsoApiGuard {
            return new FsaSsoApiGuard(
                request: $app['request'],
                validator: $app->make(FsaSsoTokenValidatorInterface::class),
                resolver: $app->make(FsaSsoUserResolver::class),
                authFailureLogger: $app->make(FsaSsoAuthFailureLogger::class),
            );
        });

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('fsa-sso.auth', AuthenticateFsaSsoToken::class);
        $router->aliasMiddleware('fsa-sso.client-code', EnsureFsaSsoClientCode::class);
        $router->aliasMiddleware('fsa-sso.introspect', EnsureFsaSsoTokenIsActive::class);

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $migrationPathsToLoad = $this->migrationPathsToLoad();

        if ($migrationPathsToLoad !== []) {
            $this->loadMigrationsFrom($migrationPathsToLoad);
        }

        if ($this->app->runningInConsole()) {
            $publishableConfig = [
                __DIR__.'/../config/fsa-sso.php' => config_path('fsa-sso.php'),
            ];

            $this->publishes($publishableConfig, 'fsa-sso-config');
            $this->publishes($publishableConfig, 'config');

            $migrationPathsToPublish = $this->migrationPathsToPublish();

            if ($migrationPathsToPublish !== []) {
                $this->publishes($migrationPathsToPublish, 'fsa-sso-migrations');
                $this->publishes($migrationPathsToPublish, 'migrations');
            }
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

    /**
     * @return list<string>
     */
    private function migrationPathsToLoad(): array
    {
        return array_values(array_filter(
            $this->packageMigrationPaths(),
            fn (string $path): bool => ! $this->migrationHasBeenPublished($path),
        ));
    }

    /**
     * @return array<string, string>
     */
    private function migrationPathsToPublish(): array
    {
        $publishablePaths = [];
        $publishedAt = now();

        foreach ($this->migrationPathsToLoad() as $index => $path) {
            $publishablePaths[$path] = database_path('migrations/'.$publishedAt->copy()->addSeconds($index)->format('Y_m_d_His').'_'.$this->migrationSuffix($path));
        }

        return $publishablePaths;
    }

    /**
     * @return list<string>
     */
    private function packageMigrationPaths(): array
    {
        $paths = glob(__DIR__.'/../database/migrations/*.php');

        if ($paths === false) {
            return [];
        }

        sort($paths);

        return $paths;
    }

    private function migrationHasBeenPublished(string $path): bool
    {
        return glob(database_path('migrations/*_'.$this->migrationSuffix($path))) !== [];
    }

    private function migrationSuffix(string $path): string
    {
        $filename = basename($path);

        return preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $filename) ?? $filename;
    }
}

<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Tests;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use PutheaKhem\FsaSso\Auth\FsaSsoApiGuard;
use PutheaKhem\FsaSso\Auth\FsaSsoAuthFailureLogger;
use PutheaKhem\FsaSso\Auth\FsaSsoTokenValidator;
use PutheaKhem\FsaSso\Auth\FsaSsoTokenValidatorInterface;
use PutheaKhem\FsaSso\Auth\FsaSsoUserResolver;
use PutheaKhem\FsaSso\FsaSsoServiceProvider;
use PutheaKhem\FsaSso\Http\Middleware\AuthenticateFsaSsoToken;
use PutheaKhem\FsaSso\Http\Middleware\EnsureFsaSsoClientCode;
use PutheaKhem\FsaSso\Http\Middleware\EnsureFsaSsoTokenIsActive;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FsaSsoServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.defaults.provider', 'users');
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => TestUser::class,
        ]);
        $app['config']->set('fsa-sso.user_model', TestUser::class);
        $app['config']->set('fsa-sso.route_middleware', []);
        $app['config']->set('auth.guards.fsa-sso-api', [
            'driver' => 'fsa-sso-api',
            'provider' => 'users',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(FsaSsoTokenValidatorInterface::class, fn ($app) => $app->make(FsaSsoTokenValidator::class));

        Auth::extend('fsa-sso-api', function (Application $app, string $name, array $config): FsaSsoApiGuard {
            return new FsaSsoApiGuard(
                request: $app['request'],
                validator: $app->make(FsaSsoTokenValidatorInterface::class),
                resolver: $app->make(FsaSsoUserResolver::class),
                authFailureLogger: $app->make(FsaSsoAuthFailureLogger::class),
            );
        });

        Route::aliasMiddleware('fsa-sso.auth', AuthenticateFsaSsoToken::class);
        Route::aliasMiddleware('fsa-sso.client-code', EnsureFsaSsoClientCode::class);
        Route::aliasMiddleware('fsa-sso.introspect', EnsureFsaSsoTokenIsActive::class);
    }
}

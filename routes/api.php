<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PutheaKhem\FsaSso\Http\Controllers\SsoInitiateController;
use PutheaKhem\FsaSso\Http\Controllers\SsoIntrospectController;
use PutheaKhem\FsaSso\Http\Controllers\SsoRevokeController;
use PutheaKhem\FsaSso\Http\Controllers\SsoVerifyController;

$middleware = array_values(array_filter(array_merge(
    (array) config('fsa-sso.route_middleware', ['api']),
    [(string) config('fsa-sso.api_throttle_middleware', 'throttle:30,1')],
), static fn (mixed $value): bool => is_string($value) && $value !== ''));

Route::middleware($middleware)
    ->prefix((string) config('fsa-sso.route_prefix', 'auth/sso'))
    ->group(function (): void {
    Route::get('/initiate', SsoInitiateController::class)->name('fsa-sso.initiate');
    Route::post('/verify', SsoVerifyController::class)->name('fsa-sso.verify');
    Route::post('/introspect', SsoIntrospectController::class)->name('fsa-sso.introspect');
    Route::post('/revoke', SsoRevokeController::class)->name('fsa-sso.revoke');
});

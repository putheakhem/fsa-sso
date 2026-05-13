<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PutheaKhem\FsaSso\Http\Controllers\SsoWebCallbackController;
use PutheaKhem\FsaSso\Http\Controllers\SsoWebLoginRedirectController;

if (! (bool) config('fsa-sso.web_routes_enabled', true)) {
    return;
}

$middleware = array_values(array_filter(array_merge(
    (array) config('fsa-sso.web_route_middleware', ['web']),
    [(string) config('fsa-sso.web_throttle_middleware', 'throttle:30,1')],
), static fn (mixed $value): bool => is_string($value) && $value !== ''));
$loginPath = ltrim((string) config('fsa-sso.web_login_path', 'fsa-sso/loginUrl'), '/');
$callbackPath = ltrim((string) config('fsa-sso.web_callback_path', 'sso/callback-success'), '/');
$fallbackCallbackPath = ltrim((string) config('fsa-sso.web_fallback_callback_path', 'fsa-sso/callback'), '/');
$loginRouteName = (string) config('fsa-sso.web_login_route_name', 'fsaSsoLoginUrl');
$callbackRouteName = (string) config('fsa-sso.web_callback_route_name', 'fsaSsoCallbackSuccess');
$fallbackCallbackRouteName = (string) config('fsa-sso.web_fallback_callback_route_name', 'fsaSsoCallback');

Route::middleware($middleware)->group(function () use (
    $callbackPath,
    $callbackRouteName,
    $fallbackCallbackPath,
    $fallbackCallbackRouteName,
    $loginPath,
    $loginRouteName
): void {
    Route::get('/'.$loginPath, SsoWebLoginRedirectController::class)->name($loginRouteName);
    Route::get('/'.$callbackPath, SsoWebCallbackController::class)->name($callbackRouteName);

    if ($fallbackCallbackPath !== $callbackPath) {
        Route::get('/'.$fallbackCallbackPath, SsoWebCallbackController::class)->name($fallbackCallbackRouteName);
    }
});

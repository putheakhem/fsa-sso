<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array getLoginUrl()
 * @method static array verifyAndProvision(string $token)
 * @method static ?string storedTokenForUser(\Illuminate\Contracts\Auth\Authenticatable $user, bool $markAsUsed = false)
 * @method static ?string storedTokenForCurrentUser(?string $guard = null, bool $markAsUsed = false)
 * @method static bool storedTokenHasExpired(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static void markStoredTokenAsUsed(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static array introspect(string $token)
 * @method static void revoke(string $token)
 *
 * @see \PutheaKhem\FsaSso\Services\FsaSsoManager
 */
final class FsaSso extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \PutheaKhem\FsaSso\Services\FsaSsoManager::class;
    }
}

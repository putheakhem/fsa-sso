<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use PutheaKhem\FsaSso\Http\Middleware\EnsureFsaSsoTokenIsActive;
use PutheaKhem\FsaSso\Tests\TestUser;

beforeEach(function () {
    config()->set('fsa-sso.jwks_url', 'https://sso.example.test/.well-known/jwks.json');
    config()->set('fsa-sso.issuer', 'https://sso.example.test');
    config()->set('fsa-sso.audience', 'https://sso.example.test');
    config()->set('fsa-sso.api_auth.introspection_url', 'https://sso.example.test/api/v1/auth/introspect');
    config()->set('fsa-sso.api_auth.introspection_cache_seconds', 120);
    config()->set('cache.default', 'array');

    TestUser::query()->create([
        'name' => 'Sensitive User',
        'email' => 'sensitive@example.com',
        'sso_id' => 'sensitive-sub',
    ]);

    Route::middleware(['auth:fsa-sso-api', 'fsa-sso.introspect'])
        ->get('/middleware/introspect', fn () => response()->json(['ok' => true]));
});

test('active token passes', function () {
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(introspectionClaims());

    fakeJwksAndIntrospection($jwk, ['active' => true, 'sub' => 'sensitive-sub', 'client_code' => 'FSA-DPS-CODE', 'exp' => time() + 300]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/middleware/introspect')
        ->assertSuccessful();
});

test('inactive token returns unauthenticated', function () {
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(introspectionClaims());

    fakeJwksAndIntrospection($jwk, ['active' => false]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/middleware/introspect')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});

test('introspection response is cached by jti', function () {
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(introspectionClaims([
        'jti' => 'cached-introspection-jti',
    ]));

    fakeJwksAndIntrospection($jwk, ['active' => true]);

    $middleware = app(EnsureFsaSsoTokenIsActive::class);
    $request = Request::create('/middleware/introspect', 'GET', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    ]);
    $request->attributes->set('fsa_sso_jti', 'cached-introspection-jti');
    $request->attributes->set('fsa_sso_token', $token);

    $middleware->handle($request, fn () => response()->json(['ok' => true]));
    $middleware->handle(clone $request, fn () => response()->json(['ok' => true]));

    expect(Http::recorded(
        fn (Illuminate\Http\Client\Request $request): bool => $request->url() === 'https://sso.example.test/api/v1/auth/introspect'
    ))->toHaveCount(1);
});

test('cache key does not contain raw token', function () {
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(introspectionClaims([
        'jti' => null,
    ]));

    fakeJwksAndIntrospection($jwk, ['active' => true]);

    $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/middleware/introspect')->assertSuccessful();

    $store = Cache::getStore();
    expect($store)->toBeInstanceOf(ArrayStore::class);

    $reflection = new ReflectionClass($store);
    $storageProperty = $reflection->getProperty('storage');
    $storageProperty->setAccessible(true);
    /** @var array<string, mixed> $storage */
    $storage = $storageProperty->getValue($store);

    expect(collect(array_keys($storage))->contains(fn (string $key): bool => str_contains($key, $token)))->toBeFalse();
});

test('failed introspection request returns unauthenticated', function () {
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(introspectionClaims());

    Http::fake([
        'https://sso.example.test/.well-known/jwks.json' => Http::response(['keys' => [$jwk]]),
        'https://sso.example.test/api/v1/auth/introspect' => Http::failedConnection(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/middleware/introspect')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function introspectionClaims(array $overrides = []): array
{
    return array_merge([
        'sub' => 'sensitive-sub',
        'email' => 'sensitive@example.com',
        'name' => 'Sensitive User',
        'client_code' => 'FSA-DPS-CODE',
        'iss' => 'https://sso.example.test',
        'aud' => 'https://sso.example.test',
        'iat' => time() - 10,
        'exp' => time() + 300,
        'jti' => 'sensitive-jti',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $payload
 */
function fakeJwksAndIntrospection(array $jwk, array $payload): void
{
    Http::fake([
        'https://sso.example.test/.well-known/jwks.json' => Http::response(['keys' => [$jwk]]),
        'https://sso.example.test/api/v1/auth/introspect' => Http::response($payload),
    ]);
}

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use PutheaKhem\FsaSso\Tests\TestUser;

beforeEach(function () {
    config()->set('fsa-sso.jwks_url', 'https://sso.example.test/.well-known/jwks.json');
    config()->set('fsa-sso.issuer', 'https://sso.example.test');
    config()->set('fsa-sso.audience', 'https://sso.example.test');

    TestUser::query()->create([
        'name' => 'Client Code User',
        'email' => 'client-code@example.com',
        'sso_id' => 'client-code-sub',
    ]);

    Route::middleware(['auth:fsa-sso-api', 'fsa-sso.client-code'])
        ->get('/middleware/client-code/config', fn () => response()->json(['ok' => true]));

    Route::middleware(['auth:fsa-sso-api', 'fsa-sso.client-code:FSA-DPS-CODE'])
        ->get('/middleware/client-code/parameter', fn () => response()->json(['ok' => true]));

    Route::middleware(['auth:fsa-sso-api', 'fsa-sso.client-code:FSA-DPS-CODE,FSA-OTHER-CODE'])
        ->get('/middleware/client-code/multiple', fn () => response()->json(['ok' => true]));
});

test('allowed client code passes', function () {
    config()->set('fsa-sso.api_auth.allowed_client_codes', ['FSA-DPS-CODE']);

    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(clientCodeClaims('FSA-DPS-CODE'));
    fakeClientCodeJwks($jwk);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/middleware/client-code/config')
        ->assertSuccessful();
});

test('disallowed client code returns forbidden', function () {
    config()->set('fsa-sso.api_auth.allowed_client_codes', ['FSA-OTHER-CODE']);

    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(clientCodeClaims('FSA-DPS-CODE'));
    fakeClientCodeJwks($jwk);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/middleware/client-code/config')
        ->assertForbidden()
        ->assertJson([
            'message' => 'Forbidden.',
        ]);
});

test('config allowlist works', function () {
    config()->set('fsa-sso.api_auth.allowed_client_codes', ['FSA-DPS-CODE', 'FSA-COMPENDIUM-CODE']);

    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(clientCodeClaims('FSA-COMPENDIUM-CODE'));
    fakeClientCodeJwks($jwk);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/middleware/client-code/config')
        ->assertSuccessful();
});

test('parameter based allowlist works', function () {
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(clientCodeClaims('FSA-DPS-CODE'));
    fakeClientCodeJwks($jwk);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/middleware/client-code/parameter')
        ->assertSuccessful();
});

test('multiple parameter client codes work', function () {
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(clientCodeClaims('FSA-OTHER-CODE'));
    fakeClientCodeJwks($jwk);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/middleware/client-code/multiple')
        ->assertSuccessful();
});

/**
 * @return array<string, mixed>
 */
function clientCodeClaims(string $clientCode): array
{
    return [
        'sub' => 'client-code-sub',
        'email' => 'client-code@example.com',
        'name' => 'Client Code User',
        'client_code' => $clientCode,
        'iss' => 'https://sso.example.test',
        'aud' => 'https://sso.example.test',
        'iat' => time() - 10,
        'exp' => time() + 300,
        'jti' => 'client-code-jti',
    ];
}

function fakeClientCodeJwks(array $jwk): void
{
    Http::fake([
        'https://sso.example.test/.well-known/jwks.json' => Http::response([
            'keys' => [$jwk],
        ]),
    ]);
}

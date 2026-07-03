<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PutheaKhem\FsaSso\Tests\TestUser;

beforeEach(function () {
    config()->set('fsa-sso.jwks_url', 'https://sso.example.test/.well-known/jwks.json');
    config()->set('fsa-sso.issuer', 'https://sso.example.test');
    config()->set('fsa-sso.audience', 'https://sso.example.test');
    config()->set('fsa-sso.api_auth.allowed_client_codes', ['FSA-DPS-CODE']);
    config()->set('fsa-sso.api_auth.auto_create_users', true);
    config()->set('fsa-sso.api_auth.mode', 'jwt');
    config()->set('fsa-sso.api_auth.introspection_url', 'https://sso.example.test/api/v1/auth/introspect');
    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    Route::middleware('auth:fsa-sso-api')->get('/guard/me', function (Illuminate\Http\Request $request) {
        /** @var PutheaKhem\FsaSso\Data\FsaSsoUserData $fsaSsoUser */
        $fsaSsoUser = $request->attributes->get('fsa_sso_user');

        return response()->json([
            'id' => auth('fsa-sso-api')->id(),
            'email' => auth('fsa-sso-api')->user()?->getAuthIdentifierName() ? auth('fsa-sso-api')->user()?->getAttribute('email') : null,
            'sub' => $fsaSsoUser->sub,
            'client_code' => $request->attributes->get('fsa_sso_client_code'),
            'jti' => $request->attributes->get('fsa_sso_jti'),
            'token_hash' => $request->attributes->get('fsa_sso_token_hash'),
            'api_guard_authenticated' => auth('api')->check(),
        ]);
    });
});

test('missing bearer token returns unauthenticated', function () {
    $this->getJson('/guard/me')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});

test('invalid token returns unauthenticated', function () {
    $this->withHeader('Authorization', 'Bearer invalid-token')
        ->getJson('/guard/me')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});

test('valid token authenticates user', function () {
    $user = TestUser::query()->create([
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'sso_id' => 'sso-existing-1',
    ]);

    $claims = validApiClaims([
        'sub' => 'sso-existing-1',
        'email' => 'existing@example.com',
    ]);

    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken($claims);

    fakeJwks($jwk);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/guard/me')
        ->assertSuccessful()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('email', 'existing@example.com')
        ->assertJsonPath('api_guard_authenticated', false);
});

test('user is resolved by sso id', function () {
    TestUser::query()->create([
        'name' => 'By SSO',
        'email' => 'by-sso@example.com',
        'sso_id' => 'sso-user-1',
    ]);

    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(validApiClaims([
        'sub' => 'sso-user-1',
        'email' => 'by-sso@example.com',
    ]));

    fakeJwks($jwk);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/guard/me')
        ->assertSuccessful()
        ->assertJsonPath('email', 'by-sso@example.com');
});

test('fallback by email works', function () {
    $user = TestUser::query()->create([
        'name' => 'Fallback Email',
        'email' => 'fallback@example.com',
        'sso_id' => null,
    ]);

    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(validApiClaims([
        'sub' => 'fallback-sub-1',
        'email' => 'fallback@example.com',
    ]));

    fakeJwks($jwk);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/guard/me')
        ->assertSuccessful()
        ->assertJsonPath('email', 'fallback@example.com');

    expect($user->fresh()->sso_id)->toBe('fallback-sub-1');
});

test('auto create user works when enabled', function () {
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(validApiClaims([
        'sub' => 'auto-create-sub',
        'email' => 'autocreate@example.com',
        'name' => 'Auto Create User',
    ]));

    fakeJwks($jwk);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/guard/me')
        ->assertSuccessful()
        ->assertJsonPath('email', 'autocreate@example.com');

    $this->assertDatabaseHas('users', [
        'email' => 'autocreate@example.com',
        'sso_id' => 'auto-create-sub',
    ]);
});

test('auto create user returns unauthenticated when disabled', function () {
    config()->set('fsa-sso.api_auth.auto_create_users', false);

    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(validApiClaims([
        'sub' => 'no-auto-create-sub',
        'email' => 'no-auto@example.com',
    ]));

    fakeJwks($jwk);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/guard/me')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});

test('request attributes are set', function () {
    TestUser::query()->create([
        'name' => 'Request Attr User',
        'email' => 'attrs@example.com',
        'sso_id' => 'attrs-sub-1',
    ]);

    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(validApiClaims([
        'sub' => 'attrs-sub-1',
        'email' => 'attrs@example.com',
        'jti' => 'attrs-jti-1',
    ]));

    fakeJwks($jwk);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/guard/me')
        ->assertSuccessful()
        ->assertJsonPath('sub', 'attrs-sub-1')
        ->assertJsonPath('client_code', 'FSA-DPS-CODE')
        ->assertJsonPath('jti', 'attrs-jti-1')
        ->assertJsonPath('token_hash', hash('sha256', $token));
});

test('unsupported algorithm produces debug log and unauthenticated response', function () {
    Log::spy();

    $token = Firebase\JWT\JWT::encode(
        validApiClaims(),
        str_repeat('shared-secret-', 6),
        'HS256',
        'fsa-test-key',
    );

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/guard/me')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) use ($token): bool {
        return $message === 'FSA SSO authentication rejected.'
            && ($context['reason'] ?? null) === 'unsupported_algorithm'
            && ($context['guard'] ?? null) === 'fsa-sso-api'
            && ($context['mode'] ?? null) === 'jwt'
            && ($context['token_hash'] ?? null) === hash('sha256', $token)
            && ! str_contains((string) json_encode($context), $token);
    })->once();
});

test('introspection mode authenticates opaque tokens without jwks parsing', function () {
    config()->set('fsa-sso.api_auth.mode', 'introspection');

    TestUser::query()->create([
        'name' => 'Opaque User',
        'email' => 'opaque@example.com',
        'sso_id' => 'opaque-sub-1',
    ]);

    Http::fake([
        'https://sso.example.test/api/v1/auth/introspect' => Http::response([
            'active' => true,
            'sub' => 'opaque-sub-1',
            'email' => 'opaque@example.com',
            'name' => 'Opaque User',
            'client_code' => 'FSA-DPS-CODE',
            'iss' => 'https://sso.example.test',
            'aud' => 'https://sso.example.test',
            'iat' => time() - 10,
            'exp' => time() + 300,
            'jti' => 'opaque-jti-1',
        ]),
    ]);

    $token = 'opaque-external-access-token';

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/guard/me')
        ->assertSuccessful()
        ->assertJsonPath('email', 'opaque@example.com')
        ->assertJsonPath('sub', 'opaque-sub-1')
        ->assertJsonPath('client_code', 'FSA-DPS-CODE')
        ->assertJsonPath('jti', 'opaque-jti-1')
        ->assertJsonPath('token_hash', hash('sha256', $token));

    Http::assertSentCount(1);
    Http::assertSent(fn (Illuminate\Http\Client\Request $request): bool => $request->url() === 'https://sso.example.test/api/v1/auth/introspect');
});

test('claim mapping config works', function () {
    config()->set('fsa-sso.api_auth.claims', [
        'sub' => 'subject',
        'client_code' => 'client_id',
        'jti' => 'token_id',
        'iss' => 'issuer',
        'aud' => 'audience',
        'iat' => 'issued_at',
        'exp' => 'expires_at',
        'email' => 'mail',
        'name' => 'display_name',
        'provider' => 'provider_name',
        'kyc_level' => 'kyc',
        'e_kyc' => 'electronic_kyc',
        'camdigikey_id' => 'camdigikey',
        'nbfs_id' => 'nbfs',
        'roles' => 'scopes',
    ]);

    TestUser::query()->create([
        'name' => 'Mapped User',
        'email' => 'mapped@example.com',
        'sso_id' => 'mapped-sub-1',
    ]);

    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken([
        'subject' => 'mapped-sub-1',
        'mail' => 'mapped@example.com',
        'display_name' => 'Mapped User',
        'provider_name' => 'camdigikey',
        'kyc' => 'kyc_verified',
        'electronic_kyc' => true,
        'camdigikey' => 'CDK-MAP-001',
        'nbfs' => 'NBFS-MAP-001',
        'client_id' => 'FSA-DPS-CODE',
        'scopes' => ['trm_analyst'],
        'issuer' => 'https://sso.example.test',
        'audience' => 'https://sso.example.test',
        'issued_at' => time() - 10,
        'expires_at' => time() + 300,
        'token_id' => 'mapped-jti-1',
    ]);

    fakeJwks($jwk);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/guard/me')
        ->assertSuccessful()
        ->assertJsonPath('sub', 'mapped-sub-1')
        ->assertJsonPath('client_code', 'FSA-DPS-CODE')
        ->assertJsonPath('jti', 'mapped-jti-1');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validApiClaims(array $overrides = []): array
{
    return array_merge([
        'sub' => 'api-user-sub',
        'email' => 'api-user@example.com',
        'name' => 'API User',
        'provider' => 'camdigikey',
        'kyc_level' => 'kyc_verified',
        'e_kyc' => true,
        'camdigikey_id' => 'CDK-001',
        'nbfs_id' => 'NBFS-001',
        'client_code' => 'FSA-DPS-CODE',
        'roles' => ['trm_analyst'],
        'iss' => 'https://sso.example.test',
        'aud' => 'https://sso.example.test',
        'iat' => time() - 10,
        'exp' => time() + 300,
        'jti' => 'api-jti-1',
    ], $overrides);
}

function fakeJwks(array $jwk): void
{
    Http::fake([
        'https://sso.example.test/.well-known/jwks.json' => Http::response([
            'keys' => [$jwk],
        ]),
    ]);
}

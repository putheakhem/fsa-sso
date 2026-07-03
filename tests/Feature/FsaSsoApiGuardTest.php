<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use PutheaKhem\FsaSso\Tests\TestUser;

beforeEach(function () {
    config()->set('fsa-sso.jwks_url', 'https://sso.example.test/.well-known/jwks.json');
    config()->set('fsa-sso.issuer', 'https://sso.example.test');
    config()->set('fsa-sso.audience', 'https://sso.example.test');
    config()->set('fsa-sso.api_auth.allowed_client_codes', ['FSA-DPS-CODE']);
    config()->set('fsa-sso.api_auth.auto_create_users', true);

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
        ->assertJsonPath('email', 'existing@example.com');
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

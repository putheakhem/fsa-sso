<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use PutheaKhem\FsaSso\Tests\TestUser;

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

/**
 * @return array{token: string, jwk: array<string, string>}
 */
function createSignedFsaToken(array $claims): array
{
    $keyPair = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($keyPair);
    $publicKey = sodium_crypto_sign_publickey($keyPair);

    $header = [
        'alg' => 'EdDSA',
        'kid' => 'fsa-test-key',
        'typ' => 'JWT',
    ];

    $headerEncoded = base64UrlEncode((string) json_encode($header, JSON_THROW_ON_ERROR));
    $payloadEncoded = base64UrlEncode((string) json_encode($claims, JSON_THROW_ON_ERROR));

    $signature = sodium_crypto_sign_detached($headerEncoded.'.'.$payloadEncoded, $secretKey);
    $token = $headerEncoded.'.'.$payloadEncoded.'.'.base64UrlEncode($signature);

    return [
        'token' => $token,
        'jwk' => [
            'kty' => 'OKP',
            'crv' => 'Ed25519',
            'x' => base64UrlEncode($publicKey),
            'kid' => 'fsa-test-key',
            'use' => 'sig',
            'alg' => 'EdDSA',
        ],
    ];
}

test('initiate endpoint returns correct login url for any frontend client', function () {
    config()->set('fsa-sso.frontend_url', 'http://localhost:4040');
    config()->set('fsa-sso.client_code', 'FSA-F9E25931EF19');

    $this->getJson('/auth/sso/initiate')
        ->assertSuccessful()
        ->assertJson([
            'loginUrl' => 'http://localhost:4040/auth/login?client_code=FSA-F9E25931EF19',
        ]);
});

test('verify endpoint validates token via jwks and upserts user', function () {
    config()->set('fsa-sso.jwks_url', 'http://localhost:3000/.well-known/jwks.json');
    config()->set('fsa-sso.issuer', 'http://localhost:3000');
    config()->set('fsa-sso.audience', 'http://localhost:3000');
    config()->set('fsa-sso.client_code', 'FSA-F9E25931EF19');

    $claims = [
        'sub' => 'clx1abc2def',
        'email' => 'sso-user@example.com',
        'name' => 'SSO User',
        'provider' => 'camdigikey',
        'kyc_level' => 'kyc_verified',
        'e_kyc' => true,
        'camdigikey_id' => 'CDK-123456',
        'nbfs_id' => 'FSA-000123',
        'client_code' => 'FSA-F9E25931EF19',
        'iss' => 'http://localhost:3000',
        'aud' => 'http://localhost:3000',
        'iat' => time() - 10,
        'exp' => time() + 300,
        'jti' => 'test-jti-123',
    ];

    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken($claims);

    Http::fake([
        'http://localhost:3000/.well-known/jwks.json' => Http::response([
            'keys' => [$jwk],
        ]),
    ]);

    $this->postJson('/auth/sso/verify', [
        'authToken' => $token,
    ])
        ->assertSuccessful()
        ->assertJsonPath('user.email', 'sso-user@example.com')
        ->assertJsonPath('user.sso_id', 'clx1abc2def');

    $this->assertDatabaseHas('users', [
        'email' => 'sso-user@example.com',
        'sso_id' => 'clx1abc2def',
        'sso_provider' => 'camdigikey',
        'kyc_level' => 'kyc_verified',
        'camdigikey_id' => 'CDK-123456',
        'nbfs_id' => 'FSA-000123',
    ]);
});

test('verify endpoint rejects wrong client code', function () {
    config()->set('fsa-sso.jwks_url', 'http://localhost:3000/.well-known/jwks.json');
    config()->set('fsa-sso.issuer', 'http://localhost:3000');
    config()->set('fsa-sso.audience', 'http://localhost:3000');
    config()->set('fsa-sso.client_code', 'FSA-EXPECTED');

    $claims = [
        'sub' => 'clx1abc2def',
        'email' => 'sso-user@example.com',
        'name' => 'SSO User',
        'provider' => 'google',
        'kyc_level' => 'non_kyc',
        'client_code' => 'FSA-OTHER',
        'iss' => 'http://localhost:3000',
        'aud' => 'http://localhost:3000',
        'iat' => time() - 10,
        'exp' => time() + 300,
        'jti' => 'test-jti-124',
    ];

    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken($claims);

    Http::fake([
        'http://localhost:3000/.well-known/jwks.json' => Http::response([
            'keys' => [$jwk],
        ]),
    ]);

    $this->postJson('/auth/sso/verify', [
        'authToken' => $token,
    ])
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Invalid or expired token.',
        ]);

    expect(TestUser::query()->where('sso_id', 'clx1abc2def')->exists())->toBeFalse();
});

test('introspect endpoint proxies active response', function () {
    Http::fake([
        'http://localhost:3000/api/v1/auth/introspect' => Http::response([
            'active' => true,
            'sub' => 'clx1abc2def',
        ]),
    ]);

    $this->withHeader('Authorization', 'Bearer test-token')
        ->postJson('/auth/sso/introspect')
        ->assertSuccessful()
        ->assertJson([
            'active' => true,
            'sub' => 'clx1abc2def',
        ]);
});

test('middleware authenticates request and attaches claims', function () {
    config()->set('fsa-sso.jwks_url', 'http://localhost:3000/.well-known/jwks.json');
    config()->set('fsa-sso.issuer', 'http://localhost:3000');
    config()->set('fsa-sso.audience', 'http://localhost:3000');
    config()->set('fsa-sso.client_code', 'FSA-F9E25931EF19');

    $claims = [
        'sub' => 'clx1abc2def',
        'email' => 'sso-user@example.com',
        'name' => 'SSO User',
        'provider' => 'camdigikey',
        'kyc_level' => 'kyc_verified',
        'client_code' => 'FSA-F9E25931EF19',
        'iss' => 'http://localhost:3000',
        'aud' => 'http://localhost:3000',
        'iat' => time() - 10,
        'exp' => time() + 300,
        'jti' => 'test-jti-125',
    ];

    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken($claims);

    Http::fake([
        'http://localhost:3000/.well-known/jwks.json' => Http::response([
            'keys' => [$jwk],
        ]),
    ]);

    Route::middleware('fsa-sso.auth')->get('/fsa-sso/protected', function (Request $request) {
        $verifiedClaims = $request->attributes->get('fsa_sso_claims', []);

        return response()->json([
            'sub' => $verifiedClaims['sub'] ?? null,
        ]);
    });

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/fsa-sso/protected')
        ->assertSuccessful()
        ->assertJson([
            'sub' => 'clx1abc2def',
        ]);
});

test('middleware rejects invalid bearer token', function () {
    Route::middleware('fsa-sso.auth')->get('/fsa-sso/protected-invalid', function () {
        return response()->json(['ok' => true]);
    });

    $this->withHeader('Authorization', 'Bearer invalid-token')
        ->getJson('/fsa-sso/protected-invalid')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Invalid or expired token.',
        ]);
});

<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use PutheaKhem\FsaSso\Auth\FsaSsoTokenValidator;
use PutheaKhem\FsaSso\Exceptions\ExpiredFsaSsoTokenException;
use PutheaKhem\FsaSso\Exceptions\InvalidFsaSsoTokenException;
use PutheaKhem\FsaSso\Exceptions\MissingFsaSsoClaimException;

beforeEach(function () {
    config()->set('fsa-sso.jwks_url', 'https://sso.example.test/.well-known/jwks.json');
    config()->set('fsa-sso.issuer', 'https://sso.example.test');
    config()->set('fsa-sso.audience', 'https://sso.example.test');
    config()->set('cache.default', 'array');
});

test('valid jwt passes', function () {
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(validatorClaims());

    fakeValidatorJwks($jwk);

    $data = app(FsaSsoTokenValidator::class)->validate($token);

    expect($data->sub)->toBe('validator-sub')
        ->and($data->clientCode)->toBe('FSA-DPS-CODE')
        ->and($data->roles)->toBe(['trm_analyst']);
});

test('expired jwt fails', function () {
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(validatorClaims([
        'exp' => time() - 5,
    ]));

    fakeValidatorJwks($jwk);

    expect(fn () => app(FsaSsoTokenValidator::class)->validate($token))
        ->toThrow(ExpiredFsaSsoTokenException::class);
});

test('invalid issuer fails', function () {
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(validatorClaims([
        'iss' => 'https://other-issuer.test',
    ]));

    fakeValidatorJwks($jwk);

    expect(fn () => app(FsaSsoTokenValidator::class)->validate($token))
        ->toThrow(InvalidFsaSsoTokenException::class);
});

test('invalid audience fails', function () {
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(validatorClaims([
        'aud' => 'https://other-audience.test',
    ]));

    fakeValidatorJwks($jwk);

    expect(fn () => app(FsaSsoTokenValidator::class)->validate($token))
        ->toThrow(InvalidFsaSsoTokenException::class);
});

test('invalid signature fails', function () {
    ['token' => $token] = createSignedFsaToken(validatorClaims());
    ['jwk' => $wrongJwk] = createSignedFsaToken(validatorClaims());

    fakeValidatorJwks($wrongJwk);

    expect(fn () => app(FsaSsoTokenValidator::class)->validate($token))
        ->toThrow(InvalidFsaSsoTokenException::class);
});

test('missing sub fails', function () {
    $claims = validatorClaims();
    unset($claims['sub']);

    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken($claims);

    fakeValidatorJwks($jwk);

    expect(fn () => app(FsaSsoTokenValidator::class)->validate($token))
        ->toThrow(MissingFsaSsoClaimException::class);
});

test('missing client code fails', function () {
    $claims = validatorClaims();
    unset($claims['client_code']);

    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken($claims);

    fakeValidatorJwks($jwk);

    expect(fn () => app(FsaSsoTokenValidator::class)->validate($token))
        ->toThrow(MissingFsaSsoClaimException::class);
});

test('non eddsa algorithm fails', function () {
    $token = JWT::encode(validatorClaims(), str_repeat('shared-secret-', 6), 'HS256', 'fsa-test-key');

    expect(fn () => app(FsaSsoTokenValidator::class)->validate($token))
        ->toThrow(InvalidFsaSsoTokenException::class);
});

test('jwks is cached', function () {
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken(validatorClaims());

    fakeValidatorJwks($jwk);

    app(FsaSsoTokenValidator::class)->validate($token);
    app(FsaSsoTokenValidator::class)->validate($token);

    Http::assertSentCount(1);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validatorClaims(array $overrides = []): array
{
    return array_merge([
        'sub' => 'validator-sub',
        'email' => 'validator@example.com',
        'name' => 'Validator User',
        'provider' => 'camdigikey',
        'kyc_level' => 'kyc_verified',
        'e_kyc' => true,
        'camdigikey_id' => 'CDK-VAL-001',
        'nbfs_id' => 'NBFS-VAL-001',
        'client_code' => 'FSA-DPS-CODE',
        'roles' => ['trm_analyst'],
        'iss' => 'https://sso.example.test',
        'aud' => 'https://sso.example.test',
        'iat' => time() - 10,
        'exp' => time() + 300,
        'jti' => 'validator-jti',
    ], $overrides);
}

function fakeValidatorJwks(array $jwk): void
{
    Http::fake([
        'https://sso.example.test/.well-known/jwks.json' => Http::response([
            'keys' => [$jwk],
        ]),
    ]);
}

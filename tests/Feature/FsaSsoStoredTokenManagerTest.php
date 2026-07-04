<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PutheaKhem\FsaSso\Services\FsaSsoStoredTokenManager;
use PutheaKhem\FsaSso\Services\FsaSsoTokenVerifier;
use PutheaKhem\FsaSso\Services\FsaSsoUserProvisioner;
use PutheaKhem\FsaSso\Tests\TestUser;

beforeEach(function () {
    config()->set('fsa-sso.jwks_url', 'http://localhost:3000/.well-known/jwks.json');
    config()->set('fsa-sso.issuer', 'http://localhost:3000');
    config()->set('fsa-sso.audience', 'http://localhost:3000');
    config()->set('fsa-sso.client_code', 'FSA-DPS-CODE');
});

test('token is saved with metadata when token storage is enabled', function () {
    config()->set('fsa-sso.token_storage.enabled', true);

    $claims = storedTokenClaims();
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken($claims);

    fakeJwksForStoredTokenTests($jwk);

    $user = verifyProvisionAndPersistStoredToken($token);

    $record = DB::table('users')->where('id', $user->getKey())->first([
        'fsa_sso_access_token',
        'fsa_sso_token_expires_at',
        'fsa_sso_token_client_code',
        'fsa_sso_token_last_used_at',
    ]);

    expect($record)->not->toBeNull()
        ->and($record->fsa_sso_access_token)->not->toBe($token)
        ->and($record->fsa_sso_token_client_code)->toBe('FSA-DPS-CODE')
        ->and(CarbonImmutable::parse((string) $record->fsa_sso_token_expires_at)->timestamp)->toBe($claims['exp'])
        ->and($record->fsa_sso_token_last_used_at)->toBeNull();
});

test('token is not saved when token storage is disabled', function () {
    config()->set('fsa-sso.token_storage.enabled', false);

    $claims = storedTokenClaims();
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken($claims);

    fakeJwksForStoredTokenTests($jwk);

    $user = verifyProvisionAndPersistStoredToken($token);

    $record = DB::table('users')->where('id', $user->getKey())->first([
        'fsa_sso_access_token',
        'fsa_sso_token_expires_at',
        'fsa_sso_token_client_code',
        'fsa_sso_token_last_used_at',
    ]);

    expect($record)->not->toBeNull()
        ->and($record->fsa_sso_access_token)->toBeNull()
        ->and($record->fsa_sso_token_expires_at)->toBeNull()
        ->and($record->fsa_sso_token_client_code)->toBeNull()
        ->and($record->fsa_sso_token_last_used_at)->toBeNull();
});

test('stored token retrieval works and can mark last used timestamp', function () {
    config()->set('fsa-sso.token_storage.enabled', true);

    $claims = storedTokenClaims();
    ['token' => $token, 'jwk' => $jwk] = createSignedFsaToken($claims);

    fakeJwksForStoredTokenTests($jwk);

    $user = verifyProvisionAndPersistStoredToken($token);

    $storedTokenManager = app(FsaSsoStoredTokenManager::class);

    expect($storedTokenManager->get($user))->toBe($token);
    expect($storedTokenManager->get($user, true))->toBe($token);

    $record = DB::table('users')->where('id', $user->getKey())->first([
        'fsa_sso_token_last_used_at',
    ]);

    expect($record)->not->toBeNull()
        ->and($record->fsa_sso_token_last_used_at)->not->toBeNull();
});

test('stored token expiration detection works', function () {
    config()->set('fsa-sso.token_storage.enabled', true);

    $expiredUser = TestUser::query()->create([
        'name' => 'Expired Token User',
        'email' => 'expired-token@example.com',
        'sso_id' => 'expired-sub',
        'fsa_sso_access_token' => Illuminate\Support\Facades\Crypt::encryptString('expired-token'),
        'fsa_sso_token_expires_at' => now()->subMinute(),
    ]);

    $activeUser = TestUser::query()->create([
        'name' => 'Active Token User',
        'email' => 'active-token@example.com',
        'sso_id' => 'active-sub',
        'fsa_sso_access_token' => Illuminate\Support\Facades\Crypt::encryptString('active-token'),
        'fsa_sso_token_expires_at' => now()->addMinute(),
    ]);

    $storedTokenManager = app(FsaSsoStoredTokenManager::class);

    expect($storedTokenManager->hasExpired($expiredUser))->toBeTrue();
    expect($storedTokenManager->hasExpired($activeUser))->toBeFalse();
});

/**
 * @return array<string, mixed>
 */
function storedTokenClaims(): array
{
    return [
        'sub' => 'shared-token-sub',
        'email' => 'shared-token@example.com',
        'name' => 'Shared Token User',
        'provider' => 'camdigikey',
        'kyc_level' => 'kyc_verified',
        'client_code' => 'FSA-DPS-CODE',
        'iss' => 'http://localhost:3000',
        'aud' => 'http://localhost:3000',
        'iat' => time() - 10,
        'exp' => time() + 300,
        'jti' => 'stored-token-jti-1',
    ];
}

/**
 * @param  array<string, string>  $jwk
 */
function fakeJwksForStoredTokenTests(array $jwk): void
{
    Http::fake([
        'http://localhost:3000/.well-known/jwks.json' => Http::response([
            'keys' => [$jwk],
        ]),
    ]);
}

function verifyProvisionAndPersistStoredToken(string $token): TestUser
{
    $claims = app(FsaSsoTokenVerifier::class)->verify($token);
    /** @var TestUser $user */
    $user = app(FsaSsoUserProvisioner::class)->upsert($claims);

    app(FsaSsoStoredTokenManager::class)->persist($user, $token, $claims);

    return $user;
}

<?php

declare(strict_types=1);

use PutheaKhem\FsaSso\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

function base64UrlEncode(string $value): string
{
    return mb_rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

/**
 * @param  array<string, mixed>  $claims
 * @return array{token: string, jwk: array<string, string>}
 */
function createSignedFsaToken(array $claims, ?string $algorithm = 'EdDSA'): array
{
    $keyPair = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($keyPair);
    $publicKey = sodium_crypto_sign_publickey($keyPair);
    $header = [
        'alg' => $algorithm ?? 'EdDSA',
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

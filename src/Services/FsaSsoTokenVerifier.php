<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PutheaKhem\FsaSso\Exceptions\FsaSsoTokenException;

final class FsaSsoTokenVerifier
{
    /**
     * @return array<string, mixed>
     */
    public function verify(string $token): array
    {
        [$headerB64, $payloadB64, $signatureB64] = $this->splitToken($token);

        $header = $this->decodeJsonSegment($headerB64, 'token header');
        $payload = $this->decodeJsonSegment($payloadB64, 'token payload');
        $signature = $this->base64UrlDecode($signatureB64);

        if (($header['alg'] ?? null) !== 'EdDSA') {
            throw new FsaSsoTokenException('Unsupported token algorithm. Expected EdDSA.');
        }

        $kid = $header['kid'] ?? null;

        if (! is_string($kid) || $kid === '') {
            throw new FsaSsoTokenException('Token kid is missing.');
        }

        $publicKey = $this->resolvePublicKey($kid);
        $signedData = $headerB64.'.'.$payloadB64;

        if (! sodium_crypto_sign_verify_detached($signature, $signedData, $publicKey)) {
            throw new FsaSsoTokenException('Invalid token signature.');
        }

        $this->validateStandardClaims($payload);

        return $payload;
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function splitToken(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new FsaSsoTokenException('Malformed JWT.');
        }

        return [$parts[0], $parts[1], $parts[2]];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonSegment(string $segment, string $context): array
    {
        $decoded = $this->base64UrlDecode($segment);
        $json = json_decode($decoded, true);

        if (! is_array($json)) {
            throw new FsaSsoTokenException("Unable to decode {$context}.");
        }

        return $json;
    }

    private function base64UrlDecode(string $value): string
    {
        $base64 = strtr($value, '-_', '+/');
        $remainder = strlen($base64) % 4;

        if ($remainder > 0) {
            $base64 .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($base64, true);

        if ($decoded === false) {
            throw new FsaSsoTokenException('Invalid base64url segment.');
        }

        return $decoded;
    }

    private function resolvePublicKey(string $kid): string
    {
        $jwksUrl = (string) config('fsa-sso.jwks_url');
        $ttl = (int) config('fsa-sso.jwks_cache_ttl_seconds', 600);
        $cacheKey = 'fsa-sso:jwks:'.md5($jwksUrl);

        $jwks = Cache::remember($cacheKey, now()->addSeconds($ttl), function () use ($jwksUrl): array {
            $response = Http::acceptJson()->timeout(10)->connectTimeout(5)->get($jwksUrl);
            $response->throw();

            $json = $response->json();

            if (! is_array($json)) {
                throw new FsaSsoTokenException('Invalid JWKS payload.');
            }

            return $json;
        });

        $keys = Arr::get($jwks, 'keys', []);

        if (! is_array($keys)) {
            throw new FsaSsoTokenException('JWKS keys are invalid.');
        }

        foreach ($keys as $key) {
            if (! is_array($key)) {
                continue;
            }

            if (($key['kid'] ?? null) !== $kid) {
                continue;
            }

            if (($key['kty'] ?? null) !== 'OKP' || ($key['crv'] ?? null) !== 'Ed25519') {
                throw new FsaSsoTokenException('JWKS key is not an Ed25519 key.');
            }

            $x = $key['x'] ?? null;

            if (! is_string($x) || $x === '') {
                throw new FsaSsoTokenException('JWKS key is missing x coordinate.');
            }

            return $this->base64UrlDecode($x);
        }

        throw new FsaSsoTokenException('Unable to find matching JWKS key.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateStandardClaims(array $payload): void
    {
        $issuer = (string) config('fsa-sso.issuer');
        $audience = config('fsa-sso.audience');
        $clientCode = config('fsa-sso.client_code');

        if ($issuer === '') {
            throw new FsaSsoTokenException('FSA SSO issuer is not configured.');
        }

        if (! is_string($audience) || $audience === '') {
            throw new FsaSsoTokenException('FSA SSO audience is not configured.');
        }

        if (($payload['iss'] ?? null) !== $issuer) {
            throw new FsaSsoTokenException('Invalid token issuer.');
        }

        if (! $this->audienceMatches($payload['aud'] ?? null, $audience)) {
            throw new FsaSsoTokenException('Invalid token audience.');
        }

        $exp = $payload['exp'] ?? null;

        if (! is_int($exp) && ! ctype_digit((string) $exp)) {
            throw new FsaSsoTokenException('Token exp claim is missing or invalid.');
        }

        if ((int) $exp <= time()) {
            throw new FsaSsoTokenException('Token has expired.');
        }

        if (is_string($clientCode) && $clientCode !== '') {
            if (($payload['client_code'] ?? null) !== $clientCode) {
                throw new FsaSsoTokenException('Token client code does not match this portal.');
            }
        }
    }

    private function audienceMatches(mixed $tokenAudience, mixed $expectedAudience): bool
    {
        if (! is_string($expectedAudience) || $expectedAudience === '') {
            return false;
        }

        if (is_string($tokenAudience)) {
            return $tokenAudience === $expectedAudience;
        }

        if (is_array($tokenAudience)) {
            return in_array($expectedAudience, $tokenAudience, true);
        }

        return false;
    }
}

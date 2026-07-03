<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Auth;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PutheaKhem\FsaSso\Data\FsaSsoUserData;
use PutheaKhem\FsaSso\Exceptions\ExpiredFsaSsoTokenException;
use PutheaKhem\FsaSso\Exceptions\InvalidFsaSsoTokenException;
use PutheaKhem\FsaSso\Exceptions\MissingFsaSsoClaimException;
use stdClass;
use Throwable;

final class FsaSsoTokenValidator
{
    public function validate(string $token): FsaSsoUserData
    {
        $algorithm = $this->extractAlgorithm($token);

        if ($algorithm !== 'EdDSA') {
            throw new InvalidFsaSsoTokenException('Unsupported token algorithm.');
        }

        try {
            $payload = JWT::decode($token, $this->resolveKeys());
        } catch (ExpiredException) {
            throw new ExpiredFsaSsoTokenException('Token has expired.');
        } catch (Throwable) {
            throw new InvalidFsaSsoTokenException('Invalid token.');
        }

        $claims = $this->normalizePayload($payload);
        $this->validateClaims($claims);

        return new FsaSsoUserData(
            sub: $this->requiredStringClaim($claims, 'sub'),
            email: $this->nullableString($claims['email'] ?? null),
            name: $this->nullableString($claims['name'] ?? null),
            provider: $this->nullableString($claims['provider'] ?? null),
            kycLevel: $this->nullableString($claims['kyc_level'] ?? null),
            eKyc: $this->nullableBool($claims['e_kyc'] ?? null),
            camdigikeyId: $this->nullableString($claims['camdigikey_id'] ?? null),
            nbfsId: $this->nullableString($claims['nbfs_id'] ?? null),
            clientCode: $this->requiredStringClaim($claims, 'client_code'),
            roles: $this->normalizeRoles($claims['roles'] ?? []),
            jti: $this->nullableString($claims['jti'] ?? null),
            exp: $this->requiredIntClaim($claims, 'exp'),
            iat: $this->requiredIntClaim($claims, 'iat'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePayload(stdClass $payload): array
    {
        /** @var array<string, mixed> $claims */
        $claims = json_decode((string) json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        return $claims;
    }

    private function extractAlgorithm(string $token): ?string
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new InvalidFsaSsoTokenException('Malformed JWT.');
        }

        $decodedHeader = JWT::jsonDecode(JWT::urlsafeB64Decode($segments[0]));

        if (! $decodedHeader instanceof stdClass) {
            throw new InvalidFsaSsoTokenException('Invalid token header.');
        }

        return is_string($decodedHeader->alg ?? null) ? $decodedHeader->alg : null;
    }

    /**
     * @return array<string, Key>
     */
    private function resolveKeys(): array
    {
        $jwksUrl = (string) config('fsa-sso.jwks_url');
        $cacheSeconds = (int) config('fsa-sso.api_auth.jwks_cache_seconds', 600);
        $cacheKey = 'fsa-sso:jwks:'.md5($jwksUrl);

        /** @var array{keys: array<int, array<string, mixed>>} $jwks */
        $jwks = Cache::remember($cacheKey, now()->addSeconds($cacheSeconds), function () use ($jwksUrl): array {
            $response = Http::acceptJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->retry(3, 200)
                ->get($jwksUrl)
                ->throw();

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new InvalidFsaSsoTokenException('Invalid JWKS payload.');
            }

            return $payload;
        });

        try {
            return JWK::parseKeySet($jwks, 'EdDSA');
        } catch (Throwable) {
            throw new InvalidFsaSsoTokenException('Unable to parse JWKS.');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function validateClaims(array $claims): void
    {
        $issuer = mb_trim((string) config('fsa-sso.issuer'));
        $audience = mb_trim((string) config('fsa-sso.audience'));

        if ($issuer === '' || ($claims['iss'] ?? null) !== $issuer) {
            throw new InvalidFsaSsoTokenException('Invalid token issuer.');
        }

        if ($audience === '' || ! $this->audienceMatches($claims['aud'] ?? null, $audience)) {
            throw new InvalidFsaSsoTokenException('Invalid token audience.');
        }

        if ($this->requiredIntClaim($claims, 'exp') <= time()) {
            throw new ExpiredFsaSsoTokenException('Token has expired.');
        }

        $this->requiredStringClaim($claims, 'sub');
        $this->requiredStringClaim($claims, 'client_code');
    }

    private function audienceMatches(mixed $tokenAudience, string $expectedAudience): bool
    {
        if (is_string($tokenAudience)) {
            return $tokenAudience === $expectedAudience;
        }

        if (is_array($tokenAudience)) {
            return in_array($expectedAudience, $tokenAudience, true);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function requiredStringClaim(array $claims, string $key): string
    {
        $value = $claims[$key] ?? null;

        if (! is_string($value) || mb_trim($value) === '') {
            throw new MissingFsaSsoClaimException(sprintf('Missing required claim [%s].', $key));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function requiredIntClaim(array $claims, string $key): int
    {
        $value = $claims[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new MissingFsaSsoClaimException(sprintf('Missing required claim [%s].', $key));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRoles(mixed $roles): array
    {
        if (! is_array($roles)) {
            return [];
        }

        return array_values(array_filter($roles, static fn (mixed $role): bool => is_string($role) && $role !== ''));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return null;
        }

        return $value;
    }

    private function nullableBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}

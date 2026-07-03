<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Auth;

use PutheaKhem\FsaSso\Data\FsaSsoUserData;
use PutheaKhem\FsaSso\Exceptions\ExpiredFsaSsoTokenException;
use PutheaKhem\FsaSso\Exceptions\InvalidFsaSsoTokenException;
use PutheaKhem\FsaSso\Exceptions\MissingFsaSsoClaimException;

final class FsaSsoClaimMapper
{
    public function claimKey(string $attribute): string
    {
        $key = config("fsa-sso.api_auth.claims.{$attribute}", $attribute);

        return is_string($key) && mb_trim($key) !== '' ? $key : $attribute;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function toUserData(array $claims): FsaSsoUserData
    {
        return new FsaSsoUserData(
            sub: $this->requiredStringClaim($claims, 'sub'),
            email: $this->nullableStringClaim($claims, 'email'),
            name: $this->nullableStringClaim($claims, 'name'),
            provider: $this->nullableStringClaim($claims, 'provider'),
            kycLevel: $this->nullableStringClaim($claims, 'kyc_level'),
            eKyc: $this->nullableBoolClaim($claims, 'e_kyc'),
            camdigikeyId: $this->nullableStringClaim($claims, 'camdigikey_id'),
            nbfsId: $this->nullableStringClaim($claims, 'nbfs_id'),
            clientCode: $this->requiredStringClaim($claims, 'client_code'),
            roles: $this->rolesClaim($claims),
            jti: $this->nullableStringClaim($claims, 'jti'),
            exp: $this->requiredIntClaim($claims, 'exp'),
            iat: $this->requiredIntClaim($claims, 'iat'),
        );
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function validateStandardClaims(array $claims): void
    {
        $issuer = mb_trim((string) config('fsa-sso.issuer'));
        $audience = mb_trim((string) config('fsa-sso.audience'));

        if ($issuer === '' || $this->claimValue($claims, 'iss') !== $issuer) {
            throw new InvalidFsaSsoTokenException('Invalid token issuer.');
        }

        if ($audience === '' || ! $this->audienceMatches($this->claimValue($claims, 'aud'), $audience)) {
            throw new InvalidFsaSsoTokenException('Invalid token audience.');
        }

        if ($this->requiredIntClaim($claims, 'exp') <= time()) {
            throw new ExpiredFsaSsoTokenException('Token has expired.');
        }

        $this->requiredStringClaim($claims, 'sub');
        $this->requiredStringClaim($claims, 'client_code');
        $this->requiredIntClaim($claims, 'iat');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function requiredStringClaim(array $claims, string $attribute): string
    {
        $value = $this->claimValue($claims, $attribute);

        if (! is_string($value) || mb_trim($value) === '') {
            throw new MissingFsaSsoClaimException(sprintf('Missing required claim [%s].', $this->claimKey($attribute)));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function requiredIntClaim(array $claims, string $attribute): int
    {
        $value = $this->claimValue($claims, $attribute);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new MissingFsaSsoClaimException(sprintf('Missing required claim [%s].', $this->claimKey($attribute)));
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function nullableStringClaim(array $claims, string $attribute): ?string
    {
        $value = $this->claimValue($claims, $attribute);

        if (! is_string($value) || mb_trim($value) === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function nullableBoolClaim(array $claims, string $attribute): ?bool
    {
        $value = $this->claimValue($claims, $attribute);

        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return array<int, string>
     */
    public function rolesClaim(array $claims): array
    {
        $roles = $this->claimValue($claims, 'roles');

        if (! is_array($roles)) {
            return [];
        }

        return array_values(array_filter($roles, static fn (mixed $role): bool => is_string($role) && $role !== ''));
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function claimValue(array $claims, string $attribute): mixed
    {
        return $claims[$this->claimKey($attribute)] ?? null;
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
}

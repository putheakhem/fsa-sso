<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Data;

/**
 * @phpstan-type FsaSsoUserClaims array{
 *     sub: string,
 *     email: ?string,
 *     name: ?string,
 *     provider: ?string,
 *     kyc_level: ?string,
 *     e_kyc: ?bool,
 *     camdigikey_id: ?string,
 *     nbfs_id: ?string,
 *     client_code: string,
 *     roles: array<int, string>,
 *     jti: ?string,
 *     exp: int,
 *     iat: int
 * }
 */
final readonly class FsaSsoUserData
{
    /**
     * @param  array<int, string>  $roles
     */
    public function __construct(
        public string $sub,
        public ?string $email,
        public ?string $name,
        public ?string $provider,
        public ?string $kycLevel,
        public ?bool $eKyc,
        public ?string $camdigikeyId,
        public ?string $nbfsId,
        public string $clientCode,
        public array $roles,
        public ?string $jti,
        public int $exp,
        public int $iat,
    ) {}

    public function isKycVerified(): bool
    {
        return $this->kycLevel === 'kyc_verified';
    }

    public function hasCamDigiKey(): bool
    {
        return $this->camdigikeyId !== null && $this->camdigikeyId !== '';
    }

    /**
     * @return FsaSsoUserClaims
     */
    public function toArray(): array
    {
        return [
            'sub' => $this->sub,
            'email' => $this->email,
            'name' => $this->name,
            'provider' => $this->provider,
            'kyc_level' => $this->kycLevel,
            'e_kyc' => $this->eKyc,
            'camdigikey_id' => $this->camdigikeyId,
            'nbfs_id' => $this->nbfsId,
            'client_code' => $this->clientCode,
            'roles' => $this->roles,
            'jti' => $this->jti,
            'exp' => $this->exp,
            'iat' => $this->iat,
        ];
    }
}

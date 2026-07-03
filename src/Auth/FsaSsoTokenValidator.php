<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Auth;

use PutheaKhem\FsaSso\Data\FsaSsoUserData;
use PutheaKhem\FsaSso\Exceptions\InvalidFsaSsoTokenException;

final class FsaSsoTokenValidator implements FsaSsoTokenValidatorInterface
{
    public function __construct(
        private EdDsaJwtTokenValidator $jwtTokenValidator,
        private IntrospectionTokenValidator $introspectionTokenValidator,
    ) {}

    public function validate(string $token): FsaSsoUserData
    {
        return match ($this->mode()) {
            'jwt' => $this->jwtTokenValidator->validate($token),
            'introspection' => $this->introspectionTokenValidator->validate($token),
            default => throw new InvalidFsaSsoTokenException('Unsupported API auth mode.'),
        };
    }

    private function mode(): string
    {
        return mb_strtolower((string) config('fsa-sso.api_auth.mode', 'jwt'));
    }
}

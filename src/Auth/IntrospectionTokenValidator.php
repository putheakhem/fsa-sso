<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Auth;

use PutheaKhem\FsaSso\Data\FsaSsoUserData;
use PutheaKhem\FsaSso\Exceptions\InvalidFsaSsoTokenException;

final class IntrospectionTokenValidator implements FsaSsoTokenValidatorInterface
{
    public function __construct(
        private FsaSsoTokenIntrospector $tokenIntrospector,
        private FsaSsoClaimMapper $claimMapper,
    ) {}

    public function validate(string $token): FsaSsoUserData
    {
        $claims = $this->tokenIntrospector->introspect($token);

        if (($claims['active'] ?? false) !== true) {
            throw new InvalidFsaSsoTokenException('Token is inactive.');
        }

        $this->claimMapper->validateStandardClaims($claims);

        return $this->claimMapper->toUserData($claims);
    }
}

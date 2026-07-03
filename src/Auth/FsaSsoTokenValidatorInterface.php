<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Auth;

use PutheaKhem\FsaSso\Data\FsaSsoUserData;

interface FsaSsoTokenValidatorInterface
{
    public function validate(string $token): FsaSsoUserData;
}

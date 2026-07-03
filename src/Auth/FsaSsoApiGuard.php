<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use PutheaKhem\FsaSso\Data\FsaSsoUserData;
use Throwable;

final class FsaSsoApiGuard implements Guard
{
    private ?Authenticatable $user = null;

    private bool $resolved = false;

    public function __construct(
        private Request $request,
        private FsaSsoTokenValidator $validator,
        private FsaSsoUserResolver $resolver,
    ) {}

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function user(): ?Authenticatable
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $token = $this->request->bearerToken();

        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $fsaSsoUserData = $this->validator->validate($token);
            $this->storeRequestAttributes($token, $fsaSsoUserData);
            $this->user = $this->resolver->resolve($fsaSsoUserData);
        } catch (Throwable) {
            $this->user = null;
        }

        return $this->user;
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        $token = $credentials['token'] ?? $credentials['bearer_token'] ?? null;

        if (! is_string($token) || $token === '') {
            return false;
        }

        try {
            $userData = $this->validator->validate($token);

            return $this->resolver->resolve($userData) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;
        $this->resolved = true;

        return $this;
    }

    public function setRequest(Request $request): static
    {
        $this->request = $request;
        $this->resolved = false;
        $this->user = null;

        return $this;
    }

    private function storeRequestAttributes(string $rawToken, FsaSsoUserData $fsaSsoUserData): void
    {
        $this->request->attributes->set('fsa_sso_user', $fsaSsoUserData);
        $this->request->attributes->set('fsa_sso_client_code', $fsaSsoUserData->clientCode);
        $this->request->attributes->set('fsa_sso_jti', $fsaSsoUserData->jti);
        $this->request->attributes->set('fsa_sso_token_hash', hash('sha256', $rawToken));
    }
}

<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Services;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

final class FsaSsoManager
{
    public function __construct(
        private FsaSsoTokenVerifier $tokenVerifier,
        private FsaSsoUserProvisioner $userProvisioner,
        private FsaSsoStoredTokenManager $storedTokenManager,
    ) {}

    /**
     * @return array{loginUrl: string}
     */
    public function getLoginUrl(): array
    {
        $frontendUrl = mb_rtrim((string) config('fsa-sso.frontend_url'), '/');
        $clientCode = (string) config('fsa-sso.client_code');

        return [
            'loginUrl' => $frontendUrl.'/auth/login?client_code='.urlencode($clientCode),
        ];
    }

    /**
     * @return array{user: Authenticatable, claims: array<string,mixed>, accessToken?: string}
     */
    public function verifyAndProvision(string $authToken): array
    {
        $claims = $this->tokenVerifier->verify($authToken);
        $user = $this->userProvisioner->upsert($claims);
        $this->storedTokenManager->persist($user, $authToken, $claims);

        $response = [
            'user' => $user,
        ];

        if ((bool) config('fsa-sso.include_claims_in_response', true)) {
            $response['claims'] = $claims;
        }

        if ((bool) config('fsa-sso.return_access_token', false)) {
            $response['accessToken'] = $authToken;
        }

        return $response;
    }

    public function storedTokenForUser(Authenticatable $user, bool $markAsUsed = false): ?string
    {
        return $this->storedTokenManager->get($user, $markAsUsed);
    }

    public function storedTokenForCurrentUser(?string $guard = null, bool $markAsUsed = false): ?string
    {
        $user = Auth::guard($guard)->user();

        if (! $user instanceof Authenticatable) {
            return null;
        }

        return $this->storedTokenManager->get($user, $markAsUsed);
    }

    public function storedTokenHasExpired(Authenticatable $user): bool
    {
        return $this->storedTokenManager->hasExpired($user);
    }

    public function markStoredTokenAsUsed(Authenticatable $user): void
    {
        $this->storedTokenManager->markAsUsed($user);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function introspect(string $token): array
    {
        $baseUrl = mb_rtrim((string) config('fsa-sso.api_base_url'), '/');

        $response = Http::acceptJson()
            ->timeout(10)
            ->connectTimeout(5)
            ->retry(3, fn (int $attempt, Exception $exception): int => $attempt * 200)
            ->withToken($token)
            ->post($baseUrl.'/api/v1/auth/introspect', [
                'token' => $token,
            ])
            ->throw();

        $payload = $response->json();

        return is_array($payload) ? $payload : ['active' => false];
    }

    /**
     * @throws RequestException
     */
    public function revoke(string $token): void
    {
        $baseUrl = mb_rtrim((string) config('fsa-sso.api_base_url'), '/');

        Http::acceptJson()
            ->timeout(10)
            ->connectTimeout(5)
            ->retry(3, fn (int $attempt, Exception $exception): int => $attempt * 200)
            ->withToken($token)
            ->post($baseUrl.'/api/v1/auth/revoke')
            ->throw();
    }
}

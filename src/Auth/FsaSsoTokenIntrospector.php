<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PutheaKhem\FsaSso\Exceptions\InvalidFsaSsoTokenException;
use Throwable;

final class FsaSsoTokenIntrospector
{
    public function __construct(private FsaSsoClaimMapper $claimMapper) {}

    /**
     * @return array<string, mixed>
     */
    public function introspect(string $token, ?string $jti = null): array
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = Cache::remember(
                $this->cacheKey($token, $jti),
                now()->addSeconds((int) config('fsa-sso.api_auth.introspection_cache_seconds', 120)),
                fn (): array => $this->fetch($token),
            );

            $resolvedJti = $payload[$this->claimMapper->claimKey('jti')] ?? null;

            if ($jti === null && is_string($resolvedJti) && $resolvedJti !== '') {
                Cache::put(
                    $this->cacheKey($token, $resolvedJti),
                    $payload,
                    now()->addSeconds((int) config('fsa-sso.api_auth.introspection_cache_seconds', 120)),
                );
            }

            return $payload;
        } catch (InvalidFsaSsoTokenException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidFsaSsoTokenException('Unable to introspect token.', previous: $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $token): array
    {
        $response = Http::acceptJson()
            ->timeout(10)
            ->connectTimeout(5)
            ->retry(3, 200)
            ->withToken($token)
            ->post((string) config('fsa-sso.api_auth.introspection_url'), [
                'token' => $token,
            ])
            ->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new InvalidFsaSsoTokenException('Unable to introspect token.');
        }

        return $payload;
    }

    private function cacheKey(string $token, ?string $jti): string
    {
        if (is_string($jti) && $jti !== '') {
            return 'fsa-sso:introspection:jti:'.$jti;
        }

        return 'fsa-sso:introspection:token:'.hash('sha256', $token);
    }
}

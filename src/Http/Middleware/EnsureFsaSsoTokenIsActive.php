<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EnsureFsaSsoTokenIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->attributes->get('fsa_sso_token');

        if (! is_string($token) || $token === '') {
            $token = $request->bearerToken();
        }

        if (! is_string($token) || $token === '') {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('fsa_sso_token', $token);

        try {
            $payload = Cache::remember(
                $this->cacheKey($request, $token),
                now()->addSeconds((int) config('fsa-sso.api_auth.introspection_cache_seconds', 120)),
                fn (): array => $this->introspect($token),
            );
        } catch (Throwable) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        if (($payload['active'] ?? false) !== true) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function introspect(string $token): array
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

        return is_array($payload) ? $payload : ['active' => false];
    }

    private function cacheKey(Request $request, string $token): string
    {
        $jti = $request->attributes->get('fsa_sso_jti');

        if (is_string($jti) && $jti !== '') {
            return 'fsa-sso:introspection:jti:'.$jti;
        }

        return 'fsa-sso:introspection:token:'.hash('sha256', $token);
    }
}

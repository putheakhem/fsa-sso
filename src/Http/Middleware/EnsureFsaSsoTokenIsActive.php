<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PutheaKhem\FsaSso\Auth\FsaSsoAuthFailureLogger;
use PutheaKhem\FsaSso\Auth\FsaSsoClaimMapper;
use PutheaKhem\FsaSso\Auth\FsaSsoTokenIntrospector;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EnsureFsaSsoTokenIsActive
{
    public function __construct(
        private FsaSsoTokenIntrospector $tokenIntrospector,
        private FsaSsoClaimMapper $claimMapper,
        private FsaSsoAuthFailureLogger $authFailureLogger,
    ) {}

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
            $payload = $this->tokenIntrospector->introspect(
                token: $token,
                jti: is_string($request->attributes->get('fsa_sso_jti'))
                    ? $request->attributes->get('fsa_sso_jti')
                    : null,
            );
        } catch (Throwable $exception) {
            $this->authFailureLogger->logThrowable($exception, $token, [
                'middleware' => 'fsa-sso.introspect',
                'path' => $request->path(),
            ]);

            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        if (($payload['active'] ?? false) !== true) {
            $this->authFailureLogger->log('inactive_token', $token, [
                'middleware' => 'fsa-sso.introspect',
                'path' => $request->path(),
            ]);

            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        $jtiClaimKey = $this->claimMapper->claimKey('jti');
        $jti = $payload[$jtiClaimKey] ?? null;

        if (is_string($jti) && $jti !== '') {
            $request->attributes->set('fsa_sso_jti', $jti);
        }

        return $next($request);
    }
}

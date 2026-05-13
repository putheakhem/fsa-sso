<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PutheaKhem\FsaSso\Exceptions\FsaSsoTokenException;
use PutheaKhem\FsaSso\Services\FsaSsoTokenVerifier;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateFsaSsoToken
{
    public function __construct(private FsaSsoTokenVerifier $tokenVerifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return new JsonResponse([
                'message' => 'No token provided.',
            ], 401);
        }

        try {
            $claims = $this->tokenVerifier->verify($token);
            $request->attributes->set('fsa_sso_claims', $claims);

            return $next($request);
        } catch (FsaSsoTokenException) {
            return new JsonResponse([
                'message' => 'Invalid or expired token.',
            ], 401);
        }
    }
}

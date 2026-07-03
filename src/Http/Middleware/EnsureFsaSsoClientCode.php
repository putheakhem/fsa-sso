<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PutheaKhem\FsaSso\Data\FsaSsoUserData;
use Symfony\Component\HttpFoundation\Response;

final class EnsureFsaSsoClientCode
{
    public function handle(Request $request, Closure $next, string ...$clientCodes): Response
    {
        $fsaSsoUser = $request->attributes->get('fsa_sso_user');

        if (! $fsaSsoUser instanceof FsaSsoUserData) {
            return new JsonResponse(['message' => 'Forbidden.'], 403);
        }

        $allowedClientCodes = $this->normalizeAllowedClientCodes($clientCodes);

        if ($allowedClientCodes === []) {
            $allowedClientCodes = $this->normalizeAllowedClientCodes((array) config('fsa-sso.api_auth.allowed_client_codes', []));
        }

        if (! in_array($fsaSsoUser->clientCode, $allowedClientCodes, true)) {
            return new JsonResponse(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }

    /**
     * @param  array<int, string>  $clientCodes
     * @return array<int, string>
     */
    private function normalizeAllowedClientCodes(array $clientCodes): array
    {
        $normalizedClientCodes = [];

        foreach ($clientCodes as $clientCodeGroup) {
            foreach (explode(',', $clientCodeGroup) as $clientCode) {
                $trimmedClientCode = mb_trim($clientCode);

                if ($trimmedClientCode !== '') {
                    $normalizedClientCodes[] = $trimmedClientCode;
                }
            }
        }

        return array_values(array_unique($normalizedClientCodes));
    }
}

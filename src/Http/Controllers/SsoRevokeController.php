<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PutheaKhem\FsaSso\Services\FsaSsoManager;
use Throwable;

final class SsoRevokeController
{
    public function __construct(private FsaSsoManager $manager) {}

    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json([
                'message' => 'No token provided.',
            ], 401);
        }

        try {
            $this->manager->revoke($token);

            return response()->json([], 204);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Unable to revoke token.',
            ], 502);
        }
    }
}

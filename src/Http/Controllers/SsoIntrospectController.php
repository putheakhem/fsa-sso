<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Http\Controllers;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PutheaKhem\FsaSso\Services\FsaSsoManager;
use Throwable;

final class SsoIntrospectController
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
            return response()->json($this->manager->introspect($token));
        } catch (RequestException $exception) {
            report($exception);

            return response()->json([
                'active' => false,
            ], 502);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'active' => false,
            ], 500);
        }
    }
}

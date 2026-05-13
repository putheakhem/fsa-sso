<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Http\Controllers;

use Illuminate\Http\JsonResponse;
use PutheaKhem\FsaSso\Exceptions\FsaSsoTokenException;
use PutheaKhem\FsaSso\Http\Requests\VerifyFsaSsoTokenRequest;
use PutheaKhem\FsaSso\Services\FsaSsoManager;
use Throwable;

final class SsoVerifyController
{
    public function __construct(private FsaSsoManager $manager) {}

    public function __invoke(VerifyFsaSsoTokenRequest $request): JsonResponse
    {
        try {
            $result = $this->manager->verifyAndProvision((string) $request->string('authToken'));

            return response()->json($result);
        } catch (FsaSsoTokenException) {
            return response()->json([
                'message' => 'Invalid or expired token.',
            ], 401);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Unable to verify SSO token.',
            ], 500);
        }
    }
}

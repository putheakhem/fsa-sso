<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Throwable;
use PutheaKhem\FsaSso\Services\FsaSsoManager;

final class SsoWebCallbackController
{
    public function __construct(private FsaSsoManager $manager) {}

    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        $token = $this->resolveToken($request);

        if ($token === null) {
            return $this->failureResponse($request, 'Missing authentication token', 422);
        }

        try {
            $result = $this->manager->verifyAndProvision($token);
            $user = $result['user'] ?? null;

            if ($user === null) {
                return $this->failureResponse($request, 'User provisioning failed', 422);
            }

            Auth::guard((string) config('fsa-sso.web_guard', 'web'))->login($user, remember: true);
            $request->session()->regenerate();

            $intendedRoute = (string) config('fsa-sso.web_intended_route', 'dashboard');
            if ($intendedRoute !== '' && Route::has($intendedRoute)) {
                return redirect()->intended(route($intendedRoute, absolute: false));
            }

            return redirect()->intended('/');
        } catch (Throwable $exception) {
            return $this->failureResponse($request, 'Authentication failed: '.$exception->getMessage(), 401);
        }
    }

    private function resolveToken(Request $request): ?string
    {
        foreach (['authToken', 'token', 'access_token', 'code'] as $key) {
            $token = $request->string($key)->trim()->value();

            if ($token !== '') {
                return $token;
            }
        }

        return null;
    }

    private function failureResponse(Request $request, string $message, int $status): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], $status);
        }

        return redirect((string) config('fsa-sso.web_failure_redirect', '/login'))
            ->with('error', $message);
    }
}

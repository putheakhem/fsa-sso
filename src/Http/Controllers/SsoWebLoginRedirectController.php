<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use PutheaKhem\FsaSso\Services\FsaSsoManager;

final class SsoWebLoginRedirectController
{
    public function __construct(private FsaSsoManager $manager) {}

    public function __invoke(): RedirectResponse
    {
        $response = $this->manager->getLoginUrl();

        if (! isset($response['loginUrl']) || ! is_string($response['loginUrl'])) {
            return redirect((string) config('fsa-sso.web_failure_redirect', '/login'))
                ->with('error', 'Could not get FSA SSO login URL.');
        }

        return redirect()->away($response['loginUrl']);
    }
}

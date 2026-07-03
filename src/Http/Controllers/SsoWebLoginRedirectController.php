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

        return redirect()->away($response['loginUrl']);
    }
}

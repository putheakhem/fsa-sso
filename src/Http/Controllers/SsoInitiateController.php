<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Http\Controllers;

use Illuminate\Http\JsonResponse;
use PutheaKhem\FsaSso\Services\FsaSsoManager;

final class SsoInitiateController
{
    public function __construct(private FsaSsoManager $manager) {}

    public function __invoke(): JsonResponse
    {
        return response()->json($this->manager->getLoginUrl());
    }
}

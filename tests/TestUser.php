<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class TestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

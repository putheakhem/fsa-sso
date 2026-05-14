<?php

declare(strict_types=1);

use PutheaKhem\FsaSso\Exceptions\FsaSsoTokenException;
use PutheaKhem\FsaSso\Services\FsaSsoUserProvisioner;
use PutheaKhem\FsaSso\Tests\TestUser;

test('user provisioner accepts env escaped user model class', function () {
    config()->set('fsa-sso.user_model', 'PutheaKhem\\\\FsaSso\\\\Tests\\\\TestUser');

    $user = app(FsaSsoUserProvisioner::class)->upsert([
        'sub' => 'test-sso-id-1',
        'email' => 'escaped-model@example.com',
        'name' => 'Escaped Model User',
    ]);

    expect($user)->toBeInstanceOf(TestUser::class)
        ->and($user->getAttribute('sso_id'))->toBe('test-sso-id-1')
        ->and($user->getAttribute('email'))->toBe('escaped-model@example.com');
});

test('user provisioner throws clear error for missing model class', function () {
    config()->set('fsa-sso.user_model', 'App\\\\Models\\\\MissingUserModel');

    expect(fn () => app(FsaSsoUserProvisioner::class)->upsert([
        'sub' => 'test-sso-id-2',
    ]))->toThrow(
        FsaSsoTokenException::class,
        'Configured FSA SSO user model class was not found: App\\\\Models\\\\MissingUserModel',
    );
});

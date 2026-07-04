<?php

declare(strict_types=1);

use PutheaKhem\FsaSso\FsaSsoServiceProvider;

test('published migrations are excluded from package loading and republishing', function () {
    $publishedMigrationPath = database_path('migrations/2026_07_04_000000_add_last_sso_login_at_to_users_table.php');

    file_put_contents($publishedMigrationPath, <<<'PHP'
<?php

declare(strict_types=1);
PHP);

    try {
        $provider = new FsaSsoServiceProvider($this->app);

        $migrationPathsToLoad = invokeFsaSsoServiceProviderMethod($provider, 'migrationPathsToLoad');
        $migrationPathsToPublish = invokeFsaSsoServiceProviderMethod($provider, 'migrationPathsToPublish');

        expect($migrationPathsToLoad)
            ->toHaveCount(1)
            ->and($migrationPathsToLoad[0])->toEndWith('add_fsa_sso_columns_to_users_table.php');

        expect(array_keys($migrationPathsToPublish))
            ->toHaveCount(1)
            ->and(array_keys($migrationPathsToPublish)[0])->toEndWith('add_fsa_sso_columns_to_users_table.php');

        expect(array_values($migrationPathsToPublish))
            ->toHaveCount(1)
            ->and(array_values($migrationPathsToPublish)[0])->toEndWith('add_fsa_sso_columns_to_users_table.php');
    } finally {
        if (is_file($publishedMigrationPath)) {
            unlink($publishedMigrationPath);
        }
    }
});

function invokeFsaSsoServiceProviderMethod(FsaSsoServiceProvider $provider, string $method): mixed
{
    return Closure::bind(
        fn () => $this->{$method}(),
        $provider,
        FsaSsoServiceProvider::class,
    )();
}

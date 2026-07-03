<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use PutheaKhem\FsaSso\Data\FsaSsoUserData;
use PutheaKhem\FsaSso\Exceptions\FsaSsoTokenException;

final class FsaSsoUserResolver
{
    /**
     * @var array<string, bool>
     */
    private static array $columnCache = [];

    public function resolve(FsaSsoUserData $data): ?Authenticatable
    {
        $userModelClass = $this->resolveUserModelClass();

        /** @var Model&Authenticatable $model */
        $model = new $userModelClass();

        $columns = config('fsa-sso.columns', []);
        $ssoIdColumn = (string) ($columns['sso_id'] ?? 'sso_id');
        $emailColumn = (string) ($columns['email'] ?? 'email');

        /** @var (Model&Authenticatable)|null $user */
        $user = $model->newQuery()->where($ssoIdColumn, $data->sub)->first();

        if (! $user && $data->email !== null) {
            /** @var (Model&Authenticatable)|null $matchedByEmail */
            $matchedByEmail = $model->newQuery()->where($emailColumn, $data->email)->first();

            if ($matchedByEmail) {
                $existingSsoId = $matchedByEmail->getAttribute($ssoIdColumn);

                if (is_string($existingSsoId) && $existingSsoId !== '' && $existingSsoId !== $data->sub) {
                    return null;
                }

                $user = $matchedByEmail;
            }
        }

        $wasRecentlyCreated = false;

        if (! $user && ! (bool) config('fsa-sso.api_auth.auto_create_users', true)) {
            return null;
        }

        if (! $user) {
            /** @var Model&Authenticatable $user */
            $user = new $userModelClass();
            $this->fillUserAttributes($user, $data, isNewUser: true);
            $user->save();
            $wasRecentlyCreated = true;
        } else {
            $this->fillUserAttributes($user, $data, isNewUser: false);
            $user->save();
        }

        if ($wasRecentlyCreated) {
            $this->assignDefaultRole($user);
        }

        return $user;
    }

    /**
     * @param  Model&Authenticatable  $user
     */
    private function fillUserAttributes(Model $user, FsaSsoUserData $data, bool $isNewUser): void
    {
        $columns = config('fsa-sso.columns', []);
        $ssoIdColumn = (string) ($columns['sso_id'] ?? 'sso_id');
        $emailColumn = (string) ($columns['email'] ?? 'email');
        $nameColumn = (string) ($columns['name'] ?? 'name');
        $providerColumn = (string) ($columns['sso_provider'] ?? 'sso_provider');
        $kycLevelColumn = (string) ($columns['kyc_level'] ?? 'kyc_level');
        $camDigiKeyIdColumn = (string) ($columns['camdigikey_id'] ?? 'camdigikey_id');
        $nbfsIdColumn = (string) ($columns['nbfs_id'] ?? 'nbfs_id');
        $lastSsoLoginAtColumn = (string) ($columns['last_sso_login_at'] ?? 'last_sso_login_at');

        $this->setAttributeWhenColumnExists($user, $ssoIdColumn, $data->sub);
        $this->setAttributeWhenColumnExists($user, $providerColumn, $data->provider);
        $this->setAttributeWhenColumnExists($user, $kycLevelColumn, $data->kycLevel);
        $this->setAttributeWhenColumnExists($user, $camDigiKeyIdColumn, $data->camdigikeyId);
        $this->setAttributeWhenColumnExists($user, $nbfsIdColumn, $data->nbfsId);
        $this->setAttributeWhenColumnExists($user, $lastSsoLoginAtColumn, now());

        if ($this->modelHasColumn($user, $emailColumn) && $data->email !== null) {
            $user->setAttribute($emailColumn, $data->email);
        }

        if ($this->modelHasColumn($user, $nameColumn)) {
            $name = $data->name ?? ($isNewUser ? 'FSA SSO User' : $user->getAttribute($nameColumn));

            if (is_string($name) && $name !== '') {
                $user->setAttribute($nameColumn, $name);
            }
        }

        if ($isNewUser && $this->modelHasColumn($user, 'password')) {
            $user->setAttribute('password', null);
        }
    }

    /**
     * @param  Model&Authenticatable  $user
     */
    private function assignDefaultRole(Model $user): void
    {
        $defaultRole = mb_trim((string) config('fsa-sso.api_auth.default_role', 'external-api-user'));

        if ($defaultRole === '' || ! method_exists($user, 'assignRole')) {
            return;
        }

        $user->assignRole($defaultRole);
    }

    private function resolveUserModelClass(): string
    {
        $configuredUserModelClass = mb_trim((string) config('fsa-sso.user_model'));

        if ($configuredUserModelClass === '') {
            throw new FsaSsoTokenException('Configured FSA SSO user model is empty.');
        }

        $normalizedUserModelClass = mb_ltrim(str_replace('\\\\', '\\', $configuredUserModelClass), '\\');

        if (! class_exists($normalizedUserModelClass)) {
            throw new FsaSsoTokenException(sprintf(
                'Configured FSA SSO user model class was not found: %s',
                $configuredUserModelClass,
            ));
        }

        if (! is_subclass_of($normalizedUserModelClass, Model::class) || ! is_subclass_of($normalizedUserModelClass, Authenticatable::class)) {
            throw new FsaSsoTokenException(sprintf(
                'Configured FSA SSO user model must extend %s and implement %s: %s',
                Model::class,
                Authenticatable::class,
                $configuredUserModelClass,
            ));
        }

        return $normalizedUserModelClass;
    }

    /**
     * @param  Model&Authenticatable  $user
     */
    private function setAttributeWhenColumnExists(Model $user, string $column, mixed $value): void
    {
        if ($this->modelHasColumn($user, $column)) {
            $user->setAttribute($column, $value);
        }
    }

    /**
     * @param  Model&Authenticatable  $user
     */
    private function modelHasColumn(Model $user, string $column): bool
    {
        $connection = $user->getConnectionName() ?? config('database.default');
        $cacheKey = sprintf('%s:%s:%s', $connection, $user->getTable(), $column);

        if (! array_key_exists($cacheKey, self::$columnCache)) {
            self::$columnCache[$cacheKey] = Schema::connection($connection)->hasColumn($user->getTable(), $column);
        }

        return self::$columnCache[$cacheKey];
    }
}

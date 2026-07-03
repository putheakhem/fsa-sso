<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use PutheaKhem\FsaSso\Exceptions\FsaSsoTokenException;

final class FsaSsoUserProvisioner
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function upsert(array $claims): Authenticatable
    {
        $userModelClass = $this->resolveUserModelClass();

        $sub = $claims['sub'] ?? null;

        if (! is_string($sub) || $sub === '') {
            throw new FsaSsoTokenException('Token sub claim is missing.');
        }

        /** @var Model&Authenticatable $model */
        $model = new $userModelClass();

        $columns = config('fsa-sso.columns', []);
        $ssoIdColumn = (string) ($columns['sso_id'] ?? 'sso_id');

        /** @var Model&Authenticatable $user */
        $user = $model->newQuery()->firstOrNew([
            $ssoIdColumn => $sub,
        ]);

        $emailColumn = (string) ($columns['email'] ?? 'email');
        $nameColumn = (string) ($columns['name'] ?? 'name');
        $providerColumn = (string) ($columns['sso_provider'] ?? 'sso_provider');
        $kycLevelColumn = (string) ($columns['kyc_level'] ?? 'kyc_level');
        $camDigiKeyIdColumn = (string) ($columns['camdigikey_id'] ?? 'camdigikey_id');
        $nbfsIdColumn = (string) ($columns['nbfs_id'] ?? 'nbfs_id');

        $user->setAttribute($ssoIdColumn, $sub);
        $email = $this->nullableString($claims['email'] ?? null) ?? $this->nullableString($user->getAttribute($emailColumn));
        $user->setAttribute($emailColumn, $email ?? sprintf('%s@fsa-sso.local', $sub));
        $user->setAttribute($nameColumn, (string) ($claims['name'] ?? $user->getAttribute($nameColumn) ?? ''));
        $user->setAttribute($providerColumn, $this->nullableString($claims['provider'] ?? null));
        $user->setAttribute($kycLevelColumn, $this->nullableString($claims['kyc_level'] ?? null));
        $user->setAttribute($camDigiKeyIdColumn, $this->nullableString($claims['camdigikey_id'] ?? null));
        $user->setAttribute($nbfsIdColumn, $this->nullableString($claims['nbfs_id'] ?? null));

        $user->save();

        return $user;
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

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return null;
        }

        return $value;
    }
}

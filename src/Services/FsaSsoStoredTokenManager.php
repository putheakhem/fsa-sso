<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use PutheaKhem\FsaSso\Exceptions\FsaSsoTokenException;

final class FsaSsoStoredTokenManager
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function persist(Authenticatable $user, string $token, array $claims): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $model = $this->resolveModel($user);
        $columns = $this->columns();

        $this->assertConfiguredColumnsExist($model, array_values($columns));

        $updatedRows = $model->newQuery()->whereKey($model->getKey())->update([
            $columns['token'] => $this->shouldEncrypt() ? Crypt::encryptString($token) : $token,
            $columns['expires_at'] => $this->resolveExpiresAt($claims),
            $columns['client_code'] => $this->nullableString($claims['client_code'] ?? null),
            $columns['last_used_at'] => null,
        ]);

        if ($updatedRows !== 1) {
            throw new FsaSsoTokenException('Unable to persist the verified FSA SSO token for the authenticated user.');
        }
    }

    public function get(Authenticatable $user, bool $markAsUsed = false): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $record = $this->storedRecord($user);
        $token = $record[$this->columns()['token']] ?? null;

        if (! is_string($token) || $token === '') {
            return null;
        }

        if ($markAsUsed) {
            $this->markAsUsed($user);
        }

        return $this->shouldEncrypt() ? Crypt::decryptString($token) : $token;
    }

    public function hasExpired(Authenticatable $user): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $expiresAt = $this->storedRecord($user)[$this->columns()['expires_at']] ?? null;

        if (! is_string($expiresAt) || $expiresAt === '') {
            return false;
        }

        return CarbonImmutable::parse($expiresAt)->isPast();
    }

    public function markAsUsed(Authenticatable $user): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $model = $this->resolveModel($user);
        $column = $this->columns()['last_used_at'];

        $this->assertConfiguredColumnsExist($model, [$column]);

        $updatedRows = $model->newQuery()->whereKey($model->getKey())->update([
            $column => now(),
        ]);

        if ($updatedRows !== 1) {
            throw new FsaSsoTokenException('Unable to update the FSA SSO token last-used timestamp for the authenticated user.');
        }
    }

    public function isEnabled(): bool
    {
        return (bool) config('fsa-sso.token_storage.enabled', false);
    }

    /**
     * @return array{token: string, expires_at: string, client_code: string, last_used_at: string}
     */
    private function columns(): array
    {
        return [
            'token' => $this->configString('fsa-sso.token_storage.token_column', 'fsa_sso_access_token'),
            'expires_at' => $this->configString('fsa-sso.token_storage.expires_at_column', 'fsa_sso_token_expires_at'),
            'client_code' => $this->configString('fsa-sso.token_storage.client_code_column', 'fsa_sso_token_client_code'),
            'last_used_at' => $this->configString('fsa-sso.token_storage.last_used_at_column', 'fsa_sso_token_last_used_at'),
        ];
    }

    private function shouldEncrypt(): bool
    {
        return (bool) config('fsa-sso.token_storage.encrypted', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function storedRecord(Authenticatable $user): array
    {
        $model = $this->resolveModel($user);
        $columns = $this->columns();

        $this->assertConfiguredColumnsExist($model, array_values($columns));

        /** @var object|null $record */
        $record = $model->newQuery()
            ->toBase()
            ->where($model->getKeyName(), $model->getKey())
            ->first(array_values($columns));

        if ($record === null) {
            throw new FsaSsoTokenException('Unable to locate the user record for stored FSA SSO token access.');
        }

        return (array) $record;
    }

    /**
     * @param  array<string>  $columns
     */
    private function assertConfiguredColumnsExist(Model $model, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($model->getTable(), $column)) {
                throw new FsaSsoTokenException(sprintf(
                    'Configured FSA SSO token storage column does not exist on [%s]: %s',
                    $model->getTable(),
                    $column,
                ));
            }
        }
    }

    private function resolveModel(Authenticatable $user): Model
    {
        if (! $user instanceof Model) {
            throw new FsaSsoTokenException('FSA SSO token storage requires an Eloquent user model.');
        }

        return $user;
    }

    private function resolveExpiresAt(array $claims): ?CarbonImmutable
    {
        $expiresAt = $claims['exp'] ?? null;

        if (! is_int($expiresAt) && ! ctype_digit((string) $expiresAt)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $expiresAt);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return null;
        }

        return $value;
    }

    private function configString(string $key, string $default): string
    {
        $value = mb_trim((string) config($key, $default));

        if ($value === '') {
            throw new FsaSsoTokenException(sprintf('Configured FSA SSO token storage value is empty: %s', $key));
        }

        return $value;
    }
}

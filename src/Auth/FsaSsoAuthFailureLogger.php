<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Auth;

use Illuminate\Support\Facades\Log;
use PutheaKhem\FsaSso\Exceptions\ExpiredFsaSsoTokenException;
use PutheaKhem\FsaSso\Exceptions\MissingFsaSsoClaimException;
use Throwable;

final class FsaSsoAuthFailureLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $reason, string $token, array $context = [], ?Throwable $exception = null): void
    {
        if (! $this->shouldLog()) {
            return;
        }

        $payload = array_merge(
            [
                'reason' => $reason,
                'token_hash' => hash('sha256', $token),
                'mode' => (string) config('fsa-sso.api_auth.mode', 'jwt'),
            ],
            $this->sanitizeContext($context),
        );

        if ($exception !== null) {
            $payload['exception'] = $exception::class;
            $payload['exception_message'] = $exception->getMessage();
        }

        Log::warning('FSA SSO authentication rejected.', $payload);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logThrowable(Throwable $throwable, string $token, array $context = []): void
    {
        $reason = match (true) {
            $throwable instanceof MissingFsaSsoClaimException => 'missing_required_claim',
            $throwable instanceof ExpiredFsaSsoTokenException => 'expired_token',
            str_contains($throwable->getMessage(), 'Malformed JWT') => 'malformed_token',
            str_contains($throwable->getMessage(), 'Unsupported token algorithm') => 'unsupported_algorithm',
            str_contains($throwable->getMessage(), 'Unable to fetch JWKS') => 'jwks_fetch_failure',
            str_contains($throwable->getMessage(), 'Unable to parse JWKS') => 'key_parse_failure',
            str_contains($throwable->getMessage(), 'Invalid token issuer') => 'issuer_mismatch',
            str_contains($throwable->getMessage(), 'Invalid token audience') => 'audience_mismatch',
            str_contains($throwable->getMessage(), 'Unable to introspect token') => 'introspection_request_failure',
            str_contains($throwable->getMessage(), 'Token is inactive') => 'inactive_token',
            default => 'token_validation_failure',
        };

        if ($throwable instanceof MissingFsaSsoClaimException && preg_match('/\[(.+)]/', $throwable->getMessage(), $matches) === 1) {
            $context['claim'] = $matches[1];
        }

        $this->log($reason, $token, $context, $throwable);
    }

    private function shouldLog(): bool
    {
        return ! app()->isProduction() || (bool) config('fsa-sso.api_auth.debug_logging', false);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            if (in_array($key, ['token', 'access_token', 'authorization'], true)) {
                continue;
            }

            $sanitized[$key] = match (true) {
                is_array($value) => $this->sanitizeContext($value),
                $value instanceof Throwable => $value::class,
                default => $value,
            };
        }

        return $sanitized;
    }
}

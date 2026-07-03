<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Auth;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PutheaKhem\FsaSso\Data\FsaSsoUserData;
use PutheaKhem\FsaSso\Exceptions\ExpiredFsaSsoTokenException;
use PutheaKhem\FsaSso\Exceptions\InvalidFsaSsoTokenException;
use stdClass;
use Throwable;

final class EdDsaJwtTokenValidator implements FsaSsoTokenValidatorInterface
{
    public function __construct(private FsaSsoClaimMapper $claimMapper) {}

    public function validate(string $token): FsaSsoUserData
    {
        $algorithm = $this->extractAlgorithm($token);

        if ($algorithm !== 'EdDSA') {
            throw new InvalidFsaSsoTokenException('Unsupported token algorithm.');
        }

        try {
            $payload = JWT::decode($token, $this->resolveKeys());
        } catch (ExpiredException) {
            throw new ExpiredFsaSsoTokenException('Token has expired.');
        } catch (InvalidFsaSsoTokenException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidFsaSsoTokenException('Invalid token.');
        }

        $claims = $this->normalizePayload($payload);
        $this->claimMapper->validateStandardClaims($claims);

        return $this->claimMapper->toUserData($claims);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePayload(stdClass $payload): array
    {
        /** @var array<string, mixed> $claims */
        $claims = json_decode((string) json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        return $claims;
    }

    private function extractAlgorithm(string $token): ?string
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new InvalidFsaSsoTokenException('Malformed JWT.');
        }

        $decodedHeader = JWT::jsonDecode(JWT::urlsafeB64Decode($segments[0]));

        if (! $decodedHeader instanceof stdClass) {
            throw new InvalidFsaSsoTokenException('Invalid token header.');
        }

        return is_string($decodedHeader->alg ?? null) ? $decodedHeader->alg : null;
    }

    /**
     * @return array<string, Key>
     */
    private function resolveKeys(): array
    {
        $jwksUrl = (string) config('fsa-sso.jwks_url');
        $cacheSeconds = (int) config('fsa-sso.api_auth.jwks_cache_seconds', 600);
        $cacheKey = 'fsa-sso:jwks:'.md5($jwksUrl);

        try {
            /** @var array{keys: array<int, array<string, mixed>>} $jwks */
            $jwks = Cache::remember($cacheKey, now()->addSeconds($cacheSeconds), function () use ($jwksUrl): array {
                try {
                    $response = Http::acceptJson()
                        ->timeout(10)
                        ->connectTimeout(5)
                        ->retry(3, 200)
                        ->get($jwksUrl)
                        ->throw();
                } catch (Throwable $exception) {
                    throw new InvalidFsaSsoTokenException('Unable to fetch JWKS.', previous: $exception);
                }

                $payload = $response->json();

                if (! is_array($payload)) {
                    throw new InvalidFsaSsoTokenException('Unable to fetch JWKS.');
                }

                return $payload;
            });
        } catch (InvalidFsaSsoTokenException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidFsaSsoTokenException('Unable to fetch JWKS.', previous: $exception);
        }

        try {
            return JWK::parseKeySet($jwks, 'EdDSA');
        } catch (Throwable $exception) {
            throw new InvalidFsaSsoTokenException('Unable to parse JWKS.', previous: $exception);
        }
    }
}

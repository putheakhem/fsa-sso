<?php

declare(strict_types=1);

return [
    'route_prefix' => env('FSA_SSO_ROUTE_PREFIX', 'auth/sso'),

    'route_middleware' => ['api'],

    'web_routes_enabled' => (bool) env('FSA_SSO_ENABLE_WEB_ROUTES', true),
    'web_route_middleware' => ['web'],
    'web_login_path' => env('FSA_SSO_WEB_LOGIN_PATH', 'fsa-sso/loginUrl'),
    'web_callback_path' => env('FSA_SSO_WEB_CALLBACK_PATH', 'sso/callback-success'),
    'web_fallback_callback_path' => env('FSA_SSO_WEB_FALLBACK_CALLBACK_PATH', 'fsa-sso/callback'),
    'web_login_route_name' => env('FSA_SSO_WEB_LOGIN_ROUTE_NAME', 'fsaSsoLoginUrl'),
    'web_callback_route_name' => env('FSA_SSO_WEB_CALLBACK_ROUTE_NAME', 'fsaSsoCallbackSuccess'),
    'web_fallback_callback_route_name' => env('FSA_SSO_WEB_FALLBACK_CALLBACK_ROUTE_NAME', 'fsaSsoCallback'),
    'web_guard' => env('FSA_SSO_WEB_GUARD', 'web'),
    'web_intended_route' => env('FSA_SSO_WEB_INTENDED_ROUTE', 'dashboard'),
    'web_failure_redirect' => env('FSA_SSO_WEB_FAILURE_REDIRECT', '/login'),

    'frontend_url' => env('FSA_SSO_FRONTEND_URL', 'http://localhost:4040'),
    'api_base_url' => env('FSA_SSO_API_BASE_URL', 'http://localhost:3000'),
    'jwks_url' => env('FSA_SSO_JWKS_URL', 'http://localhost:3000/.well-known/jwks.json'),
    'issuer' => env('FSA_SSO_ISSUER', 'http://localhost:3000'),
    'audience' => env('FSA_SSO_AUDIENCE', 'http://localhost:3000'),
    'client_code' => env('FSA_SSO_CLIENT_CODE'),
    'jwks_cache_ttl_seconds' => (int) env('FSA_SSO_JWKS_CACHE_TTL_SECONDS', 600),

    'user_model' => env('FSA_SSO_USER_MODEL', 'App\\Models\\User'),

    'columns' => [
        'sso_id' => 'sso_id',
        'email' => 'email',
        'name' => 'name',
        'sso_provider' => 'sso_provider',
        'kyc_level' => 'kyc_level',
        'camdigikey_id' => 'camdigikey_id',
        'nbfs_id' => 'nbfs_id',
    ],

    'return_access_token' => (bool) env('FSA_SSO_RETURN_ACCESS_TOKEN', false),

    'include_claims_in_response' => (bool) env('FSA_SSO_INCLUDE_CLAIMS_IN_RESPONSE', true),
];

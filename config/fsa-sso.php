<?php

declare(strict_types=1);

return [
    'route_prefix' => env('FSA_SSO_ROUTE_PREFIX', 'auth/sso'),

    'route_middleware' => ['api'],
    'api_throttle_middleware' => env('FSA_SSO_API_THROTTLE_MIDDLEWARE', 'throttle:30,1'),

    'web_routes_enabled' => (bool) env('FSA_SSO_ENABLE_WEB_ROUTES', true),
    'web_route_middleware' => ['web'],
    'web_throttle_middleware' => env('FSA_SSO_WEB_THROTTLE_MIDDLEWARE', 'throttle:30,1'),
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
        'last_sso_login_at' => 'last_sso_login_at',
    ],

    'api_auth' => [
        'enabled' => env('FSA_SSO_API_AUTH_ENABLED', true),

        'guard' => 'fsa-sso-api',

        'mode' => env(
            'FSA_SSO_API_AUTH_MODE',
            env('FSA_SSO_USE_INTROSPECTION', false) ? 'introspection' : 'jwt'
        ),

        'debug_logging' => (bool) env('FSA_SSO_API_AUTH_DEBUG_LOGGING', false),

        'allowed_client_codes' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('FSA_SSO_ALLOWED_CLIENT_CODES', ''))
        ))),

        'auto_create_users' => env('FSA_SSO_AUTO_CREATE_USERS', true),

        'default_role' => env('FSA_SSO_DEFAULT_API_ROLE', 'external-api-user'),

        'jwks_cache_seconds' => env('FSA_SSO_JWKS_CACHE_SECONDS', 600),

        'use_introspection' => env('FSA_SSO_USE_INTROSPECTION', false),

        'introspection_url' => env(
            'FSA_SSO_INTROSPECTION_URL',
            'https://sso.fsa.gov.kh/api/v1/auth/introspect'
        ),

        'introspection_cache_seconds' => env('FSA_SSO_INTROSPECTION_CACHE_SECONDS', 120),

        'claims' => [
            'sub' => 'sub',
            'client_code' => 'client_code',
            'jti' => 'jti',
            'iss' => 'iss',
            'aud' => 'aud',
            'iat' => 'iat',
            'exp' => 'exp',
            'email' => 'email',
            'name' => 'name',
            'provider' => 'provider',
            'kyc_level' => 'kyc_level',
            'e_kyc' => 'e_kyc',
            'camdigikey_id' => 'camdigikey_id',
            'nbfs_id' => 'nbfs_id',
            'roles' => 'roles',
        ],
    ],

    'return_access_token' => (bool) env('FSA_SSO_RETURN_ACCESS_TOKEN', false),

    'include_claims_in_response' => (bool) env('FSA_SSO_INCLUDE_CLAIMS_IN_RESPONSE', true),
];

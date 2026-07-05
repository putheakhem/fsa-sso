<div align="left">
<h1>Laravel FSA SSO Package</h1>

[![Latest Stable Version](https://img.shields.io/packagist/v/putheakhem/fsa-sso.svg?style=flat-square)](https://packagist.org/packages/putheakhem/fsa-sso)
[![Total Downloads](https://img.shields.io/packagist/dt/putheakhem/fsa-sso.svg?style=flat-square)](https://packagist.org/packages/putheakhem/fsa-sso)
</div>

> ⚠️ **DISCLAIMER**: This is an **unofficial community package** and is **not affiliated with, endorsed by, or supported by the official FSA SSO Platform** or the Non-Bank Financial Services Authority (FSA). This package is maintained independently. For official support, please contact the FSA SSO Platform directly.

A Laravel package for **FSA SSO authentication** with support for JWT verification via JWKS, user provisioning, and optional web login flow.

---

## 📋 What This Package Does

- Builds the FSA login URL: `/auth/login?client_code=...`
- Verifies FSA JWT using JWKS public keys (Ed25519 / EdDSA — no shared secret needed)
- Validates token claims: `iss`, `aud`, `exp`, `client_code`
- Upserts local users by stable FSA `sub` claim
- Proxies optional introspect and revoke calls to FSA API
- Registers a `fsa-sso.auth` middleware for per-request bearer token protection
- Registers an opt-in `fsa-sso-api` auth driver for API bearer authentication
- Registers package-managed web routes for browser login redirect and callback
- Keeps host-app `auth:api` / Laravel Passport separate from package-owned `auth:fsa-sso-api`

---

## ⚙️ Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13
- `ext-sodium` enabled (for Ed25519 verification)

---

## 📦 Installation

```bash
composer require putheakhem/fsa-sso
```

The service provider is auto-discovered via Laravel's package discovery.

### Publish Config and Migrations

```bash
php artisan vendor:publish --tag=fsa-sso-config
php artisan vendor:publish --tag=fsa-sso-migrations
php artisan migrate
```

If your app has many packages and tag-based publish does not detect resources, use provider-scoped commands:

```bash
php artisan vendor:publish --provider="PutheaKhem\\FsaSso\\FsaSsoServiceProvider" --tag=fsa-sso-config
php artisan vendor:publish --provider="PutheaKhem\\FsaSso\\FsaSsoServiceProvider" --tag=fsa-sso-migrations
```

The package also registers conventional `config` and `migrations` tags for compatibility.

---

## 🚀 Choose Your Integration Path

This package supports two main integration styles. Start with the one you actually need:

### Option A — Web Login for Browser Users

Use this when users click a "Sign in with FSA SSO" button in your Laravel web app and should end up logged into your local `web` guard session.

You need:

- published config
- published migrations
- `FSA_SSO_CLIENT_CODE`
- `FSA_SSO_ENABLE_WEB_ROUTES=true`
- callback URL registered in FSA admin

Typical outcome:

- browser redirects to FSA SSO
- FSA redirects back to your app callback
- package verifies token, provisions the user, logs them in, and redirects

Jump to:

- [Web Login Quick Start](#-web-login-quick-start)
- [Integration Guide (Laravel + Inertia)](#-integration-guide-laravel--inertia)

### Option B — API Authentication for Incoming Bearer Tokens

Use this when another FSA-connected system calls your Laravel API with an existing FSA SSO bearer token and your routes should authenticate with `auth:fsa-sso-api`.

You need:

- published config
- published migrations
- `FSA_SSO_API_AUTH_ENABLED=true`
- JWKS / issuer / audience values configured
- protected routes using `auth:fsa-sso-api`

Typical outcome:

- client sends `Authorization: Bearer <token>`
- package verifies the token in `jwt` mode or introspects it in `introspection` mode
- your route receives an authenticated local user and request attributes

Jump to:

- [API Auth Quick Start](#-api-auth-quick-start)
- [API Bearer Authentication](#api-bearer-authentication)

### Optional Add-On — Shared Token Storage

Enable this only if your app needs to store the verified FSA token for later trusted downstream calls.

- set `FSA_SSO_TOKEN_STORAGE_ENABLED=true`
- publish the latest package migrations
- migrate your app so the token storage columns exist

If you do not need downstream token reuse, leave token storage disabled.

---

## 🌐 .env Configuration

Add these to your `.env` file:

```env
# FSA SSO production endpoints
FSA_SSO_FRONTEND_URL=https://sso.fsa.gov.kh
FSA_SSO_API_BASE_URL=https://sso.fsa.gov.kh
FSA_SSO_JWKS_URL=https://sso.fsa.gov.kh/.well-known/jwks.json
FSA_SSO_ISSUER=https://sso.fsa.gov.kh
FSA_SSO_AUDIENCE=https://sso.fsa.gov.kh

# Your portal client code from FSA SSO admin
FSA_SSO_CLIENT_CODE=FSA-XXXXXXXXXXXX

# Optional tuning (defaults shown)
FSA_SSO_ROUTE_PREFIX=auth/sso
FSA_SSO_JWKS_CACHE_TTL_SECONDS=600
FSA_SSO_RETURN_ACCESS_TOKEN=false
FSA_SSO_INCLUDE_CLAIMS_IN_RESPONSE=true
FSA_SSO_USER_MODEL=App\Models\User
FSA_SSO_TOKEN_STORAGE_ENABLED=false
FSA_SSO_TOKEN_STORAGE_ENCRYPTED=true
FSA_SSO_TOKEN_STORAGE_TOKEN_COLUMN=fsa_sso_access_token
FSA_SSO_TOKEN_STORAGE_EXPIRES_AT_COLUMN=fsa_sso_token_expires_at
FSA_SSO_TOKEN_STORAGE_CLIENT_CODE_COLUMN=fsa_sso_token_client_code
FSA_SSO_TOKEN_STORAGE_LAST_USED_AT_COLUMN=fsa_sso_token_last_used_at

# Optional package-managed web flow
FSA_SSO_ENABLE_WEB_ROUTES=true
FSA_SSO_WEB_LOGIN_PATH=fsa-sso/loginUrl
FSA_SSO_WEB_CALLBACK_PATH=sso/callback-success
FSA_SSO_WEB_FALLBACK_CALLBACK_PATH=fsa-sso/callback
FSA_SSO_WEB_LOGIN_ROUTE_NAME=fsaSsoLoginUrl
FSA_SSO_WEB_CALLBACK_ROUTE_NAME=fsaSsoCallbackSuccess
FSA_SSO_WEB_FALLBACK_CALLBACK_ROUTE_NAME=fsaSsoCallback
FSA_SSO_WEB_GUARD=web
FSA_SSO_WEB_INTENDED_ROUTE=dashboard
FSA_SSO_WEB_FAILURE_REDIRECT=/login

# Optional API bearer authentication
FSA_SSO_API_AUTH_ENABLED=true
FSA_SSO_API_AUTH_MODE=jwt
FSA_SSO_ALLOWED_CLIENT_CODES=FSA-DPS-CODE
FSA_SSO_AUTO_CREATE_USERS=true
FSA_SSO_DEFAULT_API_ROLE=external-api-user
FSA_SSO_JWKS_CACHE_SECONDS=600
FSA_SSO_API_AUTH_DEBUG_LOGGING=false
FSA_SSO_USE_INTROSPECTION=false
FSA_SSO_INTROSPECTION_URL=https://sso.fsa.gov.kh/api/v1/auth/introspect
FSA_SSO_INTROSPECTION_CACHE_SECONDS=120
```

> ✅ `FSA_SSO_CLIENT_CODE` must exactly match the client code registered in the FSA SSO admin portal.

> ✅ Use single backslashes for `FSA_SSO_USER_MODEL` in `.env` (example: `App\Models\User`).

---

## 🧭 Web Login Quick Start

Minimum setup for browser-based login:

1. Install the package.
2. Publish config and migrations.
3. Set these env values:

```env
FSA_SSO_FRONTEND_URL=https://sso.fsa.gov.kh
FSA_SSO_JWKS_URL=https://sso.fsa.gov.kh/.well-known/jwks.json
FSA_SSO_ISSUER=https://sso.fsa.gov.kh
FSA_SSO_AUDIENCE=https://sso.fsa.gov.kh
FSA_SSO_CLIENT_CODE=FSA-XXXXXXXXXXXX
FSA_SSO_ENABLE_WEB_ROUTES=true
FSA_SSO_WEB_CALLBACK_PATH=sso/callback-success
FSA_SSO_WEB_INTENDED_ROUTE=dashboard
```

4. Register the exact callback URL in FSA admin:

- `https://your-app.com/sso/callback-success`

5. Add a login link or button to the package route:

```php
route('fsaSsoLoginUrl')
```

That is enough for the package-managed web flow. The package will redirect to FSA, verify the callback token, provision the user, log them into the configured `web` guard, and redirect them to your intended route.

## 🔌 API Auth Quick Start

Minimum setup for protecting API routes that receive FSA SSO bearer tokens:

1. Install the package.
2. Publish config and migrations.
3. Set these env values:

```env
FSA_SSO_JWKS_URL=https://sso.fsa.gov.kh/.well-known/jwks.json
FSA_SSO_ISSUER=https://sso.fsa.gov.kh
FSA_SSO_AUDIENCE=https://sso.fsa.gov.kh
FSA_SSO_API_AUTH_ENABLED=true
FSA_SSO_API_AUTH_MODE=jwt
FSA_SSO_ALLOWED_CLIENT_CODES=FSA-DPS-CODE
```

4. Protect routes with the package guard:

```php
Route::middleware(['auth:fsa-sso-api'])->group(function () {
    // protected routes
});
```

5. Add optional client-code restriction when needed:

```php
Route::middleware([
    'auth:fsa-sso-api',
    'fsa-sso.client-code:FSA-DPS-CODE',
])->group(function () {
    // protected routes
});
```

That is enough for the package to validate incoming bearer tokens and resolve a local user for your API routes.

---

## 🗄️ User Model — Fillable & Migration

The published migration adds these columns to your `users` table:

| Column | Type | Notes |
|--------|------|-------|
| `sso_id` | string, unique, nullable | Stable FSA `sub` identifier |
| `sso_provider` | string, nullable | e.g. `camdigikey` |
| `kyc_level` | string, nullable | e.g. `kyc_verified` |
| `camdigikey_id` | string, unique, nullable | |
| `nbfs_id` | string, unique, nullable | |

Add these columns to your `User` model's `$fillable`:

```php
// app/Models/User.php
protected $fillable = [
    'name',
    'email',
    'password',
    'sso_id',
    'sso_provider',
    'kyc_level',
    'camdigikey_id',
    'nbfs_id',
];
```

If you enable shared token storage, publish the latest package migration and migrate so the default storage columns are available:

```bash
php artisan vendor:publish --provider="PutheaKhem\\FsaSso\\FsaSsoServiceProvider" --tag=fsa-sso-migrations
php artisan migrate
```

The default migration adds:

| Column | Type | Notes |
|--------|------|-------|
| `fsa_sso_access_token` | text, nullable | Raw FSA token, encrypted by the package when enabled |
| `fsa_sso_token_expires_at` | timestamp, nullable | Derived from JWT `exp` |
| `fsa_sso_token_client_code` | string, nullable | Client code from verified claims |
| `fsa_sso_token_last_used_at` | timestamp, nullable | Updated when the host app marks token usage |

If you want different column names, create your own migration and set the `token_storage.*_column` config values to match.

### Migration Ownership Note

This package auto-loads package migrations until matching published copies exist in the host application's `database/migrations` directory.

- If you publish the package migrations, the published app copies become the migrations your app owns.
- Do not keep two different migrations that add the same FSA SSO columns with different filenames.
- If you customize the schema yourself, keep one clear owner for each column so rollback history stays consistent.

## Design A: Shared FSA token for trusted downstream resource access

This package now supports an optional Design A integration pattern where a portal such as DPS stores the verified FSA SSO token and later reuses that same token when calling another trusted resource server such as Compendium.

- This is a trusted downstream resource-access pattern, not a token exchange flow.
- `client_code` still matters. Each downstream service can continue validating trusted callers through the verified FSA token claims it already receives.
- Authentication comes from FSA SSO, but authorization remains local to each portal or service.
- The package does not automatically forward tokens anywhere. It only offers opt-in storage and retrieval so the host application decides when a downstream call is appropriate.
- The feature is disabled by default and only activates when `FSA_SSO_TOKEN_STORAGE_ENABLED=true`.

### How it works

1. User authenticates with FSA SSO and the package verifies the returned JWT via JWKS.
2. The package keeps user identity mapping based on `sub`, exactly as before.
3. If `token_storage.enabled` is true, the package stores the raw token plus metadata on the user record.
4. Later, the host app can retrieve the stored token and attach it to an outbound request to a trusted downstream portal or service.

### Retrieving the stored token

You can retrieve the current authenticated user token:

```php
use PutheaKhem\FsaSso\Facades\FsaSso;

$token = FsaSso::storedTokenForCurrentUser(markAsUsed: true);
```

Or retrieve it for a specific user model:

```php
use PutheaKhem\FsaSso\Facades\FsaSso;

$token = FsaSso::storedTokenForUser($user, markAsUsed: true);

if ($token !== null && ! FsaSso::storedTokenHasExpired($user)) {
    // Host application decides whether to send the token downstream.
}
```

### Token storage config

```php
'token_storage' => [
    'enabled' => false,
    'encrypted' => true,
    'token_column' => 'fsa_sso_access_token',
    'expires_at_column' => 'fsa_sso_token_expires_at',
    'client_code_column' => 'fsa_sso_token_client_code',
    'last_used_at_column' => 'fsa_sso_token_last_used_at',
],
```

When `encrypted` is true, the package uses Laravel encryption before persisting the token. That keeps storage secure even if your `User` model does not define an encrypted cast for the token column.

## API Bearer Authentication

FSA SSO authenticates the user's identity. The consuming Laravel application still owns authorization, roles, policies, and permissions.

The package only owns the `fsa-sso-api` guard and related middleware. It does not replace or modify a host application's `api` guard, Laravel Passport configuration, or internal `auth:api` routes.

Use the new opt-in guard when another FSA system calls your Laravel API with an existing FSA SSO JWT:

```http
Authorization: Bearer <FSA_SSO_JWT>
```

The package validates the bearer token through JWKS using `EdDSA`, resolves the local user from `sub`, and lets your application keep full control over authorization.

### Environment

```env
FSA_SSO_JWKS_URL=https://sso.fsa.gov.kh/.well-known/jwks.json
FSA_SSO_ISSUER=https://sso.fsa.gov.kh
FSA_SSO_AUDIENCE=https://sso.fsa.gov.kh

FSA_SSO_API_AUTH_ENABLED=true
FSA_SSO_API_AUTH_MODE=jwt
FSA_SSO_ALLOWED_CLIENT_CODES=FSA-DPS-CODE
FSA_SSO_AUTO_CREATE_USERS=true
FSA_SSO_DEFAULT_API_ROLE=external-api-user
FSA_SSO_API_AUTH_DEBUG_LOGGING=false

FSA_SSO_USE_INTROSPECTION=false
FSA_SSO_INTROSPECTION_URL=https://sso.fsa.gov.kh/api/v1/auth/introspect
FSA_SSO_INTROSPECTION_CACHE_SECONDS=120
```

### Auth Modes

`jwt` is the default mode. It preserves the current behavior:

- bearer token is decoded locally
- `EdDSA` / JWKS verification is enforced
- configured claims are validated before local user resolution

```env
FSA_SSO_API_AUTH_MODE=jwt
```

`introspection` is opt-in. Use it when the upstream token is opaque or when the upstream claim shape is not a locally verifiable JWT:

- bearer token is accepted as-is
- the package calls the configured introspection endpoint
- authentication succeeds only when `active=true`
- the introspection payload is mapped into `FsaSsoUserData` and resolved through the same local user flow

```env
FSA_SSO_API_AUTH_MODE=introspection
FSA_SSO_INTROSPECTION_URL=https://sso.fsa.gov.kh/api/v1/auth/introspect
```

For older installs, `FSA_SSO_USE_INTROSPECTION=true` still maps to `introspection` mode when `FSA_SSO_API_AUTH_MODE` is not set.

### Debug Logging

When the application is not running in production, auth rejections are logged with a structured reason and a SHA-256 token hash. Production logging stays off unless you opt in:

```env
FSA_SSO_API_AUTH_DEBUG_LOGGING=true
```

The package never logs the raw bearer token. Typical reasons include malformed token, unsupported algorithm, JWKS fetch failure, key parse failure, issuer mismatch, audience mismatch, missing required claim, inactive token, and user resolution failure.

### Claim Mapping

Claim names are configurable per auth mode consumer. The defaults match the current package behavior exactly:

```php
'api_auth' => [
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
```

This is useful when introspection responses or partner-issued payloads use different field names while you still want the guard to populate:

- `fsa_sso_user`
- `fsa_sso_client_code`
- `fsa_sso_jti`
- `fsa_sso_token_hash`

### Guard Registration

The package registers the `fsa-sso-api` driver. If you prefer to declare the guard explicitly in your application, use:

```php
'guards' => [
    'fsa-sso-api' => [
        'driver' => 'fsa-sso-api',
        'provider' => 'users',
    ],
],
```

### Route Examples

```php
Route::prefix('api/v1/integrations')
    ->middleware([
        'auth:fsa-sso-api',
        'fsa-sso.client-code:FSA-DPS-CODE',
        'permission:compendium.integration.read',
    ])
    ->group(function () {
        Route::get('/regulators', RegulatorIntegrationController::class);
    });
```

Sensitive endpoints can also confirm `active=true` through introspection:

```php
Route::middleware([
    'auth:fsa-sso-api',
    'fsa-sso.client-code:FSA-DPS-CODE',
    'fsa-sso.introspect',
])->get('/api/v1/integrations/sensitive-data', SensitiveDataController::class);
```

The optional `fsa-sso.introspect` middleware remains compatible in both auth modes. In `jwt` mode it adds an active-token check after local verification. In `introspection` mode it reuses the same introspection endpoint and cache strategy as the guard.

### Migration Example

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('sso_id')->nullable()->unique();
    $table->string('sso_provider')->nullable();
    $table->string('kyc_level')->nullable();
    $table->string('camdigikey_id')->nullable()->unique();
    $table->string('nbfs_id')->nullable()->unique();
    $table->timestamp('last_sso_login_at')->nullable();

    $table->string('password')->nullable()->change();
});
```

---

## 🛣️ Package API Endpoints

The package auto-registers these routes under the configured prefix (`auth/sso` by default):

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/auth/sso/initiate` | Returns the FSA SSO login URL |
| `POST` | `/auth/sso/verify` | Verifies JWT, upserts user, returns user + claims |
| `POST` | `/auth/sso/introspect` | Proxies bearer token to FSA introspect API |
| `POST` | `/auth/sso/revoke` | Proxies bearer token to FSA revoke API |

The package also auto-registers web routes (enabled by default):

| Method | Path | Route Name | Description |
|--------|------|------------|-------------|
| `GET` | `/fsa-sso/loginUrl` | `fsaSsoLoginUrl` | Redirects browser to FSA login URL |
| `GET` | `/sso/callback-success` | `fsaSsoCallbackSuccess` | Callback that verifies token, logs in user, and redirects |
| `GET` | `/fsa-sso/callback` | `fsaSsoCallback` | Fallback callback path |

---

## 🧪 Integration Guide (Laravel + Inertia)

This package now handles the common web flow internally.

### Step 1 — Configure Callback URL in FSA Admin

Register the callback URL in FSA SSO admin:

- `https://your-app.com/sso/callback-success`

No custom app controller or app-level web route is required when `FSA_SSO_ENABLE_WEB_ROUTES=true`.

### Using Different Callback URI Per Application

Each application can use its own callback path and route names while sharing the same package.

Example for another app:

```env
FSA_SSO_ENABLE_WEB_ROUTES=true
FSA_SSO_WEB_LOGIN_PATH=auth/fsa/login
FSA_SSO_WEB_CALLBACK_PATH=auth/fsa/callback
FSA_SSO_WEB_FALLBACK_CALLBACK_PATH=auth/fsa/callback-alt
FSA_SSO_WEB_LOGIN_ROUTE_NAME=fsa.login
FSA_SSO_WEB_CALLBACK_ROUTE_NAME=fsa.callback
FSA_SSO_WEB_FALLBACK_CALLBACK_ROUTE_NAME=fsa.callback.alt
```

Then register this exact URL in FSA admin for that application:

- `https://other-app.com/auth/fsa/callback`

Notes:

- Callback URL must match exactly between your app env config and FSA admin configuration.
- Keep a distinct `FSA_SSO_CLIENT_CODE` per application if required by your FSA SSO setup.

### Step 2 — Confirm the Login Page Is Rendered by Inertia

```php
// app/Http/Controllers/Auth/AuthenticatedSessionController.php

public function create(): Response
{
    return Inertia::render('auth/login', [
        'canResetPassword' => Route::has('password.request'),
        'canRegister'      => Route::has('register'),
        'status'           => session('status'),
    ]);
}
```

### Step 3 — Add the "Sign in with FSA SSO" Button (React / Inertia)

Use the generated Wayfinder route helper from `@/routes`. Call `.url()` when assigning it to a native `<a href>`.

```tsx
// resources/js/pages/auth/login.tsx

import { fsaSsoLoginUrl } from '@/routes';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}

export default function Login({ status, canResetPassword, canRegister }: LoginProps) {
    return (
        <AuthLayout title="Log in to your account" description="Enter your credentials to log in">
            <Form ...>
                {({ processing, errors }) => (
                    <>
                        {/* email, password, remember me, submit button */}

                        <div className="relative text-center text-xs uppercase text-muted-foreground">
                            <span className="bg-background px-2">or</span>
                        </div>

                        <Button type="button" variant="outline" className="w-full" asChild>
                            <a href={fsaSsoLoginUrl.url()}>Sign in with FSA SSO</a>
                        </Button>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
```

---

## 🔄 Complete Authentication Flow

```
1. User clicks "Sign in with FSA SSO"
         ↓
2. Browser navigates to GET /fsa-sso/loginUrl
         ↓
3. Package web login controller redirects away to:
   https://sso.fsa.gov.kh/auth/login?client_code=FSA-XXXXXXXXXXXX
         ↓
4. User authenticates with FSA SSO (CamDigi Key, NBFS, etc.)
         ↓
5. FSA SSO redirects back to your registered callback:
   https://your-app.com/sso/callback-success?authToken=<EdDSA_JWT>
         ↓
6. Package web callback controller
   ├── Resolves authToken from query string
   ├── Calls FsaSso::verifyAndProvision($token)
   │   ├── Fetches JWKS from https://sso.fsa.gov.kh/.well-known/jwks.json (cached 10 min)
   │   ├── Selects matching key by kid
   │   ├── Verifies EdDSA (Ed25519) signature
   │   ├── Validates iss, aud, exp, client_code claims
   │   └── Upserts user by sub claim → returns { user, claims }
   ├── Auth::login($user, remember: true)
   ├── Session regenerated
   └── Redirects to dashboard
```

---

## 🛡️ Protecting Routes with the Middleware

Use the `fsa-sso.auth` middleware to protect API routes that require a valid FSA SSO bearer token:

```php
// routes/api.php

Route::middleware('fsa-sso.auth')->get('/fsa-sso/me', function (Request $request) {
    $claims = $request->attributes->get('fsa_sso_claims', []);

    return response()->json([
        'sub'       => $claims['sub'] ?? null,
        'email'     => $claims['email'] ?? null,
        'kyc_level' => $claims['kyc_level'] ?? null,
        'provider'  => $claims['sso_provider'] ?? null,
    ]);
});
```

The middleware:
- Reads the bearer token from the `Authorization` header
- Verifies the JWT via JWKS (EdDSA)
- Validates `iss`, `aud`, `exp`, and `client_code`
- Attaches claims to `$request->attributes->get('fsa_sso_claims')`
- Returns `401` for invalid or missing tokens

---

## 🧩 Using the Facade Directly

```php
use PutheaKhem\FsaSso\Facades\FsaSso;

// Get the FSA SSO login URL
$response = FsaSso::getLoginUrl();
// ['loginUrl' => 'https://sso.fsa.gov.kh/auth/login?client_code=FSA-XXXXXXXXXXXX']

// Verify a token and provision the user
$result = FsaSso::verifyAndProvision($authToken);
// ['user' => User, 'claims' => ['sub' => '...', 'kyc_level' => 'kyc_verified', ...]]

// Introspect a token (proxied to FSA API)
$status = FsaSso::introspect($token);

// Revoke a token (proxied to FSA API)
FsaSso::revoke($token);
```

---

## 🔐 Security

- **No shared secret required** — JWT verification uses EdDSA asymmetric cryptography via JWKS
- **JWKS is cached** (default 10 minutes) to avoid hammering the FSA endpoint on every request
- **Token verification is local** — no sidecar or additional service needed
- **Session is regenerated** after login to prevent session fixation attacks
- Expected algorithm is `EdDSA` (Ed25519 curve) — RS256 and HS256 tokens are rejected

---

## ⚙️ Configuration Reference

All options are in `config/fsa-sso.php` after publishing:

| Key | Default | Description |
|-----|---------|-------------|
| `route_prefix` | `auth/sso` | URL prefix for package routes |
| `route_middleware` | `['api']` | Middleware applied to package routes |
| `web_routes_enabled` | `true` | Enable package-managed web login/callback routes |
| `web_route_middleware` | `['web']` | Middleware applied to package web routes |
| `web_login_path` | `fsa-sso/loginUrl` | Login redirect endpoint path |
| `web_callback_path` | `sso/callback-success` | Primary callback endpoint path |
| `web_fallback_callback_path` | `fsa-sso/callback` | Secondary callback endpoint path |
| `web_login_route_name` | `fsaSsoLoginUrl` | Route name for login redirect endpoint |
| `web_callback_route_name` | `fsaSsoCallbackSuccess` | Route name for primary callback endpoint |
| `web_fallback_callback_route_name` | `fsaSsoCallback` | Route name for fallback callback endpoint |
| `web_guard` | `web` | Auth guard used for session login in callback |
| `web_intended_route` | `dashboard` | Intended route name used after successful callback |
| `web_failure_redirect` | `/login` | Redirect target when callback fails in browser requests |
| `frontend_url` | `http://localhost:4040` | FSA SSO login portal URL |
| `api_base_url` | `http://localhost:3000` | FSA SSO backend API base URL |
| `jwks_url` | `http://localhost:3000/.well-known/jwks.json` | JWKS endpoint |
| `issuer` | `http://localhost:3000` | Expected `iss` claim |
| `audience` | `http://localhost:3000` | Expected `aud` claim |
| `client_code` | *(required)* | Your FSA portal client code |
| `jwks_cache_ttl_seconds` | `600` | JWKS cache duration in seconds |
| `user_model` | `App\Models\User` | User model to upsert |
| `columns` | *see config* | Maps JWT claims → user column names |
| `api_auth.mode` | `jwt` | Guard auth mode: `jwt` or `introspection` |
| `api_auth.debug_logging` | `false` | Force structured auth failure logs in production |
| `api_auth.claims` | *see config* | Claim-name map used by the `fsa-sso-api` guard |
| `return_access_token` | `false` | Include raw token in verify response |
| `include_claims_in_response` | `true` | Include JWT claims in verify response |

## Backward Compatibility

- Existing installs keep local `EdDSA` + JWKS verification by default.
- Existing `auth:fsa-sso-api`, `fsa-sso.client-code`, and `fsa-sso.introspect` middleware usage remains valid.
- Existing host-app Passport or `auth:api` behavior is not changed.
- `FSA_SSO_USE_INTROSPECTION` is still honored as a fallback for older configurations, but `FSA_SSO_API_AUTH_MODE` is the preferred setting going forward.

---

## ⚠️ Common Mistakes

| Symptom | Cause | Fix |
|---------|-------|-----|
| `Missing authentication token` | FSA SSO sends `authToken` param | Ensure `resolveToken()` checks `authToken` first |
| Mass assignment error on `sso_id` | Missing `$fillable` entries | Add SSO columns to `User::$fillable` |
| Callback route is not available | Package web routes were disabled | Set `FSA_SSO_ENABLE_WEB_ROUTES=true` or register your own routes/controllers |
| Login URL not working | Wrong URL format | Correct: `/auth/login?client_code=...` |
| Client code mismatch | Env vs portal mismatch | `FSA_SSO_CLIENT_CODE` must match FSA admin exactly |
| Redirects back to login after callback | Invalid user model class value (often double-escaped in `.env`) | Set `FSA_SSO_USER_MODEL=App\Models\User` and clear config cache |
| JWT verification fails | `ext-sodium` not enabled | Enable the PHP sodium extension in `php.ini` |

---

## 🧪 Testing

The package includes standalone tests using Pest + Orchestra Testbench.

Run inside the package directory:

```bash
cd packages/fsa-sso
composer install
vendor/bin/pest
```

---

## Support Me

If you find this package useful, consider supporting my work:
- [Buy me a coffee](https://www.buymeacoffee.com/iamputhea)

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

---

Built with ❤️ by [Puthea Khem](mailto:puthea.khem@gmail.com)

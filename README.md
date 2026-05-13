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
- Registers package-managed web routes for browser login redirect and callback

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
FSA_SSO_USER_MODEL=App\\Models\\User

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
```

> ✅ `FSA_SSO_CLIENT_CODE` must exactly match the client code registered in the FSA SSO admin portal.

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

### Step 2 — Pass the Package Login Route to Your Login Page (Inertia)

```php
// app/Http/Controllers/Auth/AuthenticatedSessionController.php

public function create(): Response
{
    return Inertia::render('auth/login', [
        'canResetPassword' => Route::has('password.request'),
        'canRegister'      => Route::has('register'),
        'fsaSsoLoginUrl'   => route('fsaSsoLoginUrl', absolute: false),
        'status'           => session('status'),
    ]);
}
```

### Step 3 — Add the "Sign in with FSA SSO" Button (React / Inertia)

```tsx
// resources/js/pages/auth/login.tsx

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    fsaSsoLoginUrl: string;
}

export default function Login({ status, canResetPassword, canRegister, fsaSsoLoginUrl }: LoginProps) {
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
                            <a href={fsaSsoLoginUrl}>Sign in with FSA SSO</a>
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
| `return_access_token` | `false` | Include raw token in verify response |
| `include_claims_in_response` | `true` | Include JWT claims in verify response |

---

## ⚠️ Common Mistakes

| Symptom | Cause | Fix |
|---------|-------|-----|
| `Missing authentication token` | FSA SSO sends `authToken` param | Ensure `resolveToken()` checks `authToken` first |
| Mass assignment error on `sso_id` | Missing `$fillable` entries | Add SSO columns to `User::$fillable` |
| Callback route is not available | Package web routes were disabled | Set `FSA_SSO_ENABLE_WEB_ROUTES=true` or register your own routes/controllers |
| Login URL not working | Wrong URL format | Correct: `/auth/login?client_code=...` |
| Client code mismatch | Env vs portal mismatch | `FSA_SSO_CLIENT_CODE` must match FSA admin exactly |
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

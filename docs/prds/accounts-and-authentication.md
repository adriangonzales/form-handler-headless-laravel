# PRD: Accounts & Authentication

**Status:** Built (JWT API only) · **Owner area:** `AuthController`, `LoginRequest`, `UserResource`, `tymon/jwt-auth`, `config/auth.php`, `config/jwt.php`

## 1. Summary

The system is a headless JSON API; there is no web interface. Account holders authenticate by exchanging their email and password for a JSON Web Token (JWT) and send it as a bearer token on every API request. Tokens are short-lived and can be refreshed for a longer window. Authentication uses [`tymon/jwt-auth`](https://github.com/tymondesigns/jwt-auth) through an `api` guard with the `jwt` driver, which is the application's default guard.

## 2. Users

- **Account holder**: owns forms and calls the API with a JWT.
- **Operator / admin**: creates accounts. There is no self-service sign-up.

## 3. Endpoints

All under `/api/v1/auth`.

| Method | Path       | Auth required          | Purpose                                 |
| ------ | ---------- | ---------------------- | --------------------------------------- |
| POST   | `/login`   | No                     | Exchange email and password for a token |
| POST   | `/refresh` | Token (may be expired) | Exchange a token for a new one          |
| POST   | `/logout`  | Yes                    | Invalidate the current token            |
| GET    | `/me`      | Yes                    | The authenticated user                  |

## 4. Functional requirements

**FR-1 Log in.** `POST /api/v1/auth/login` with `email` (required, email) and `password` (required, string). The email is matched case-insensitively. On success it returns:

```json
{ "access_token": "<jwt>", "token_type": "bearer", "expires_in": 3600 }
```

`expires_in` is in seconds and follows `JWT_TTL` (minutes, default 60). Wrong credentials return 422 with `errors.email = ["These credentials do not match our records."]`.

**FR-2 Login throttling.** After 5 failed attempts for the same email and IP address, further attempts return 429 with an `errors.email` message saying how many seconds remain, until the minute window passes. A successful login clears the counter.

**FR-3 Authenticated requests.** Every `/api/v1` route other than login and refresh requires `Authorization: Bearer <token>`. A missing, malformed, expired or invalidated token returns `401 {"message":"Unauthenticated."}`. All responses, including errors, are JSON. Guests are never redirected.

**FR-4 Refresh.** `POST /api/v1/auth/refresh` with the current token, which may already be expired, returns a new token in the FR-1 shape. The old token is blacklisted. The refresh window is measured from the **original login**: a refreshed token keeps the first token's issued-at (`iat`) claim, so a chain of refreshes ends `JWT_REFRESH_TTL` minutes (default 20160 = 14 days) after login and the user must log in again. After that, or without a valid token, refresh returns 401.

**FR-5 Log out.** `POST /api/v1/auth/logout` blacklists the current token and returns 204.

**FR-6 Current user.** `GET /api/v1/auth/me` returns `{ "data": { id, name, email, email_verified_at, created_at, updated_at } }`. The password and remember token are never exposed.

**FR-7 Configuration.** `JWT_SECRET` signs tokens (HS256). `composer setup` generates one with `php artisan jwt:secret`. Tests use a fixed secret from `phpunit.xml`.

## 5. Gaps

- **No account management endpoints.** There is no registration, password reset, password change, profile update or account deletion. Accounts are created by seeding or manually.
- **No email verification.** `email_verified_at` is stored and returned but never checked.
- **No multi-factor authentication.** Two-factor authentication and passkeys were removed with the web app.
- **No "log out everywhere".** Only the presented token is invalidated. Other tokens stay valid until they expire.
- **No token scopes.** Every token has full access to the owner's data.
- **The blacklist depends on the cache.** Logged-out and refreshed tokens are recorded in the cache store. Clearing the cache makes them valid again until they expire.

## 6. Known issues

- The `sessions` and `password_reset_tokens` tables are still created by the base migration but are no longer used.
- `AppServiceProvider` still configures `Password::defaults()` (a strict policy in production) and `config/auth.php` still defines a password broker, but no endpoint sets or resets a password, so neither has any effect.
- Authorization between users is not enforced on the entry and notification endpoints; see [Form Entries](form-entries.md) and [Form Notifications](form-notifications.md).

## 7. Open questions

1. Should registration and password reset be offered as API endpoints, or remain operator-only?
2. Is a 60-minute access token with a 14-day refresh window right, or should refresh tokens be separate and revocable?
3. Should per-form public keys be introduced for form submissions, separate from account JWTs?

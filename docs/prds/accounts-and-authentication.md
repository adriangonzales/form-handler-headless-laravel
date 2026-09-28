# PRD: Accounts & Authentication

**Status:** Built (from the Laravel React starter kit) · **Owner area:** Fortify, Sanctum, `Settings\ProfileController`, `Settings\SecurityController`, `resources/js/pages`

## 1. Summary

The system has two entry points for account holders: a session-based web app (Inertia + React) for signing in and managing their account, and a token-based JSON API (Sanctum) for managing forms, entries, and notifications. Authentication is provided by Laravel Fortify with password, two-factor, and passkey support.

## 2. Users

- **Account holder** — owns forms and signs in to the web app and/or calls the API.
- **Operator / admin** — creates accounts (there is no self-service sign-up).

## 3. Web application

### Pages

| Route                                                   | Page                                                                   | Access        |
| ------------------------------------------------------- | ---------------------------------------------------------------------- | ------------- |
| `/`                                                     | Welcome                                                                | Public        |
| `/login`, `/forgot-password`, `/reset-password/{token}` | Auth pages                                                             | Guest         |
| `/two-factor-challenge`                                 | 2FA code / recovery code entry                                         | Mid-login     |
| `/user/confirm-password`                                | Password confirmation                                                  | Authenticated |
| `/dashboard`                                            | Dashboard (placeholder content)                                        | Authenticated |
| `/settings/profile`                                     | Name and email; delete account                                         | Authenticated |
| `/settings/security`                                    | Change password, 2FA, passkeys (requires recent password confirmation) | Authenticated |
| `/settings/appearance`                                  | Light / dark / system theme                                            | Authenticated |

### Functional requirements

**FR-1 Sign in** with email and password. Emails are lowercased. Throttled to 5 attempts per minute per email + IP.

**FR-2 Password reset** by emailed link.

**FR-3 Two-factor authentication (TOTP).** Enabling requires password confirmation and confirming a code; recovery codes are provided. The 2FA challenge is throttled to 5 per minute per login session.

**FR-4 Passkeys (WebAuthn).** Users can register, name, and remove passkeys and sign in with them. Management requires password confirmation. Passkey attempts are throttled to 10 per minute. A `/.well-known/passkey-endpoints` document points password managers at the security page.

**FR-5 Profile.** Users can update name and email. Changing email clears `email_verified_at`. Users can delete their account after confirming their password.

**FR-6 Password change** from the security page, throttled to 6 per minute.

**FR-7 Password policy.** In production, passwords must be at least 12 characters with mixed case, letters, numbers, symbols, and must not appear in known breaches. Outside production, Laravel defaults apply.

**FR-8 Appearance.** Theme choice is stored in an unencrypted `appearance` cookie and applied server-side to avoid a flash of the wrong theme.

## 4. API authentication

**FR-9** All `/api/v1/*` routes require `auth:sanctum`. Clients authenticate with a personal access token (Bearer) or, from configured first-party domains, with the web session cookie.

**FR-10** Unauthenticated API requests receive `401` JSON. All `api/*` errors render as JSON.

**FR-11** Tokens do not expire (`sanctum.expiration = null`).

## 5. Gaps

- **No way to obtain an API token.** `HasApiTokens` is on the user, but there is no UI or endpoint to create, list, or revoke tokens. Tokens can only be minted from code or tinker.
- **No self-service registration.** Fortify's registration feature is disabled; accounts are created by seeding or manually.
- **Email verification is not enforced.** Routes use the `verified` middleware, but `User` does not implement `MustVerifyEmail`, so it always passes.
- **No teams or shared access.** A form belongs to exactly one user.
- **No token abilities/scopes** to limit what a token can do (e.g. submit-only).
- **Deleting an account that owns forms** is unhandled: `forms.user_id` has a foreign key with no `ON DELETE` action and nothing deletes the user's forms first, so the delete will be rejected by the database (or leave orphaned forms where FK checks are off).

## 6. Known issues

- Authorization between users is not enforced on most API endpoints; see [Forms](forms.md), [Form Entries](form-entries.md), and [Form Notifications](form-notifications.md).
- The sidebar still links to the starter kit's GitHub repository and documentation.

## 7. Open questions

1. Should API tokens be per-user, per-form, or both (e.g. a public submit key per form)?
2. Is registration intended to stay invite-only?
3. Should account deletion cascade to forms, entries, and notifications, or be blocked while forms exist?

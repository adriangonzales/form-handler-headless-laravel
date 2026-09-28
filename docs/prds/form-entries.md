# PRD: Form Entries

**Status:** Partially built · **Owner area:** `FormEntryController`, `FormEntry` model, `FormEntryStoreRequest`

## 1. Summary

A Form Entry is one submission to a form. The service validates submitted fields against the form's schema, stores only the validated values, records request metadata (IP, user agent, referer), and exposes entries to the form owner for review and triage (starring, marking read, spam flags).

## 2. Users

- **Submitter** — an end user filling in a form on a third-party site. Today they cannot submit directly (see Gaps); submissions must be made with an API token.
- **Account holder** — reviews and triages entries for their forms.

## 3. Goals

- Accept structured submissions and reject invalid ones with field-level errors.
- Keep an audit trail of where each submission came from.
- Provide an inbox-style workflow: unread/read, starred, spam.

## 4. Data model

Table `form_entries`:

| Field | Type | Notes |
| --- | --- | --- |
| `id` | ULID (PK) | |
| `form_id` | FK (ULID) → `forms.id` | |
| `data` | JSON, nullable | Validated submission values |
| `ip` | string, nullable | All client IPs from the request, comma-joined |
| `ip_location_display` | string, nullable | Reserved; not populated |
| `referer` | string, nullable | See Known issues |
| `user_agent` | string, nullable | Raw UA string |
| `user_agent_display` | string, nullable | Reserved; not populated |
| `spam` | boolean, nullable | Model default `false` |
| `spam_score` | decimal(4,3) | Default `0` |
| `spam_reason` | string, nullable | |
| `starred` | boolean | Default `false` |
| `read_at` | timestamp, nullable | `null` = unread |
| `created_at`, `updated_at`, `deleted_at` | timestamps | Soft-deletable |

## 5. Functional requirements

**FR-1 Submit an entry.** `POST /api/v1/forms/{form}/entries` (Sanctum-authenticated).
- The form must be active; otherwise the request is rejected with `403 {"message":"This form is not accepting submissions."}` before validation.
- Request fields are validated with the rules built from the form's schema (see [Forms FR-5](forms.md)). Failures return 422 with per-field errors.
- Only validated fields are stored in `data`; unknown fields are silently dropped. A form with an empty schema stores `data: []`.
- Metadata captured: `ip`, `referer`, `user_agent`. `spam` is set to `false` and `spam_score` to `0`.
- A `FormEntryCreated` event is dispatched (no listeners are registered).
- Responds `201` with the entry resource.

**FR-2 List entries.** `GET /api/v1/forms/{form}/entries` returns entries oldest first, paginated (15 per page), with `links` and `meta`.

**FR-3 Show an entry.** `GET /api/v1/entries/{entry}` (shallow route).

**FR-4 Update an entry.** `PUT/PATCH /api/v1/entries/{entry}` accepts:

| Field | Rules |
| --- | --- |
| `spam_score` | required, numeric |
| `starred` | required |
| `data` | nullable, json |
| `ip`, `ip_location_display`, `referer`, `user_agent`, `user_agent_display`, `spam_reason` | nullable, string |
| `spam`, `read_at` | nullable |

Returns the refreshed entry. This is the mechanism for starring, marking read/unread, and flagging spam.

**FR-5 Response shape.** The entry resource returns `id, form_id, data, ip, ip_location_display, referer, user_agent, user_agent_display, spam, spam_score, spam_reason, starred, read_at, created_at, updated_at, deleted_at`. Because the payload has its own `data` key, Laravel does **not** add the usual `{ "data": ... }` wrapper to single entries — unlike forms and notifications. `spam_score` is serialised as a string with 2 decimals; `read_at` as a Unix timestamp integer. `created_at`, `updated_at` and `deleted_at` are ISO 8601 UTC strings with microseconds (e.g. `2026-01-02T03:04:05.000000Z`); `deleted_at` is `null` for any record the API can return.

## 6. Gaps

- **No public submission endpoint.** The store route is behind `auth:sanctum`, so a browser form cannot post to it without exposing a token. The controller has a TODO to split out an inbound, public-facing endpoint. This is the most significant gap for a "headless form handler".
- **Entries are not scoped to their form.** The index endpoint ignores `{form}` and returns entries from *every* form in the system.
- **No ownership checks** on list, show, or update — any authenticated user can read or edit any entry.
- **No spam protection** (captcha, honeypot, rate limiting on submissions). Placeholders only.
- **No IP geolocation or user-agent parsing** for the `*_display` fields.
- **No filtering or sorting** (unread, starred, spam, date range) and no newest-first option.
- **No delete, bulk actions, or export.**
- Pending tests (`todo`): form/entry ID match, starring, marking read, marking unread.

## 7. Known issues

- **Referer is never captured.** The controller reads `$request->header('HTTP_REFERER')`; the header name is `Referer`, so the value is always `null`.
- **Update allows rewriting submission metadata.** `data`, `ip`, `user_agent` and `referer` are editable, which undermines the audit trail.
- **`data` validated as a JSON string on update** while the model casts it to an array, so updating `data` with a JSON object fails validation.
- **Full-replacement semantics.** `spam_score` and `starred` are required on every update, so a client cannot PATCH just `read_at`.
- **Mixed date formats.** `read_at` is serialised as a Unix integer (model cast `timestamp`) while `created_at`, `updated_at` and `deleted_at` are ISO 8601 strings.
- **Precision mismatch.** Column is `decimal(4,3)` but the model casts to `decimal:2`.
- **Undelivered alerts.** `NewFormEntry` mail and notification calls are commented out; see [Form Notifications](form-notifications.md).

## 8. Open questions

1. How should public submissions be authenticated — form ID only, per-form public key, allowed origins, signed requests?
2. Should entries record which schema version they were validated against, given schemas can change?
3. Should spam-flagged entries be stored, quarantined, or discarded?
4. What should the submission response be for browser posts (JSON vs. redirect to `settings.redirect`)?

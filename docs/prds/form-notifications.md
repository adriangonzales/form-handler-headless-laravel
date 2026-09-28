# PRD: Form Notifications

**Status:** Configuration built; delivery not built · **Owner area:** `FormNotificationController`, `FormNotification` model, `App\Mail\NewFormEntry`, `App\Notification\NewFormEntry`

## 1. Summary

Form Notifications let an account holder list the recipients who should be alerted when a form receives a new entry. Each recipient is either an email address or an SMS number, and can be enabled or disabled. Today the API stores this configuration, but no alert is actually sent.

## 2. Users

- **Account holder** — configures who hears about new submissions to each form.
- **Recipient** — a person (not necessarily a user of the system) who receives the alert.

## 3. Goals

- Configure multiple recipients per form across email and SMS.
- Temporarily turn a recipient off without deleting them.
- Record the last delivery error per recipient so problems are visible.

## 4. Data model

Table `form_notifications`:

| Field | Type | Notes |
| --- | --- | --- |
| `id` | ULID (PK) | |
| `form_id` | FK (ULID) → `forms.id` | |
| `type` | enum `email` \| `sms` | |
| `value` | string | Email address or phone number |
| `enabled` | boolean | DB default `true`; model default `false` (see Known issues) |
| `error` | string, nullable | Intended for the last delivery error; never written by the system |
| `created_at`, `updated_at`, `deleted_at` | timestamps | Soft-deletable |

## 5. Functional requirements

**FR-1 List recipients.** `GET /api/v1/forms/{form}/notifications` returns the form's recipients, paginated (15 per page), with `links` and `meta`.

**FR-2 Show a recipient.** `GET /api/v1/notifications/{notification}` (shallow route) returns `{ data: { id, form_id, type, value, enabled, error } }`.

**FR-3 Add a recipient.** `POST /api/v1/forms/{form}/notifications` accepts:

| Field | Rules |
| --- | --- |
| `type` | required, `email` or `sms` |
| `value` | required, string |
| `enabled` | optional, boolean |

Responds `201` with the recipient resource.

**FR-4 Update a recipient.** `PUT/PATCH /api/v1/notifications/{notification}` accepts `form_id`, `type`, `value`, `enabled` (all required) and `error` (nullable string). See Known issues — this endpoint currently cannot succeed.

## 6. Alert delivery (current state)

Two alert classes exist as scaffolding, neither is triggered:

- `App\Mail\NewFormEntry` — mailable with subject "New Form Entry" and view `emails.new-form-entry`, which is an empty template.
- `App\Notification\NewFormEntry` — queued mail notification containing starter placeholder text; it takes no entry argument.

The calls that would send them are commented out in `FormEntryController::store`, and they target the **form owner**, not the configured recipients. There is no SMS channel or provider configured.

## 7. Gaps

- **No delivery at all** — email or SMS — when an entry is created.
- **Recipients are not used.** Even the commented-out code alerts the owner, not `form_notifications` rows.
- **No value validation by type**: `value` is not checked as an email for `email` or as E.164 for `sms`.
- **No ownership checks** on any endpoint; any authenticated user can list, view, or add recipients on any form.
- **No delete endpoint.**
- **No error recording or retry** — `error` is never populated.
- **No tests for update**, and none asserting cross-user access is denied.

## 8. Known issues

- **Update always fails validation.** `form_id` is validated as `integer` and `exists:forms.id,id`; form IDs are ULIDs, and the `exists` table reference is malformed.
- **Update can move a recipient to another form**, because `form_id` is fillable and accepted in the request.
- **Default mismatch.** The database defaults `enabled` to `true`, but the model's attribute default is `false`, so a recipient created through the API without `enabled` is stored as disabled.
- **`error` is client-writable**, although it is meant to be system-reported.

## 9. Open questions

1. Should delivery be driven by a `FormEntryCreated` listener that fans out to all enabled recipients?
2. Which SMS provider, and how are SMS costs and rate limits handled?
3. Should the form owner be notified by default when no recipients are configured?
4. Should the email include entry data (via `MapFormData`) or only a link, given privacy concerns?
5. Should recipients have to verify their address or number before they can be enabled?

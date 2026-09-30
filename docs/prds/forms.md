# PRD: Forms

**Status:** Built (API only) · **Owner area:** `FormController`, `Form` model, `FormPolicy`, `BuildValidationRules`, `DuplicateForm`, `FormSettings`

## 1. Summary

A Form is the central object of the product. An account holder creates a form, describes its fields and validation rules in a JSON schema, and uses the form's ID as the target for submissions from their own front end. The service never renders the form; it only stores the definition and enforces it when entries arrive.

## 2. Users

- **Account holder / developer** — authenticates with a JWT ([Accounts & Authentication](accounts-and-authentication.md)), creates forms, and wires the form ID into a website or app.

## 3. Goals

- Let a user define a form without deploying backend code.
- Let validation rules live alongside the form definition so submissions are validated server-side.
- Give each form an opaque, non-sequential identifier (ULID) that is safe to embed in public markup.

## 4. Data model

Table `forms`:

| Field                                    | Type            | Notes                                                     |
| ---------------------------------------- | --------------- | --------------------------------------------------------- |
| `id`                                     | ULID (PK)       | Generated automatically                                   |
| `user_id`                                | FK → `users.id` | Owner. Set from the authenticated user on create          |
| `name`                                   | string(400)     | Required                                                  |
| `active`                                 | boolean         | Defaults to `false`                                       |
| `schema`                                 | JSON, nullable  | Field definitions (see §5)                                |
| `settings`                               | JSON, nullable  | Cast to the `App\Data\FormSettings` data object (see §5a) |
| `created_at`, `updated_at`, `deleted_at` | timestamps      | Soft-deletable                                            |

Relationships: belongs to a `User`; has many `FormEntry`; has many `FormNotification`.

## 5. Schema format

`schema` is a JSON object keyed by **field ID**. Each value describes one field:

```json
{
    "01J9...A": { "label": "Name", "rules": ["required"] },
    "01J9...B": { "label": "Email", "rules": "required,email" },
    "01J9...C": { "label": "Message", "name": "message" }
}
```

| Key     | Required | Meaning                                                                                            |
| ------- | -------- | -------------------------------------------------------------------------------------------------- |
| `label` | No       | Human-readable label. Used when displaying entry data; falls back to the field ID                  |
| `rules` | No       | Laravel validation rules, either an array or a comma-separated string. Defaults to `["sometimes"]` |
| `name`  | No       | Overrides the input name used for validation. Defaults to the field ID                             |

The factory's `withBasicSchema()` state and the tests use ULIDs as field IDs, but any string key works. An empty schema is stored and returned as `[]` rather than `{}`.

## 5a. Settings format

`settings` is defined by `App\Data\FormSettings` (spatie/laravel-data), which is the single source of truth for both the allowed keys and their validation rules.

```json
{
    "redirect": "https://example.com/thanks",
    "timezone": "America/Chicago",
    "domains": ["example.com", "*.example.org"]
}
```

| Key        | Type                    | Default | Rules                                                                          | Intended use                                          |
| ---------- | ----------------------- | ------- | ------------------------------------------------------------------------------ | ----------------------------------------------------- |
| `redirect` | string \| null          | `null`  | URL, max 2048                                                                  | Where to send a browser after a successful submission |
| `timezone` | string \| null          | `null`  | Valid PHP timezone identifier                                                  | Displaying entry timestamps and notification content  |
| `domains`  | list of strings \| null | `[]`    | List of hostnames; a leading `*.` wildcard is allowed. No scheme, port or path | Origins allowed to submit to the form                 |

- Unknown keys are rejected with a 422 on `settings`. They are not silently dropped.
- Omitted keys take their defaults. A form with settings always returns all three keys.
- `settings` itself may be `null` or omitted, in which case the form has no settings and the API returns `null`.
- Validation is shared by create and update through `App\Concerns\FormSettingsValidationRules`, which reads the allowed keys and rules from `FormSettings`. Adding a property to `FormSettings` is enough to accept and validate a new setting.
- Planned settings (noted in `FormSettings`): CAPTCHA type (none, reCAPTCHA, hCaptcha) and secret key, honeypot enabled flag and field name.

## 6. Functional requirements

**FR-1 List forms.** `GET /api/v1/forms` returns the authenticated user's forms only, paginated (Laravel default of 15 per page) with `links` and `meta`. Pagination links keep all query parameters.

- **Sorting:** the optional `sort` parameter accepts `created_at` (the default), `updated_at` or `name`. Prefix it with `-` for descending order, e.g. `sort=-updated_at`. Names are compared case-insensitively. Ties are broken by ID in the same direction. Any other value, including combined sorts such as `name,-created_at`, returns 422 on `sort`.
- **Filtering:** `filter[active]=true` or `filter[active]=false` (`1` and `0` also work) limits the list to active or inactive forms. Any other value returns 422 on `filter.active`, and any other filter key returns 422 on `filter`. Filtering and sorting can be combined.

**FR-2 Show a form.** `GET /api/v1/forms/{form}` returns `{ data: { id, user_id, name, active, schema, settings, created_at, updated_at, deleted_at } }`. `created_at`, `updated_at` and `deleted_at` are ISO 8601 UTC strings with microseconds (e.g. `2026-01-02T03:04:05.000000Z`); `deleted_at` is `null` for any record the API can return. Only the owner may view a form; anyone else receives `403 {"message":"You do not own this form."}` (`FormPolicy::view`).

**FR-3 Create a form.** `POST /api/v1/forms` accepts:

| Field      | Rules                                                        |
| ---------- | ------------------------------------------------------------ |
| `name`     | required, string, max 400                                    |
| `schema`   | nullable, array (a JSON-encoded string is rejected with 422) |
| `settings` | nullable, object matching §5a                                |

The form is created under the authenticated user, `active` defaults to `false`, a `FormCreated` event is dispatched, and the response is `201` with the form resource.

**FR-4 Update a form.** `PUT/PATCH /api/v1/forms/{form}` accepts `name` (required, string, max 400), `active` (required, boolean), `schema` (nullable, array), `settings` (nullable, object matching §5a). Because `name` and `active` are required, a PATCH is effectively a full replacement of those two fields. Only the owner may update a form (`FormPolicy::update`, checked in `FormUpdateRequest` before validation); anyone else receives the same 403.

**FR-5 Schema → validation rules.** `BuildValidationRules` converts the schema into a Laravel rules array keyed by `name ?? fieldId`. String rules are split on commas. Fields without rules get `sometimes`. An empty or null schema produces no rules.

**FR-6 Inactive forms reject submissions.** Entries can only be submitted to forms with `active = true` (`FormPolicy::submit`). Submissions to an inactive form receive `403 {"message":"This form is not accepting submissions."}` before any validation runs, and no entry is stored.

**FR-7 Entry display mapping.** `MapFormData` pairs each schema field with an entry's value, producing `{ fieldId: { label, data } }`. Values are looked up under the field's `name` when it has one, otherwise its field ID, matching how submissions are stored. It builds the field list in new-entry alert emails ([Form Notifications](form-notifications.md)).

**FR-8 Delete a form.** `DELETE /api/v1/forms/{form}` soft-deletes the form and returns `204`. Its entries and notifications are left untouched. A deleted form returns 404 from every other form endpoint (including submissions) until it is restored. Owner only (`FormPolicy::delete`). There is no permanent delete (`FormPolicy::forceDelete` denies).

**FR-9 Restore a form.** `POST /api/v1/forms/{form}/restore` clears `deleted_at` and returns `200` with the form resource. The route resolves soft-deleted forms. Owner only (`FormPolicy::restore`).

**FR-10 Duplicate a form.** `POST /api/v1/forms/{form}/duplicate` creates a new form owned by the same user, with the same `schema` and `settings`, the name suffixed with ` (copy)` (the original is truncated if needed to stay within 400 characters), and `active = false`. Entries and notification recipients are not copied. The copy is made by the `App\Actions\Forms\DuplicateForm` action, which dispatches `FormCreated`. Returns `201` with the new form. Owner only (`FormPolicy::view`).

## 7. Events

- `FormCreated(Form $form)` — dispatched after create and after duplicate. No listeners are registered.

## 8. Gaps

- **Settings are stored but not yet acted on.** `redirect`, `timezone` and `domains` are validated and returned, but no submission, notification or display logic reads them yet. `redirect` and `domains` depend on a public submission endpoint ([Form Entries](form-entries.md)). `timezone` could now be applied to alert emails, which show submission times in UTC ([Form Notifications](form-notifications.md)).

## 9. Known issues

- **Unvalidated rule strings.** Rules from the schema are passed straight to the validator. An invalid rule name causes a server error at submission time rather than a 422 at form-save time.

## 10. Open questions

1. Should field definitions support type, placeholder, options, and ordering, or stay validation-only?
2. Should the schema be validated structurally (and rule names checked) when a form is saved?
3. Should a success message be added to `settings`, alongside the planned CAPTCHA and honeypot settings?

# Reminders API (GH-3)

## To-do

Review and confirm after each slice.

| Done | Slice                          | Step                                                                                                                                                     |
| ---- | ------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [x]  | 1 — Create & list reminders    | `php artisan make:model Reminder -mfs`; migration for `reminders`                                                                                        |
| [x]  | 1                              | `ReminderType` enum (`Once`, `Recurring`); `Reminder` model (`#[Fillable]`, casts, `user()`); `User::reminders()`                                        |
| [x]  | 1                              | `ReminderFactory` (states `once()`, `recurring()`, `inactive()`), `ReminderSeeder` (called from `DatabaseSeeder`)                                        |
| [x]  | 1                              | `StoreReminderRequest`; `ReminderData` (Spatie Data)                                                                                                     |
| [x]  | 1                              | `Api\ReminderController@store` and `@index` (own reminders, paginated, optional `active` filter); `apiResource('reminders')` in the `auth:sanctum` group |
| [ ]  | 1                              | Bruno requests: create once, create recurring, list reminders                                                                                            |
| [ ]  | 1                              | Tests: AC-1 to AC-7 (`tests/Feature/Api/ReminderTest.php`)                                                                                               |
| [ ]  | 2 — View, edit, toggle, delete | `ReminderPolicy`; `@show`, `@update` (`UpdateReminderRequest`; `is_active` toggles), `@destroy`                                                          |
| [ ]  | 2                              | Bruno requests: show, update, deactivate, delete                                                                                                         |
| [ ]  | 2                              | Tests: AC-8 to AC-11                                                                                                                                     |

## Acceptance criteria

| ID    | Acceptance criterion                                                                                            | Test slice                     | Planned test                                                             |
| ----- | --------------------------------------------------------------------------------------------------------------- | ------------------------------ | ------------------------------------------------------------------------ |
| AC-1  | An unauthenticated request to any reminders endpoint returns 401                                                | 1 — Create & list reminders    | Feature: `Api/ReminderTest` dataset "rejects guests" (extended in 2)     |
| AC-2  | A user can create a one-time reminder (`title`, `type=once`, `remind_at`) and gets 201; it is active by default | 1 — Create & list reminders    | Feature: `Api/ReminderTest` "creates a one-time reminder"                |
| AC-3  | A user can create a recurring reminder (`title`, `type=recurring`, `interval_minutes`) and gets 201             | 1 — Create & list reminders    | Feature: `Api/ReminderTest` "creates a recurring reminder"               |
| AC-4  | `once` without `remind_at`, or `recurring` without `interval_minutes`, returns 422                              | 1 — Create & list reminders    | Feature: `Api/ReminderTest` dataset "requires the field for its type"    |
| AC-5  | `remind_at` on a recurring reminder, or `interval_minutes` on a one-time one, returns 422                       | 1 — Create & list reminders    | Feature: `Api/ReminderTest` dataset "rejects fields of the other type"   |
| AC-6  | Missing `title`, invalid `type`, or `interval_minutes` < 1 returns 422                                          | 1 — Create & list reminders    | Feature: `Api/ReminderTest` dataset "validates input"                    |
| AC-7  | Listing returns only the user's reminders; `?active=1` returns only active ones                                 | 1 — Create & list reminders    | Feature: `Api/ReminderTest` "lists own reminders / filters active"       |
| AC-8  | The owner can view a reminder; another user gets 403                                                            | 2 — View, edit, toggle, delete | Feature: `Api/ReminderTest` "shows own / forbids others"                 |
| AC-9  | The owner can partially update a reminder's fields; changing type requires the matching field, else 422         | 2 — View, edit, toggle, delete | Feature: `Api/ReminderTest` "updates a reminder / validates type change" |
| AC-10 | The owner can deactivate and reactivate a reminder via `is_active`; another user gets 403                       | 2 — View, edit, toggle, delete | Feature: `Api/ReminderTest` "toggles active / forbids others"            |
| AC-11 | The owner can delete a reminder; another user gets 403                                                          | 2 — View, edit, toggle, delete | Feature: `Api/ReminderTest` "deletes own / forbids others"               |
| AC-12 | In Bruno, every request works against the local environment using the token from `login`                        | 1–2                            | Manual                                                                   |

## Context

The API has login (001) and notes/tags (002). We need reminders the desktop app turns into notifications: repeating ("drink water every 30 min", "walk every hour") and one-time ("follow up with ABC at 2pm", "check this on a future date"). This is backend only: a token-authed API to add, list, view, update, activate/deactivate and delete reminders. The desktop app fetches active reminders and schedules its own timers.

## Decisions

1. **Ownership.** Reminders belong to a user (`user_id`); every query is scoped through `$request->user()`.
2. **Fields.** `reminders`: `id`, `user_id` (FK, cascade), `title` (string 255), `notes` (text, nullable), `type` (string), `remind_at` (timestamp, nullable, one-time only), `interval_minutes` (unsigned int, nullable, recurring only), `is_active` (bool, default true), timestamps. No soft deletes.
3. **Type.** String-backed enum `App\Enums\ReminderType` (`Once = 'once'`, `Recurring = 'recurring'`), cast on the model, validated with `Rule::enum`.
4. **Recurrence.** Fixed interval only (every N minutes, 1 to 10080). Client schedules timers; the server does no due-tracking, snoozing or acknowledgement. Daily-at-time or cron schedules are out of scope.
5. **Validation.** `remind_at`: `required_if:type,once`, `date`, `prohibited_if:type,recurring`. `interval_minutes`: `required_if:type,recurring`, `integer|min:1|max:10080`, `prohibited_if:type,once`. `is_active`: `sometimes|boolean`. Updates are partial (`PUT`/`PATCH` both route to `update`): `UpdateReminderRequest` makes every field `sometimes`, and the type/field consistency rules are evaluated against the effective type (request `type`, else the stored one), so `{"is_active": false}` alone is valid and a type change must supply the matching field and null out the other.
6. **Time zones.** Client sends ISO 8601 with offset; stored as Laravel's default (app timezone/UTC) and returned as ISO 8601.
7. **Activate/deactivate.** Through `PUT` with `is_active`; no separate endpoint.
8. **Past one-time reminders.** `remind_at` may be in the past (no `after:now` rule); the client decides what to do with fired one-timers. Server never auto-deactivates.
9. **Responses.** Spatie Data (`ReminderData`, snake_case), unversioned routes, per `.ai/rules/boost/http-routes.md`. Index is paginated (15), ordered by id desc, filter `?active=1`.
10. **Authorization.** `ReminderPolicy` (`view`, `update`, `delete`: owner only), applied via `Gate::authorize` as in `NoteController`.
11. **Bruno.** One `.bru` file per endpoint in `docs/bruno/`.

## Files to create/modify

| File                                                                            | Action                     |
| ------------------------------------------------------------------------------- | -------------------------- |
| `database/migrations/*_create_reminders_table.php`                              | Create                     |
| `app/Enums/ReminderType.php`                                                    | Create                     |
| `app/Models/Reminder.php`                                                       | Create                     |
| `app/Models/User.php`                                                           | Add `reminders()`          |
| `database/factories/ReminderFactory.php`, `database/seeders/ReminderSeeder.php` | Create                     |
| `database/seeders/DatabaseSeeder.php`                                           | Call the seeder            |
| `app/Http/Requests/Api/StoreReminderRequest.php`, `UpdateReminderRequest.php`   | Create (update is partial) |
| `app/Data/ReminderData.php`                                                     | Create                     |
| `app/Http/Controllers/Api/ReminderController.php`                               | Create                     |
| `app/Policies/ReminderPolicy.php`                                               | Create                     |
| `routes/api.php`                                                                | Add `apiResource`          |
| `docs/bruno/*.bru`                                                              | One request per endpoint   |
| `tests/Feature/Api/ReminderTest.php`                                            | Create                     |

## Testing plan

Pest feature tests with `Sanctum::actingAs($user)` and factory states. Cover only the ACs above.

- **Slice 1:** AC-1 to AC-7.
- **Slice 2:** AC-8 to AC-11.

## Verification

- `php artisan test --compact tests/Feature/Api/ReminderTest.php`
- `vendor/bin/pint --dirty --format agent`
- `php artisan route:list --path=api`
- Run the Bruno requests against the local environment after `login` (AC-12).

## Open questions

None yet.

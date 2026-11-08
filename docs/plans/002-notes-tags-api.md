# Notes + Tags API (GH-2)

## To-do

Review and confirm after each slice.

| Done | Slice                       | Step                                                                                                                                           |
| ---- | --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| [x]  | 1 — Create & list notes     | `php artisan make:model Note -mfs` and `make:model Tag -mf`; migrations for `notes`, `tags`, `note_tag`                                        |
| [ ]  | 1                           | `NoteFormat` enum (`Plain`, `Markdown`); `Note`/`Tag` models (`#[Fillable]`, casts, relations); `User::notes()` and `User::tags()`             |
| [ ]  | 1                           | `NoteFactory`, `TagFactory`, `NoteSeeder` (called from `DatabaseSeeder`)                                                                       |
| [ ]  | 1                           | `StoreNoteRequest`; `NoteData` and `TagData` (Spatie Data)                                                                                     |
| [ ]  | 1                           | `Api\NoteController@store` and `@index` (paginated, own notes only); routes in an `auth:sanctum` group in `routes/api.php`                     |
| [ ]  | 1                           | Bruno requests: create note, list notes                                                                                                        |
| [ ]  | 1                           | Tests: AC-1, AC-2, AC-3, AC-4, AC-5, AC-6 (`tests/Feature/Api/NoteTest.php`)                                                                   |
| [ ]  | 2 — Search & tag filter     | `index` accepts `search` (case-insensitive LIKE on title and text) and `tag` (tag name); filters combine with AND                              |
| [ ]  | 2                           | Bruno requests: search notes, notes by tag                                                                                                     |
| [ ]  | 2                           | Tests: AC-7, AC-8, AC-9                                                                                                                        |
| [ ]  | 3 — View, edit, delete      | `NotePolicy`; `NoteController@show`, `@update` (`UpdateNoteRequest`, tags still mandatory), `@destroy`                                         |
| [ ]  | 3                           | Bruno requests: show, update, delete                                                                                                           |
| [ ]  | 3                           | Tests: AC-10, AC-11, AC-12, AC-13                                                                                                              |
| [ ]  | 4 — Tags list & rule update | `Api\TagController@index` (own tags); update `.ai/rules/boost/http-routes.md` to say API responses use Spatie Data and routes stay unversioned |
| [ ]  | 4                           | Bruno request: list tags                                                                                                                       |
| [ ]  | 4                           | Tests: AC-14                                                                                                                                   |

## Acceptance criteria

| ID    | Acceptance criterion                                                                                                          | Test slice                  | Planned test                                                                              |
| ----- | ----------------------------------------------------------------------------------------------------------------------------- | --------------------------- | ----------------------------------------------------------------------------------------- |
| AC-1  | An unauthenticated request to any notes or tags endpoint returns 401                                                          | 1 — Create & list notes     | Feature: `Api/NoteTest` dataset "rejects guests" (index, store; extended in later slices) |
| AC-2  | An authenticated user can create a note with title, text, format and at least one tag and gets 201 with the note and its tags | 1 — Create & list notes     | Feature: `Api/NoteTest` "creates a note with tags"                                        |
| AC-3  | Creating a note with missing or empty `tags` returns 422                                                                      | 1 — Create & list notes     | Feature: `Api/NoteTest` dataset "requires tags on create"                                 |
| AC-4  | Missing `title`/`text`, or a `format` other than `plain`/`markdown`, returns 422                                              | 1 — Create & list notes     | Feature: `Api/NoteTest` dataset "validates input"                                         |
| AC-5  | Creating notes with an existing tag name reuses that tag for the same user and does not duplicate it                          | 1 — Create & list notes     | Feature: `Api/NoteTest` "reuses existing tags"                                            |
| AC-6  | Listing returns only the authenticated user's notes, paginated, including `format` and tags                                   | 1 — Create & list notes     | Feature: `Api/NoteTest` "lists only own notes"                                            |
| AC-7  | `search` matches title or text case-insensitively                                                                             | 2 — Search & tag filter     | Feature: `Api/NoteTest` "searches title and text case-insensitively"                      |
| AC-8  | `tag` returns only notes carrying that tag                                                                                    | 2 — Search & tag filter     | Feature: `Api/NoteTest` "filters by tag"                                                  |
| AC-9  | `search` and `tag` together return the intersection; search never leaks another user's notes                                  | 2 — Search & tag filter     | Feature: `Api/NoteTest` "combines search and tag"                                         |
| AC-10 | The owner can view a note; another user gets 403                                                                              | 3 — View, edit, delete      | Feature: `Api/NoteTest` "shows own note / forbids others"                                 |
| AC-11 | The owner can update title, text, format and tags; tags are replaced (sync)                                                   | 3 — View, edit, delete      | Feature: `Api/NoteTest` "updates a note"                                                  |
| AC-12 | Updating without tags returns 422                                                                                             | 3 — View, edit, delete      | Feature: `Api/NoteTest` "requires tags on update"                                         |
| AC-13 | The owner can delete a note; another user gets 403                                                                            | 3 — View, edit, delete      | Feature: `Api/NoteTest` "deletes own note / forbids others"                               |
| AC-14 | `GET /api/tags` returns only the user's tags                                                                                  | 4 — Tags list & rule update | Feature: `Api/TagTest` "lists own tags"                                                   |
| AC-15 | In Bruno, every request works against the local environment using the token from `login`                                      | 1–4                         | Manual                                                                                    |

## Context

The API currently has only `POST /api/login` (plan 001). We need a `Note` entity for personal notes on different topics (office, project, personal, server, ...), organised with tags, and an authenticated API to create, read, update, delete, search and filter by tag. Notes carry a `format` (`plain` or `markdown`); the API only returns the value and the frontend chooses the renderer.

## Decisions

1. **Ownership.** Notes and tags belong to a user (`user_id`), not a team. Every query is scoped through `$request->user()`.
2. **Fields.** `notes`: `id`, `user_id` (FK, cascade on delete), `title` (string 255), `text` (text), `format` (string, default `plain`), timestamps. No soft deletes.
3. **Format.** String-backed enum `App\Enums\NoteFormat` (`Plain = 'plain'`, `Markdown = 'markdown'`), cast on the model, validated with `Rule::enum`.
4. **Tags.** `tags` (`id`, `user_id`, `name`, timestamps, unique `user_id + name`) and `note_tag` pivot (`note_id`, `tag_id`, cascade on delete). The client sends `tags: ["office","server"]`; names are trimmed and lowercased, missing tags are created with `firstOrCreate` for that user, and the note's tags are `sync`ed. At least one tag is required on create and update (`required|array|min:1`, items `string|max:50|distinct`). No tag CRUD; `GET /api/tags` lists the user's tags.
5. **Search.** `GET /api/notes?search=` matches `title` OR `text` with `LOWER(col) LIKE %needle%` (needle lowercased, `%`/`_` escaped) so it behaves the same on sqlite, mysql and pgsql. `?tag=name` filters by tag name. Both combine with AND. Results ordered newest first and paginated (15 per page).
6. **Responses.** Spatie Laravel Data, matching `LoginTokenData` (`NoteData`, `TagData` with snake_case output). The rule file `.ai/rules/boost/http-routes.md` currently says to prefer Eloquent API Resources and versioning; it is updated in slice 4 to say API responses use Spatie Data and routes stay unversioned.
7. **Routes.** Unversioned, in an `auth:sanctum` group in `routes/api.php`: `apiResource('notes', NoteController::class)` and `GET /tags`.
8. **Authorization.** `NotePolicy` (`view`, `update`, `delete`: `$note->user_id === $user->id`), applied with `$this->authorize` in the controller. Index and store are scoped to the authenticated user.
9. **Bruno.** One `.bru` file per endpoint in `docs/bruno/` (seq 2+), inheriting the collection bearer auth.

## Files to create/modify

| File                                                                  | Action                     |
| --------------------------------------------------------------------- | -------------------------- |
| `database/migrations/*_create_notes_table.php`                        | Create                     |
| `database/migrations/*_create_tags_table.php`                         | Create                     |
| `database/migrations/*_create_note_tag_table.php`                     | Create                     |
| `app/Enums/NoteFormat.php`                                            | Create                     |
| `app/Models/Note.php`, `app/Models/Tag.php`                           | Create                     |
| `app/Models/User.php`                                                 | Add `notes()` and `tags()` |
| `database/factories/NoteFactory.php`, `TagFactory.php`                | Create                     |
| `database/seeders/NoteSeeder.php`, `DatabaseSeeder.php`               | Create / call the seeder   |
| `app/Http/Requests/Api/StoreNoteRequest.php`, `UpdateNoteRequest.php` | Create                     |
| `app/Data/NoteData.php`, `app/Data/TagData.php`                       | Create                     |
| `app/Http/Controllers/Api/NoteController.php`, `TagController.php`    | Create                     |
| `app/Policies/NotePolicy.php`                                         | Create                     |
| `routes/api.php`                                                      | Add routes                 |
| `.ai/rules/boost/http-routes.md`                                      | Update API guidance        |
| `docs/bruno/*.bru`                                                    | One request per endpoint   |
| `tests/Feature/Api/NoteTest.php`, `TagTest.php`                       | Create                     |

## Testing plan

Pest feature tests with `Sanctum::actingAs($user)`, using the factories. Cover only the ACs above.

- **Slice 1:** AC-1 to AC-6 (guest dataset, create, tags required, input validation dataset, tag reuse, own-notes listing).
- **Slice 2:** AC-7 to AC-9 (mixed-case search on title and text, tag filter, combined filter, no cross-user leakage).
- **Slice 3:** AC-10 to AC-13 (show, update with tag sync, tags required on update, delete, other-user 403).
- **Slice 4:** AC-14.

## Verification

- `php artisan test --compact tests/Feature/Api`
- `vendor/bin/pint --dirty --format agent` and phpstan
- `php artisan route:list --path=api`
- Run the Bruno requests against `https://hive-be.test` after `login` (AC-15).

## Open questions

None yet.

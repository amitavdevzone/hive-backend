# Notes & Tags API

Each user has their own notes and tags. Every query is scoped to the authenticated user. Responses use Spatie Data (snake_case) and the routes are unversioned.

```
Create note -> tags trimmed + lowercased -> firstOrCreate per user -> sync to note
Show/update/delete -> NotePolicy: note.user_id == user.id, otherwise 403
```

| Endpoint (auth:sanctum)          | Behavior                                                                                                                                                                |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| GET /api/notes                   | Own notes, newest first, 15 per page. `?search=` matches title or text, case-insensitive, with `%` and `_` escaped. `?tag=` filters by tag name. Both combine with AND. |
| POST /api/notes                  | `title`, `text`, `format` (`plain` or `markdown`), and `tags` (at least 1, max 50 chars each, distinct). Returns 201.                                                   |
| GET/PUT/DELETE /api/notes/{note} | Owner only. Update replaces the tags (sync), and tags are still required. Delete returns 204.                                                                           |
| GET /api/tags                    | Own tags, ordered by name.                                                                                                                                              |

`format` is only returned. The frontend chooses the renderer.

# API Login (Sanctum token)

API clients get a Sanctum personal access token from `POST /api/login`. The route is unversioned and throttled at `throttle:6,1`. The token goes in an `Authorization: Bearer <token>` header on the `auth:sanctum` routes. "Active" means `email_verified_at` is set; there is no separate `is_active` flag.

```
POST /api/login {email, password}
  -> invalid input -> 422 field errors
  -> unknown email OR wrong password OR email_verified_at null
       -> 422 errors.email = "These credentials are incorrect." (no token created)
  -> createToken('api') -> 200 {access_token, token_type: "Bearer"}
```

| Case                                        | Status | Response                                                          |
| ------------------------------------------- | ------ | ----------------------------------------------------------------- |
| Verified user, correct password             | 200    | `{"access_token": "...", "token_type": "Bearer"}`                 |
| Unknown email / wrong password / unverified | 422    | Same `errors.email` message, so account existence is not revealed |
| Missing or malformed `email` / `password`   | 422    | Field validation errors                                           |
| More than 6 attempts per minute             | 429    |                                                                   |

The body is `LoginTokenData` (Spatie Data, snake_case output). It is returned with 200, not the 201 that Data uses by default for POST. All DTOs in `app/Data` extend Spatie `Data`.

Bruno collection: `docs/bruno/`. Select the `local` environment (it sets `baseUrl`) and a Global Environment. Running `login` saves the token to the global `accessToken`, and the collection's bearer auth sends it.

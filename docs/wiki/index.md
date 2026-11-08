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

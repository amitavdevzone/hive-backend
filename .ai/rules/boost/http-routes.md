---
paths:
    - 'app/Http/**'
    - 'routes/**'
---

## APIs & Spatie Data

- API responses use Spatie Laravel Data classes (`app/Data`, snake_case output via `SnakeCaseMapper`), not Eloquent API Resources.
- API routes stay unversioned (no `/v1` prefix), matching the existing routes in `routes/api.php`.

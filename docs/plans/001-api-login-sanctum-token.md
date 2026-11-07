# API Login with Sanctum Token + Bruno Collection (GH-1)

## To-do

Review and confirm after each slice.

| Done | Slice | Step |
| ---- | ----- | ---- |
| [x] | 1 — Happy-path login | Run `php artisan install:api --no-interaction` (Sanctum, `routes/api.php`, `personal_access_tokens` migration, `bootstrap/app.php` api routing) and `composer require spatie/laravel-data` |
| [x] | 1 | Add `HasApiTokens` to `app/Models/User.php` |
| [x] | 1 | Create `LoginRequest` (`email` required/email, `password` required/string) |
| [ ] | 1 | Create `LoginTokenData` (Spatie Laravel Data) |
| [ ] | 1 | Create invokable `Api\LoginController`, register `POST /api/login` in `routes/api.php` |
| [ ] | 1 | Create Bruno collection in `docs/bruno/` (base URL env var, login request, token pushed to global var, collection-level bearer auth) |
| [ ] | 1 | Tests: AC-1 (`tests/Feature/Api/LoginTest.php`) |
| [ ] | 2 — Generic failures & abuse | Single failure path: unknown email / wrong password / unverified all raise the same `ValidationException`; dummy hash check when user missing |
| [ ] | 2 | Add `throttle:6,1` to the login route |
| [ ] | 2 | Tests: AC-3, AC-4, AC-5, AC-6, AC-7 |
| [ ] | 3 — Existing DTOs | Convert `app/Data/UserTeam.php` and `app/Data/TeamPermissions.php` to extend Spatie `Data`; fix call sites |
| [ ] | 3 | Tests: AC-8 (run existing team/Inertia tests) |

## Acceptance criteria

| ID   | Acceptance criterion | Test slice | Planned test |
| ---- | -------------------- | ---------- | ------------ |
| AC-1 | A verified user posting correct email + password gets 200 with `access_token` and `token_type`, and a token row exists for that user | 1 — Happy-path login | Feature: `Api/LoginTest` "returns a sanctum token for valid credentials" |
| AC-2 | In Bruno, running `login` stores the token in a global variable and other collection requests send it as a bearer token | 1 — Happy-path login | Manual |
| AC-3 | An email that does not exist returns 422 with the generic "credentials are wrong" message | 2 — Generic failures | Feature: `Api/LoginTest` "rejects unknown email" |
| AC-4 | A wrong password returns the identical message and status as AC-3 | 2 — Generic failures | Feature: `Api/LoginTest` "rejects wrong password with the same message" |
| AC-5 | A user with correct password but unverified email (inactive) returns the identical message and no token is created | 2 — Generic failures | Feature: `Api/LoginTest` "rejects unverified user" |
| AC-6 | Missing or malformed email / missing password returns 422 validation errors | 2 — Generic failures | Feature: `Api/LoginTest` dataset "validates input" |
| AC-7 | Repeated attempts beyond the limit return 429 | 2 — Generic failures | Feature: `Api/LoginTest` "throttles login attempts" |
| AC-8 | Existing pages using `UserTeam` / `TeamPermissions` still work after converting them to Laravel Data | 3 — Existing DTOs | Existing team feature tests |

## Context

The app is Inertia + session auth only. There is no `routes/api.php`, no Sanctum, no `spatie/laravel-data`, and `users` has no active flag. We need a token login endpoint for API clients: email + password in, Sanctum plain-text token out inside a DTO, plus a Bruno collection so the endpoint can be exercised from the Bruno app and the token reused by later authenticated requests.

The repo has no git remote, so no ticket can be created yet; the title stays `GH-TBD`.

## Decisions

1. **Dependencies.** Add `laravel/sanctum` (via `php artisan install:api`) and `spatie/laravel-data`. Both were explicitly requested, which covers the "no new dependencies without approval" rule.
2. **"Active" = `email_verified_at` is not null.** No migration. Chosen over a new `is_active` column to keep the change small; revisit if accounts need suspending independent of verification.
3. **Route.** `POST /api/login`, unversioned (user's choice; overrides the `http-routes.md` default of API versioning). Single-action `App\Http\Controllers\Api\LoginController`; validation in `App\Http\Requests\Api\LoginRequest`.
4. **One generic failure.** Unknown email, wrong password and unverified user all throw `ValidationException::withMessages(['email' => 'These credentials are incorrect.'])` → 422. Same message and status in every case so account existence is not revealed. When the user is not found, run a dummy `Hash::check` so response time does not leak existence.
5. **Throttling.** `throttle:6,1` on the route (matches existing `user-password.update` convention).
6. **Success DTO.** `App\Data\LoginTokenData extends Spatie\LaravelData\Data` with `accessToken` and `tokenType` (`'Bearer'`), output keys mapped to snake_case via `#[MapOutputName(SnakeCaseMapper::class)]`. Returned directly from the controller (Data is Responsable). Token: `$user->createToken('api')->plainTextToken`.
7. **All DTOs on Laravel Data.** New DTOs extend `Data`; the two existing plain `readonly` classes in `app/Data` are converted in slice 3.
8. **Bruno collection** in `docs/bruno/`: `bruno.json`; `collection.bru` with bearer auth `{{accessToken}}`; `environments/local.bru` with `baseUrl`; `login.bru` calling `{{baseUrl}}/api/login` with a post-response script `bru.setGlobalEnvVar("accessToken", res.body.access_token)`. The user must have a Global Environment selected in Bruno for the global variable to persist. The `bru` CLI is not installed, so this is verified manually (AC-2).

## Code sketch

```php
// app/Http/Controllers/Api/LoginController.php
public function __invoke(LoginRequest $request): LoginTokenData
{
    $user = User::query()->where('email', $request->validated('email'))->first();

    $passwordMatches = Hash::check($request->validated('password'), $user?->password ?? self::DUMMY_HASH);

    if (! $user || ! $passwordMatches || $user->email_verified_at === null) {
        throw ValidationException::withMessages(['email' => 'These credentials are incorrect.']);
    }

    return new LoginTokenData($user->createToken('api')->plainTextToken);
}
```

```php
// app/Data/LoginTokenData.php
#[MapOutputName(SnakeCaseMapper::class)]
class LoginTokenData extends Data
{
    public function __construct(public string $accessToken, public string $tokenType = 'Bearer') {}
}
```

## Files to create/modify

| File | Action |
| ---- | ------ |
| `composer.json`, `composer.lock` | Modify (sanctum, laravel-data) |
| `routes/api.php`, `bootstrap/app.php`, `database/migrations/*_create_personal_access_tokens_table.php` | Create/modify via `install:api` |
| `app/Models/User.php` | Modify (`HasApiTokens`) |
| `app/Http/Controllers/Api/LoginController.php` | Create |
| `app/Http/Requests/Api/LoginRequest.php` | Create |
| `app/Data/LoginTokenData.php` | Create |
| `app/Data/UserTeam.php`, `app/Data/TeamPermissions.php` | Modify (slice 3) |
| `tests/Feature/Api/LoginTest.php` | Create |
| `docs/bruno/bruno.json`, `collection.bru`, `login.bru`, `environments/local.bru` | Create |

## Testing plan

- **Slice 1:** AC-1 — post valid credentials for a factory user; assert 200, JSON structure, `personal_access_tokens` count 1.
- **Slice 2:** AC-3/4/5 — assert the same status and the same `errors.email` message for each failure and, for AC-5, zero tokens (use `User::factory()->unverified()`); AC-6 — dataset of invalid payloads; AC-7 — exceed the limit and assert 429.
- **Slice 3:** AC-8 — run existing team feature tests; no new test.
- Pest feature tests only; no padding beyond the listed behaviors.

## Verification

1. `php artisan test --compact tests/Feature/Api/LoginTest.php`, then the team tests after slice 3.
2. `vendor/bin/pint --dirty --format agent`.
3. `php artisan route:list --path=api` shows `POST api/login`.
4. In Bruno: open `docs/bruno`, select the `local` environment and a Global Environment, run `login` with a seeded verified user; confirm the `accessToken` global is set (AC-2).

## Open questions

None yet.

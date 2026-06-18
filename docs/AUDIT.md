# Codebase Audit — Meal Plan Shopping List API

Audited against: `docs/rubric.md`
Date: 2026-06-19

---

## Summary

| # | Criterion | Result |
|---|-----------|--------|
| 1 | Type Safety | FAIL |
| 2 | Error Handling | FAIL |
| 3 | Observability | FAIL |
| 4 | Configuration | FAIL |
| 5 | Validation | PASS |
| 6 | Data Integrity | PARTIAL |
| 7 | Security | PARTIAL |
| 8 | API Consistency | FAIL |
| 9 | Tests Pass | PASS |
| 10 | No Hardcoded Environment Values | FAIL |

**Passed: 2 / 10**
**Partial: 2 / 10**
**Failed: 6 / 10**

---

## Criterion 1 — Type Safety

**Result: FAIL**

No file under `app/` contains `declare(strict_types=1)`. Controller methods, model methods, and FormRequest methods all lack return type declarations.

Failing files:
- `app/Http/Controllers/Api/MealPlanController.php` — all 7 public methods and the private helper have no return types
- `app/Models/MealPlan.php`, `MealPlanDay.php`, `Meal.php`, `MealIngredient.php`, `User.php` — all relationship methods have no return types
- `app/Http/Requests/StoreMealPlanRequest.php`, `UpdateMealPlanRequest.php` — missing `declare(strict_types=1)`

What is needed:
- Add `declare(strict_types=1);` after `<?php` in every file under `app/`
- Add return types to all public and protected methods, e.g. `public function index(Request $request): JsonResponse`

---

## Criterion 2 — Error Handling

**Result: FAIL**

No named exception classes exist in the codebase. Authorization failure is handled via `abort(403)` and there are no named exception classes for any business-logic failure mode.

The rubric requires that every distinct business failure mode is expressed as a named exception class or a `ValidationException` with a specific key. `abort(403)` produces an `HttpException`, not a domain-specific exception.

What is needed:
- Create a named exception for authorization failure, e.g. `App\Exceptions\MealPlanAccessDeniedException`
- Any other domain-specific failure mode should have its own named class

Note: No raw `new \Exception(...)` calls exist, which is positive, but the absence of any named exception classes means the rubric criterion is not met.

---

## Criterion 3 — Observability

**Result: FAIL**

No `Log::info()` calls exist anywhere in the codebase. State-changing operations — `store`, `update`, `destroy` — emit no log entries.

Failing operations:
- `POST /api/meal-plans` — no log on plan creation
- `PUT /api/meal-plans/{id}` — no log on metadata update
- `DELETE /api/meal-plans/{id}` — no log on deletion

Note: The rubric's specific examples reference a Loot Drop Simulator (trade accept, trade reject, etc.) which do not apply here. However, the underlying requirement — that every state-changing operation emits a structured log entry with entity ID and actor ID — does apply to this API.

What is needed:
```php
Log::info('meal_plan.created', ['meal_plan_id' => $mealPlan->id, 'coach_id' => $request->user()->id]);
Log::info('meal_plan.updated', ['meal_plan_id' => $mealPlan->id, 'coach_id' => $request->user()->id]);
Log::info('meal_plan.deleted', ['meal_plan_id' => $mealPlan->id, 'coach_id' => $request->user()->id]);
```

---

## Criterion 4 — Configuration

**Result: FAIL**

Magic numbers appear directly in business logic and validation rules. None are extracted to a `config/*.php` file.

Hardcoded values found:
- `max:120` — meal plan name max length (`StoreMealPlanRequest`)
- `max:80` — meal name max length (`StoreMealPlanRequest`)
- `max:100` — ingredient name max length (`StoreMealPlanRequest`)
- `between:1,7` — valid day range (`StoreMealPlanRequest`)
- `* 4`, `* 4`, `* 9` — kcal/g multipliers for protein, carbs, fat (`MealPlanController::nutritionSummary`)

The kcal/g multipliers are particularly important — if nutritional science guidance changed, a developer would need to find and edit a raw SQL string in the controller rather than updating a config value.

What is needed:
- Create `config/meal_plans.php` with values for name length, day range, allowed units, and macro multipliers
- Reference via `config('meal_plans.max_name_length')` etc.

---

## Criterion 5 — Validation

**Result: PASS**

No validation rules issue DB queries. All rules in `StoreMealPlanRequest` and `UpdateMealPlanRequest` use Laravel's built-in rule objects only. No custom `Rule::exists()` or `Rule::unique()` callbacks that would cause N+1 query issues. The duplicate day number check uses Laravel's `distinct` rule, which operates in memory on the request payload.

---

## Criterion 6 — Data Integrity

**Result: PARTIAL**

**Pass:**
- The `store` method wraps the entire nested create (plan + days + meals + ingredients) in `DB::transaction()`. A failure at any nesting level rolls back the whole operation.

**Fail:**
- `update` and `destroy` do not use `DB::transaction()`. `update` only writes to one table so the risk is low, but `destroy` relies on cascade deletes which are database-level operations outside Laravel's transaction scope in SQLite — this is inconsistent.
- No `lockForUpdate()` is used anywhere. The ownership check in `authoriseMealPlan` reads `coach_id` and then the method proceeds to write. Under concurrent requests, another process could modify the plan between the ownership read and the write. For this API the risk is low, but the rubric requires it.

---

## Criterion 7 — Security

**Result: PARTIAL**

**Pass:**
- All 7 endpoints are inside `Route::middleware('auth:sanctum')` — unauthenticated requests receive 401 before reaching the controller.
- Ownership is checked on every endpoint that takes a `{mealPlan}` parameter.
- 403 is returned for cross-coach access; 404 for missing plans. These are correctly separated.

**Fail:**
- `APP_DEBUG=true` in `.env.example` means a developer who copies it verbatim for local development (or for a staging environment) will expose full stack traces in HTTP error responses. This is covered in detail under criterion 10.
- No API resources are used, so the response shape is not explicitly controlled — fields added to a model in the future would automatically appear in responses without a developer actively choosing to expose them.

---

## Criterion 8 — API Consistency

**Result: FAIL**

**Status codes:**
- `GET` endpoints return `200` ✓
- `POST /api/meal-plans` returns `201` ✓
- `DELETE /api/meal-plans/{id}` returns `200` with a JSON body ✗ — the rubric requires `204` for no-content deletes. A `204` must have no response body; the current `{'message': 'Meal plan deleted.'}` body is incompatible with `204`.

**Response shapes:**
- `index` returns `{'data': [...]}` (wrapped)
- `store` and `show` return the model instance directly (unwrapped)
- `update` returns the model instance directly (unwrapped)
- `shoppingList` returns `{'items': [...]}`
- `nutritionSummary` returns `{'days': [...]}`

No API Resource classes are used anywhere. The rubric requires consistent use of API resources — no controller should return raw arrays alongside resource objects. Currently every endpoint uses a different shape.

What is needed:
- Create `App\Http\Resources\MealPlanResource` and `MealPlanCollection`
- Change `destroy` to return `response()->noContent()` (204)

---

## Criterion 9 — Tests Pass

**Result: PASS**

All 22 tests pass. No tests are marked pending or skipped.

```
Tests: 22 passed (86 assertions)
```

Coverage includes: authentication, create with nested records, duplicate day validation, quantity validation, show, update, delete, ownership (403 vs 404), shopping list aggregation (same unit, case-insensitive, different unit), nutrition summary (calorie calculation, null macros, per-day grouping).

---

## Criterion 10 — No Hardcoded Environment Values

**Result: FAIL**

`.env.example` line 4:
```
APP_DEBUG=true
```

The rubric requires `APP_DEBUG=false`. A developer copying `.env.example` verbatim would deploy with debug mode on, exposing full stack traces including file paths, class names, and query structure in HTTP error responses.

Additionally, no required keys are annotated with `# REQUIRED`. `APP_KEY` is required for the application to start — it is currently blank with no annotation.

No credentials or secrets appear in tracked files. ✓

What is needed:
- Change `APP_DEBUG=true` to `APP_DEBUG=false` in `.env.example`
- Add `# REQUIRED` comment above `APP_KEY=`

## Goal

I am building a Laravel JSON API for coach-created meal plans.

A coach can create a meal plan that contains days, meals, and ingredients. From that saved data, the API must also generate:

1. A weekly shopping list that combines matching ingredients.
2. A daily nutrition summary that totals protein, carbohydrates, fat, and calories per day.

All endpoints will require Sanctum authentication. Coaches can only manage their own meal plans.

---

## Project setup

The project starts from an empty directory:

```bash
composer create-project laravel/laravel meal-plans
cd meal-plans
php artisan install:api
```

I will configure SQLite in `.env` for local development and testing.

Example:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

I will use the API install step to set up Sanctum-based API authentication.

---

## Libraries and packages

### Laravel

Laravel will provide:

* routing
* controllers
* migrations
* Eloquent models
* FormRequest validation
* authentication middleware
* database transactions
* feature testing tools

### Laravel Sanctum

Sanctum will be used because the brief says all endpoints require `auth:sanctum`.

All meal plan routes will be inside an authenticated route group:

```php
Route::middleware('auth:sanctum')->group(function () {
    // meal plan routes
});
```

### SQLite

SQLite will be used for local development because the brief specifically asks for SQLite configuration.

### Pest or Laravel feature tests

I will use the project’s configured test framework to test the API behaviour. The tests need to cover authentication, ownership, nested validation, shopping list aggregation, and nutrition summary calculations.

No extra package is needed for the shopping list or nutrition summary because both can be handled with Laravel’s database query builder.

---

## Data model

The API needs four main tables:

1. `meal_plans`
2. `meal_plan_days`
3. `meals`
4. `meal_ingredients`

The existing `users` table will be used for coach ownership.

---

## Table: `meal_plans`

Stores the top-level meal plan.

| Column        | Type            | Constraints / notes   |
| ------------- | --------------- | --------------------- |
| `id`          | `bigIncrements` | Primary key           |
| `coach_id`    | `foreignId`     | References `users.id` |
| `name`        | `string(120)`   | Required              |
| `description` | `text`          | Nullable              |
| `created_at`  | `timestamp`     | Laravel timestamps    |
| `updated_at`  | `timestamp`     | Laravel timestamps    |

Migration approach:

```php
Schema::create('meal_plans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
    $table->string('name', 120);
    $table->text('description')->nullable();
    $table->timestamps();
});
```

Ownership rule:

* `coach_id` will always come from the authenticated user.
* The request must not be allowed to set or override `coach_id`.

---

## Table: `meal_plan_days`

Stores the days inside a meal plan.

| Column         | Type                  | Constraints / notes        |
| -------------- | --------------------- | -------------------------- |
| `id`           | `bigIncrements`       | Primary key                |
| `meal_plan_id` | `foreignId`           | References `meal_plans.id` |
| `day_number`   | `unsignedTinyInteger` | Must be 1–7                |
| `created_at`   | `timestamp`           | Laravel timestamps         |
| `updated_at`   | `timestamp`           | Laravel timestamps         |

Migration approach:

```php
Schema::create('meal_plan_days', function (Blueprint $table) {
    $table->id();
    $table->foreignId('meal_plan_id')->constrained('meal_plans')->cascadeOnDelete();
    $table->unsignedTinyInteger('day_number');
    $table->timestamps();

    $table->unique(['meal_plan_id', 'day_number']);
});
```

Important constraint:

```php
$table->unique(['meal_plan_id', 'day_number']);
```

This prevents one meal plan from having two Day 3 records.

I will also validate this in the request so the API returns a clean `422` validation response before the database throws an error.

---

## Table: `meals`

Stores meals inside each day.

| Column             | Type            | Constraints / notes                     |
| ------------------ | --------------- | --------------------------------------- |
| `id`               | `bigIncrements` | Primary key                             |
| `meal_plan_day_id` | `foreignId`     | References `meal_plan_days.id`          |
| `name`             | `string(80)`    | Required                                |
| `meal_type`        | `enum`          | `breakfast`, `lunch`, `dinner`, `snack` |
| `created_at`       | `timestamp`     | Laravel timestamps                      |
| `updated_at`       | `timestamp`     | Laravel timestamps                      |

Migration approach:

```php
Schema::create('meals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('meal_plan_day_id')->constrained('meal_plan_days')->cascadeOnDelete();
    $table->string('name', 80);
    $table->enum('meal_type', ['breakfast', 'lunch', 'dinner', 'snack']);
    $table->timestamps();
});
```

---

## Table: `meal_ingredients`

Stores ingredient servings inside each meal.

| Column            | Type            | Constraints / notes                                      |
| ----------------- | --------------- | -------------------------------------------------------- |
| `id`              | `bigIncrements` | Primary key                                              |
| `meal_id`         | `foreignId`     | References `meals.id`                                    |
| `ingredient_name` | `string(100)`   | Required                                                 |
| `quantity`        | `decimal(8,2)`  | Required and positive                                    |
| `unit`            | `string(20)`    | Allowed values: `g`, `ml`, `cup`, `tbsp`, `tsp`, `piece` |
| `protein_g`       | `decimal(6,2)`  | Nullable                                                 |
| `carbs_g`         | `decimal(6,2)`  | Nullable                                                 |
| `fat_g`           | `decimal(6,2)`  | Nullable                                                 |
| `created_at`      | `timestamp`     | Laravel timestamps                                       |
| `updated_at`      | `timestamp`     | Laravel timestamps                                       |

Migration approach:

```php
Schema::create('meal_ingredients', function (Blueprint $table) {
    $table->id();
    $table->foreignId('meal_id')->constrained('meals')->cascadeOnDelete();
    $table->string('ingredient_name', 100);
    $table->decimal('quantity', 8, 2);
    $table->string('unit', 20);
    $table->decimal('protein_g', 6, 2)->nullable();
    $table->decimal('carbs_g', 6, 2)->nullable();
    $table->decimal('fat_g', 6, 2)->nullable();
    $table->timestamps();
});
```

The positive quantity rule will be handled in validation:

```php
'days.*.meals.*.ingredients.*.quantity' => ['required', 'numeric', 'gt:0']
```

---

## Eloquent models and relationships

### `User`

```php
public function mealPlans()
{
    return $this->hasMany(MealPlan::class, 'coach_id');
}
```

---

### `MealPlan`

```php
public function coach()
{
    return $this->belongsTo(User::class, 'coach_id');
}

public function days()
{
    return $this->hasMany(MealPlanDay::class);
}
```

Fillable fields:

```php
protected $fillable = [
    'coach_id',
    'name',
    'description',
];
```

---

### `MealPlanDay`

```php
public function mealPlan()
{
    return $this->belongsTo(MealPlan::class);
}

public function meals()
{
    return $this->hasMany(Meal::class);
}
```

Fillable fields:

```php
protected $fillable = [
    'meal_plan_id',
    'day_number',
];
```

---

### `Meal`

```php
public function day()
{
    return $this->belongsTo(MealPlanDay::class, 'meal_plan_day_id');
}

public function ingredients()
{
    return $this->hasMany(MealIngredient::class);
}
```

Fillable fields:

```php
protected $fillable = [
    'meal_plan_day_id',
    'name',
    'meal_type',
];
```

---

### `MealIngredient`

```php
public function meal()
{
    return $this->belongsTo(Meal::class);
}
```

Fillable fields:

```php
protected $fillable = [
    'meal_id',
    'ingredient_name',
    'quantity',
    'unit',
    'protein_g',
    'carbs_g',
    'fat_g',
];
```

Casts:

```php
protected $casts = [
    'quantity' => 'decimal:2',
    'protein_g' => 'decimal:2',
    'carbs_g' => 'decimal:2',
    'fat_g' => 'decimal:2',
];
```

---

## Routes

All routes will be protected by `auth:sanctum`.

```php
use App\Http\Controllers\Api\MealPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/meal-plans', [MealPlanController::class, 'index']);
    Route::post('/meal-plans', [MealPlanController::class, 'store']);
    Route::get('/meal-plans/{mealPlan}', [MealPlanController::class, 'show']);
    Route::put('/meal-plans/{mealPlan}', [MealPlanController::class, 'update']);
    Route::delete('/meal-plans/{mealPlan}', [MealPlanController::class, 'destroy']);
    Route::get('/meal-plans/{mealPlan}/shopping-list', [MealPlanController::class, 'shoppingList']);
    Route::get('/meal-plans/{mealPlan}/nutrition-summary', [MealPlanController::class, 'nutritionSummary']);
});
```

---

## Endpoint approach

## `GET /api/meal-plans`

Returns a shallow list of the authenticated coach’s meal plans.

This endpoint will not return all nested days, meals, and ingredients.

Reason:

The brief describes this endpoint as “List coach's meal plans” and describes `GET /api/meal-plans/{id}` separately as “Show full plan.”

Response shape:

```json
{
  "data": [
    {
      "id": 1,
      "coach_id": 1,
      "name": "High Protein Week",
      "description": "Example meal plan",
      "created_at": "2026-06-19T00:00:00.000000Z",
      "updated_at": "2026-06-19T00:00:00.000000Z"
    }
  ]
}
```

Query:

```php
$plans = MealPlan::query()
    ->where('coach_id', $request->user()->id)
    ->latest()
    ->get();
```

---

## `POST /api/meal-plans`

Creates a meal plan with nested days, meals, and ingredients.

I will use a database transaction so partial records are not saved if something fails halfway through.

High-level flow:

```php
DB::transaction(function () use ($request) {
    $mealPlan = MealPlan::create([
        'coach_id' => $request->user()->id,
        'name' => $validated['name'],
        'description' => $validated['description'] ?? null,
    ]);

    foreach ($validated['days'] as $dayData) {
        $day = $mealPlan->days()->create([
            'day_number' => $dayData['day_number'],
        ]);

        foreach ($dayData['meals'] as $mealData) {
            $meal = $day->meals()->create([
                'name' => $mealData['name'],
                'meal_type' => $mealData['meal_type'],
            ]);

            foreach ($mealData['ingredients'] as $ingredientData) {
                $meal->ingredients()->create([
                    'ingredient_name' => $ingredientData['ingredient_name'],
                    'quantity' => $ingredientData['quantity'],
                    'unit' => strtolower($ingredientData['unit']),
                    'protein_g' => $ingredientData['protein_g'] ?? null,
                    'carbs_g' => $ingredientData['carbs_g'] ?? null,
                    'fat_g' => $ingredientData['fat_g'] ?? null,
                ]);
            }
        }
    }

    return $mealPlan;
});
```

Expected response:

* `201 Created`
* created meal plan with nested days, meals, and ingredients

---

## `GET /api/meal-plans/{id}`

Returns the full meal plan.

This includes:

* plan metadata
* days
* meals
* ingredients

I will eager load the nested records:

```php
$mealPlan->load('days.meals.ingredients');
```

Ownership is checked before returning the plan.

---

## `PUT /api/meal-plans/{id}`

Updates only meal plan metadata.

Allowed fields:

* `name`
* `description`

This endpoint will not update days, meals, or ingredients because the brief says “Update plan metadata.”

---

## `DELETE /api/meal-plans/{id}`

Deletes the meal plan.

Nested records are deleted by cascade rules:

* deleting a meal plan deletes its days
* deleting a day deletes its meals
* deleting a meal deletes its ingredients

Expected response:

```json
{
  "message": "Meal plan deleted."
}
```

---

## `GET /api/meal-plans/{id}/shopping-list`

Returns the aggregated shopping list for the whole plan.

### Unit grouping decision

The API will not convert units.

Ingredients are combined only when they have:

1. The same ingredient name, case-insensitive.
2. The same unit.

Examples:

* `100g oats` and `200g oats` combine into `300g oats`.
* `100g oats` and `2 cups oats` stay separate.
* `Chicken Breast` and `chicken breast` combine if the unit is the same.

Reason:

The brief says ingredients with the same name but different units must be listed separately and never combined. Unit conversion is also risky because units like `cup`, `tbsp`, and `piece` depend on ingredient density or item size.

### Shopping list query

The query will join from ingredients back to the meal plan:

```php
$items = DB::table('meal_ingredients')
    ->join('meals', 'meal_ingredients.meal_id', '=', 'meals.id')
    ->join('meal_plan_days', 'meals.meal_plan_day_id', '=', 'meal_plan_days.id')
    ->where('meal_plan_days.meal_plan_id', $mealPlan->id)
    ->selectRaw('LOWER(meal_ingredients.ingredient_name) as ingredient_name')
    ->selectRaw('SUM(meal_ingredients.quantity) as quantity')
    ->addSelect('meal_ingredients.unit')
    ->groupByRaw('LOWER(meal_ingredients.ingredient_name)')
    ->groupBy('meal_ingredients.unit')
    ->orderBy('ingredient_name')
    ->get();
```

The important `GROUP BY` is:

```sql
GROUP BY LOWER(meal_ingredients.ingredient_name), meal_ingredients.unit
```

This ensures:

* case-insensitive name matching
* same-name/different-unit ingredients stay separate

Example result:

```json
{
  "items": [
    {
      "ingredient_name": "chicken breast",
      "quantity": 650,
      "unit": "g"
    },
    {
      "ingredient_name": "oats",
      "quantity": 300,
      "unit": "g"
    },
    {
      "ingredient_name": "oats",
      "quantity": 2,
      "unit": "cup"
    },
    {
      "ingredient_name": "olive oil",
      "quantity": 250,
      "unit": "ml"
    }
  ]
}
```

---

## `GET /api/meal-plans/{id}/nutrition-summary`

Returns calorie and macro totals grouped by day.

### Kcal/g values

I will use the accurate common macronutrient calorie values:

| Macro         | kcal/g |
| ------------- | -----: |
| Protein       |      4 |
| Carbohydrates |      4 |
| Fat           |      9 |

The spreadsheet in the brief incorrectly says fat is 4 kcal/g. The API must use 9 kcal/g for fat.

### Nutrition calculation

```text
total_calories = protein_g * 4 + carbs_g * 4 + fat_g * 9
```

Null macro values will count as zero.

The query will use `COALESCE` so nulls do not break the calculation.

Query approach:

```php
$days = DB::table('meal_plan_days')
    ->leftJoin('meals', 'meal_plan_days.id', '=', 'meals.meal_plan_day_id')
    ->leftJoin('meal_ingredients', 'meals.id', '=', 'meal_ingredients.meal_id')
    ->where('meal_plan_days.meal_plan_id', $mealPlan->id)
    ->select('meal_plan_days.day_number')
    ->selectRaw('COALESCE(SUM(meal_ingredients.protein_g), 0) as total_protein_g')
    ->selectRaw('COALESCE(SUM(meal_ingredients.carbs_g), 0) as total_carbs_g')
    ->selectRaw('COALESCE(SUM(meal_ingredients.fat_g), 0) as total_fat_g')
    ->selectRaw('
        (
            COALESCE(SUM(meal_ingredients.protein_g), 0) * 4
            + COALESCE(SUM(meal_ingredients.carbs_g), 0) * 4
            + COALESCE(SUM(meal_ingredients.fat_g), 0) * 9
        ) as total_calories
    ')
    ->groupBy('meal_plan_days.day_number')
    ->orderBy('meal_plan_days.day_number')
    ->get();
```

The important `GROUP BY` is:

```sql
GROUP BY meal_plan_days.day_number
```

Acceptance example:

```text
165g protein * 4 = 660
220g carbs   * 4 = 880
70g fat      * 9 = 630

660 + 880 + 630 = 2170
```

So this input:

```text
165g protein, 220g carbs, 70g fat
```

must return:

```json
{
  "total_calories": 2170
}
```

---

## Validation approach

I will create two FormRequest classes:

```bash
php artisan make:request StoreMealPlanRequest
php artisan make:request UpdateMealPlanRequest
```

---

## `StoreMealPlanRequest`

Validation rules:

```php
return [
    'name' => ['required', 'string', 'max:120'],
    'description' => ['nullable', 'string'],

    'days' => ['required', 'array', 'min:1'],
    'days.*.day_number' => ['required', 'integer', 'between:1,7', 'distinct'],
    'days.*.meals' => ['required', 'array', 'min:1'],

    'days.*.meals.*.name' => ['required', 'string', 'max:80'],
    'days.*.meals.*.meal_type' => ['required', 'in:breakfast,lunch,dinner,snack'],
    'days.*.meals.*.ingredients' => ['required', 'array', 'min:1'],

    'days.*.meals.*.ingredients.*.ingredient_name' => ['required', 'string', 'max:100'],
    'days.*.meals.*.ingredients.*.quantity' => ['required', 'numeric', 'gt:0'],
    'days.*.meals.*.ingredients.*.unit' => ['required', 'string', 'in:g,ml,cup,tbsp,tsp,piece'],

    'days.*.meals.*.ingredients.*.protein_g' => ['nullable', 'numeric', 'min:0'],
    'days.*.meals.*.ingredients.*.carbs_g' => ['nullable', 'numeric', 'min:0'],
    'days.*.meals.*.ingredients.*.fat_g' => ['nullable', 'numeric', 'min:0'],
];
```

Important validation points:

* `days.*.day_number` uses `distinct` so duplicate days in the same request return `422`.
* `quantity` uses `gt:0` so zero and negative values are rejected.
* unit is restricted to known units to avoid grouping issues caused by typos.
* macro values are nullable, but cannot be negative if provided.

---

## `UpdateMealPlanRequest`

Only metadata can be updated.

```php
return [
    'name' => ['sometimes', 'required', 'string', 'max:120'],
    'description' => ['nullable', 'string'],
];
```

---

## Ownership and authorization approach

All endpoints require login through `auth:sanctum`.

A coach can only access meal plans where:

```php
$mealPlan->coach_id === $request->user()->id
```

Important acceptance rule:

If Coach B requests a plan that belongs to Coach A, the API must return `403`, not `404`.

So I will not hide existing records by only querying through the authenticated coach.

Instead, route model binding can find the plan first. Then I will check ownership manually:

```php
private function authorizeMealPlan(MealPlan $mealPlan, Request $request): void
{
    if ($mealPlan->coach_id !== $request->user()->id) {
        abort(403);
    }
}
```

Expected behaviour:

* Plan does not exist: `404`
* Plan exists but belongs to another coach: `403`
* Plan exists and belongs to current coach: continue

This ownership check must be used on:

* `show`
* `update`
* `destroy`
* `shoppingList`
* `nutritionSummary`

For `index`, the query only returns the authenticated coach’s own plans.

For `store`, the new plan’s `coach_id` comes from the authenticated user.

---

## Decisions for unclear parts of the brief

## 1. Does a meal plan have to contain exactly 7 days?

The brief says a meal plan covers a 7-day week, but it does not explicitly say the create request must include all 7 days at once.

Decision:

I will allow creating a meal plan with one or more days, as long as every provided `day_number` is between 1 and 7 and there are no duplicates.

Reason:

The acceptance criteria only explicitly requires that duplicate day numbers return `422`. If the reviewer wants exactly 7 days, this can be tightened later by validating `days` with `size:7`.

---

## 2. Should `GET /api/meal-plans` return nested days, meals, and ingredients?

Decision:

No. The index endpoint returns a shallow list of plan metadata.

Reason:

The brief says:

* `GET /api/meal-plans` = list coach’s meal plans
* `GET /api/meal-plans/{id}` = show full plan

So the full nested structure belongs on the show endpoint.

---

## 3. Should `PUT /api/meal-plans/{id}` update nested records?

Decision:

No. It updates only plan metadata.

Allowed fields:

* `name`
* `description`

Reason:

The endpoint table says “Update plan metadata,” not “replace full plan.”

---

## 4. Should shopping list units be converted?

Decision:

No. Units are kept separate.

Reason:

The acceptance criteria says ingredients with the same name but different units must be listed separately and never combined.

Example:

```text
100g oats
2 cups oats
```

Expected result:

```json
{
  "items": [
    {
      "ingredient_name": "oats",
      "quantity": 100,
      "unit": "g"
    },
    {
      "ingredient_name": "oats",
      "quantity": 2,
      "unit": "cup"
    }
  ]
}
```

---

## 5. Should ingredient name matching be case-sensitive?

Decision:

No. Ingredient names should combine case-insensitively.

Example:

```text
Chicken Breast 200g
chicken breast 300g
```

Expected result:

```json
{
  "items": [
    {
      "ingredient_name": "chicken breast",
      "quantity": 500,
      "unit": "g"
    }
  ]
}
```

Implementation:

```sql
LOWER(meal_ingredients.ingredient_name)
```

---

## 6. Should ingredient names be changed when stored?

Decision:

I will preserve the submitted ingredient name in the database, but return the normalized lowercase name in the shopping list response.

Reason:

This keeps the user’s original data while still producing consistent grouped shopping list output.

---

## 7. Should units be normalized?

Decision:

Yes. Units will be restricted to lowercase allowed values:

```text
g, ml, cup, tbsp, tsp, piece
```

Reason:

This prevents accidental separation between `g`, `G`, and `grams`.

---

## 8. How should nullable macros be handled?

Decision:

Null macro values count as zero in the nutrition summary.

Reason:

The macro columns are nullable. If nulls are not converted to zero, totals can become incorrect or null.

Implementation:

```sql
COALESCE(SUM(meal_ingredients.protein_g), 0)
COALESCE(SUM(meal_ingredients.carbs_g), 0)
COALESCE(SUM(meal_ingredients.fat_g), 0)
```

---

## 9. Should deletes be soft deletes or hard deletes?

Decision:

Use hard deletes with cascading foreign keys.

Reason:

The brief says delete the meal plan and all nested records. It does not mention soft deletes or recovery.

---

## Edge cases and how I will handle them

## Duplicate day numbers

Problem:

A meal plan cannot have two Day 3 records.

Handling:

* FormRequest uses `distinct` on `days.*.day_number`.
* Database has unique constraint on `meal_plan_id` and `day_number`.

Expected response:

```text
422 Unprocessable Entity
```

---

## Quantity is zero or negative

Problem:

Ingredient quantity must be positive.

Handling:

```php
'days.*.meals.*.ingredients.*.quantity' => ['required', 'numeric', 'gt:0']
```

Expected response:

```text
422 Unprocessable Entity
```

---

## Same ingredient, same unit, different case

Problem:

`Chicken Breast` and `chicken breast` should combine.

Handling:

Group by:

```sql
LOWER(meal_ingredients.ingredient_name), meal_ingredients.unit
```

Expected result:

```text
Chicken Breast 200g + chicken breast 300g = chicken breast 500g
```

---

## Same ingredient, different unit

Problem:

`oats 100g` and `oats 2 cup` should not combine.

Handling:

The shopping list groups by both ingredient name and unit.

Expected result:

```json
{
  "items": [
    {
      "ingredient_name": "oats",
      "quantity": 100,
      "unit": "g"
    },
    {
      "ingredient_name": "oats",
      "quantity": 2,
      "unit": "cup"
    }
  ]
}
```

---

## Incorrect spreadsheet calorie value

Problem:

The spreadsheet says fat is 4 kcal/g, but fat should be 9 kcal/g.

Handling:

Use:

```text
protein = 4 kcal/g
carbs = 4 kcal/g
fat = 9 kcal/g
```

Expected result:

```text
165 protein * 4 = 660
220 carbs * 4 = 880
70 fat * 9 = 630

total = 2170
```

---

## Null macro values

Problem:

An ingredient may have null macro values.

Handling:

Null macros count as zero.

Example:

```text
protein_g = null
```

is treated as:

```text
protein_g = 0
```

---

## Another coach accesses a plan

Problem:

Coach B calls:

```text
GET /api/meal-plans/42
```

where plan 42 belongs to Coach A.

Handling:

* Find the plan normally.
* Check `coach_id`.
* If it belongs to someone else, abort with `403`.

Expected response:

```text
403 Forbidden
```

Not:

```text
404 Not Found
```

---

## Missing meal plan

Problem:

A user requests a plan ID that does not exist.

Handling:

Route model binding returns:

```text
404 Not Found
```

This is different from ownership failure.

---

## Empty or partial weekly plan

Problem:

The brief says a plan covers 7 days, but does not clearly say all 7 days must exist on creation.

Handling:

Allow one or more days for now. Each day must be valid and unique.

If exact 7-day creation is required later, change:

```php
'days' => ['required', 'array', 'size:7']
```

---

## Testing plan

I will create a feature test file for the meal plan API.

Main tests:

1. Guest users cannot access meal plan endpoints.
2. Authenticated coach can create a meal plan with nested days, meals, and ingredients.
3. Duplicate `day_number` returns `422`.
4. Nested ingredient quantity validation rejects zero and negative values.
5. `GET /api/meal-plans` returns only the authenticated coach’s shallow plan list.
6. `GET /api/meal-plans/{id}` returns the full nested plan.
7. `PUT /api/meal-plans/{id}` updates only metadata.
8. `DELETE /api/meal-plans/{id}` deletes the plan and nested records.
9. Coach B accessing Coach A’s plan returns `403`.
10. Shopping list combines same ingredient name and same unit.
11. Shopping list combines ingredient names case-insensitively.
12. Shopping list keeps same ingredient with different units separate.
13. Nutrition summary uses protein 4, carbs 4, fat 9.
14. Day 1 with 165g protein, 220g carbs, and 70g fat returns `2170`.
15. Null macro values count as zero.

---

## Final implementation order

1. Set up Laravel project and SQLite.
2. Install/configure API auth.
3. Write migrations.
4. Create models and relationships.
5. Create FormRequests.
6. Create routes and controller.
7. Implement ownership helper.
8. Implement CRUD endpoints.
9. Implement shopping list aggregation.
10. Implement nutrition summary.
11. Write failing tests.
12. Make tests pass.
13. Paste before/after terminal output into `BEFORE-AFTER.md`.
14. Run final full test suite.

What I need to make

I need to make a laravel JSON API for coach-created meal plans

I need to let a coach create a meal plan for their clients.

Each meal plan will work for all 7 days of the week. Each day will contain 1 or more meals such as breakfast, lunch, dinner, or a snack.
Every meal will have the ingredients with quantities, units, and optional macro values for that serving.

The API also needs to generate two calculated views from the saved meal plan data:

A weekly shopping list that combines ingredient quantities across the whole meal plan.
A daily nutrition summary that totals protein, carbohydrates, fat, and calories for each day.

All endpoints must use Sanctum authentication. Coaches can only manage their own meal plans.

--------------------------------------------------------------------------------------------------------------------------------------------------

What inputs does it take?

    Create a meal plan

        POST /api/meal-plans

Expected input will include meal plan metadata and nested days, meals, and ingredients.

Example structure:

```json
{
  "name": "High Protein Week",
  "description": "Example meal plan",
  "days": [
    {
      "day_number": 1,
      "meals": [
        {
          "name": "Breakfast",
          "meal_type": "breakfast",
          "ingredients": [
            {
              "ingredient_name": "oats",
              "quantity": 100,
              "unit": "g",
              "protein_g": 17,
              "carbs_g": 66,
              "fat_g": 7
            }
          ]
        }
      ]
    }
  ]
}
```

Validation Rules

    - Name is required (Max 120 Characters)
    - Description is nullable
    - Days must contain valid day objects.
    - days.*.day_number must be between 1 and 7.
    - A meal plan cannot have duplicate day_number values.
    - A meal plan cannot have duplicate day_number values.
    - Days.*.meals must contain one or more meals.
    - Meal_type must be one of: breakfast, lunch, dinner, snack.
    - Ingredients.*.ingredient_name is required and max 100 characters.
    - Ingredients.*.quantity is required and must be positive.
    - Ingredients.*.unit is required and max 20 characters.
    - Macro values are nullable decimals.

--------------------------------------------------------------------------------------------------------------------------------------------------

What it'll return?

    GET /api/meal-plans

        Returns a list of the authenticated coach’s meal plans.

This will be a shallow list. that shows a JSON list of the coaches meal plans
GET /api/meal-plans/{id} will show the full plan

Expected response data should include plan-level metadata only, such as:

- id
- coach_id
- name
- description
- created_at
- updated_at

----------------------------------------

POST /api/meal-plans

    Creates a meal plan with nested days, meals, and ingredients.

----------------------------------------

GET /api/meal-plans/{id}

    Returns the full meal plan with:

    plan metadata
    days
    meals
    ingredients

    Only the owning coach can access it.

----------------------------------------

PUT /api/meal-plans/{id}

    Updates meal plan metadata only.

    The endpoint table says “Update plan metadata,” so I am assuming this updates only:

    name
    description

    It does not update nested days, meals, or ingredients unless later clarified.PUT /api/meal-plans/{id}

    Updates meal plan metadata only.

    The endpoint table says “Update plan metadata,” so I am assuming this updates only:

    name
    description

    It does not update nested days, meals, or ingredients unless later clarified.

----------------------------------------

DELETE /api/meal-plans/{id}

    Deletes the meal plan and all nested records.

    That means deleting:

    meal plan
    meal plan days
    meals
    meal ingredients

    This can be handled with database cascade deletes.

----------------------------------------

GET /api/meal-plans/{id}/shopping-list

    Returns all ingredients across the plan, grouped by ingredient name and unit.

Example response:

```json
{
  "items": [
    { "ingredient_name": "chicken breast", "quantity": 650, "unit": "g" },
    { "ingredient_name": "oats", "quantity": 300, "unit": "g" },
    { "ingredient_name": "olive oil", "quantity": 250, "unit": "ml" }
  ]
}
```

----------------------------------------

GET /api/meal-plans/{id}/nutrition-summary

    Returns per-day nutrition totals.

Example response:

```json
{
  "days": [
    {
      "day_number": 1,
      "total_protein_g": 165,
      "total_carbs_g": 220,
      "total_fat_g": 70,
      "total_calories": 2170
    }
  ]
}
```

--------------------------------------------------------------------------------------------------------------------------------------------------

Unit-handling decision for the shopping list

    The shopping list should not convert units.

    Ingredients should only be combined when they have:

    The same ingredient name, case-insensitive.
    The same unit.

    This means:

    100g oats and 200g oats combine into 300g oats.
    100g oats and 2 cups oats do not combine.
    Chicken Breast and chicken breast do combine if the unit is the same.

    Unit conversion would be risky because units like cup, tbsp, piece, and ml depend on the ingredient’s density or item size. For example, 1 cup of oats and 1 cup of olive oil do not weigh the same.

--------------------------------------------------------------------------------------------------------------------------------------------------

Kcal/g values

    The accurate general nutrition values I will use are:

    Macro	            Correct kcal/g
    Protein	            4
    Carbohydrates	    4
    Fat	                9

    So the calorie calculation should be:

total_calories = protein_g * 4 + carbs_g * 4 + fat_g * 9

This is why the acceptance example works:

165g protein * 4 = 660
220g carbs   * 4 = 880
70g fat      * 9 = 630

660 + 880 + 630 = 2170 calories

Therefore a plan with Day 1 containing 165g protein, 220g carbs, and 70g fat must return:

{
  "total_calories": 2170
}

--------------------------------------------------------------------------------------------------------------------------------------------------

Worked shopping list aggregation example

Given these ingredients:

Day 1 Breakfast
200g chicken breast — 25g protein, 0g carbs, 4g fat
100g oats — 17g protein, 66g carbs, 7g fat
Day 1 Lunch
300g chicken breast — 38g protein, 0g carbs, 5g fat
150ml olive oil — 0g protein, 0g carbs, 135g fat
Day 2 Dinner
150g chicken breast — 19g protein, 0g carbs, 3g fat
200g oats — 34g protein, 132g carbs, 14g fat
100ml olive oil — 0g protein, 0g carbs, 90g fat

The shopping list combines by lowercased ingredient name and exact unit.

Expected shopping list response:

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
      "ingredient_name": "olive oil",
      "quantity": 250,
      "unit": "ml"
    }
  ]
}
```
--------------------------------------------------------------------------------------------------------------------------------------------------

Nutrition summary for Day 1

Day 1 contains:

Day 1 Breakfast
chicken breast: 25g protein, 0g carbs, 4g fat
oats: 17g protein, 66g carbs, 7g fat

Breakfast subtotal:

protein = 25 + 17 = 42g
carbs   = 0 + 66 = 66g
fat     = 4 + 7 = 11g
Day 1 Lunch
chicken breast: 38g protein, 0g carbs, 5g fat
olive oil: 0g protein, 0g carbs, 135g fat

Lunch subtotal:

protein = 38 + 0 = 38g
carbs   = 0 + 0 = 0g
fat     = 5 + 135 = 140g
Day 1 total macros
protein = 42 + 38 = 80g
carbs   = 66 + 0 = 66g
fat     = 11 + 140 = 151g
Day 1 calories

Using the correct kcal/g values:

protein calories = 80 * 4 = 320
carb calories    = 66 * 4 = 264
fat calories     = 151 * 9 = 1359

total calories = 320 + 264 + 1359 = 1943

Expected Day 1 nutrition summary:

```json
{
  "day_number": 1,
  "total_protein_g": 80,
  "total_carbs_g": 66,
  "total_fat_g": 151,
  "total_calories": 1943
}
```

--------------------------------------------------------------------------------------------------------------------------------------------------

Does a meal plan have to contain exactly 7 days on creation?

    I will validate that each provided day has a day_number from 1 to 7 and that there are no duplicates. Unless clarified otherwise, I would allow creating a partial plan with fewer than 7 days

-------------------------------------------------------------

Should GET /api/meal-plans include nested days, meals, and ingredients?

    The index endpoint should return a shallow list for performance. The show endpoint should return the full nested plan.

-------------------------------------------------------------

Should nested records be editable through PUT /api/meal-plans/{id}?

    The PUT /api/meal-plans/{id} endpoint updates the plan metadata like name and description. It does not replace or patch days, meals, or ingredients.

-------------------------------------------------------------

Should deleting a meal plan use soft deletes or hard deletes?

use hard deletes with cascading foreign keys because the data model does not include deleted_at

-------------------------------------------------------------

How should nullable macro values be treated in nutrition totals?

null macro values should count as 0 in the nutrition summary.

-------------------------------------------------------------

Should ingredient name normalization change stored data?

Ingredient name matching must be case-insensitive for the shopping list

I will preserve the original ingredient name when storing records, but use LOWER(ingredient_name) in the shopping list query for grouping. The response can return the normalized lowercase name so combined rows are consistent.

-------------------------------------------------------------

Should unit matching be case-sensitive?

I will normalize the allowed units to lowercase values, such as g, ml, cup, tbsp, tsp, and piece. This avoids g and G being treated as separate units by accident.

-------------------------------------------------------------

Should the allowed units be restricted to the listed examples?

The table list units: g, ml, cup, tbsp, tsp, piece.

I will restrict units to those values because shopping list grouping depends on consistent unit names.

-------------------------------------------------------------

Should authorization failure return 403 or 404?

A coach cannot access another coach’s meal plan and this must return 403

If the plan does not exist at all, return 404.


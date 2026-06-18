Step 1

    Project set up
                1. Start new Laravel project
                2. connect to Github repo
                                                                                                    10 mins

----------------------------------------------------------------------------------------------------------------

Step 2

    Documentation
                1. Write out the Understand.md
                2. Write out the Time Estimate.md
                3. Add the Ai Time estimate to the Estimate.md
                4. Write out the Aproach.md
                                                                                                        120 mins

----------------------------------------------------------------------------------------------------------------

Step 3

    Finish Project set up
                1. Install dependencies
                2. Install Sanctum
                3. Install Pest
                4. Confirm API/auth setup
                                                                                                    20 mins

----------------------------------------------------------------------------------------------------------------

Step 4 

    Database Migrations

                1. Create migrations
                        php artisan make:migration create_meal_plans_table
                        php artisan make:migration create_meal_plan_days_table
                        php artisan make:migration create_meals_table
                        php artisan make:migration create_meal_ingredients_table
                
                2. Build meal_plans
                        coach_id foreign key to users.
                        name string length 120.
                        description nullable text.
                        timestamps.
                
                3. Build meal_plan_days
                        meal_plan_id foreign key.
                        day_number unsigned tiny integer.
                        unique constraint:
                
                4. Build meals
                        meal_plan_day_id foreign key.
                        name string length 80.
                        meal_type enum: breakfast, lunch, dinner, snack.
                        timestamps.
                
                5. Build meal_ingredients
                        meal_id foreign key.
                        ingredient_name string length 100.
                        quantity decimal 8,2.
                        unit string length 20.
                        nullable macro decimals.
                        timestamps.
                
                6. Add cascade deletes
                        Deleting a meal plan deletes days.
                        Deleting a day deletes meals.
                        Deleting a meal deletes ingredients.
                
                7. Run migrations
                                                                                                    30 mins

----------------------------------------------------------------------------------------------------------------

Step 5

    Models

                1. Create models

                2. Add fillable fields
                        MealPlan
                            coach_id
                            name
                            description
                            MealPlanDay
                            meal_plan_id
                            day_number
                        Meal
                            meal_plan_day_id
                            name
                            meal_type
                        MealIngredient
                            meal_id
                            ingredient_name
                            quantity
                            unit
                            protein_g
                            carbs_g
                            fat_g
                
                3. Add casts
                        Use decimal casts for:
                            quantity
                            protein_g
                            carbs_g
                            fat_g
                
                4. Add relationships
                                                                                                    30 mins

----------------------------------------------------------------------------------------------------------------

Step 6

    Form Requests

                1. Create requests
                2. StoreMealPlanRequest
                        Validate:
                            name
                            description
                            days
                            days.*.day_number
                            days.*.meals
                            days.*.meals.*.name
                            days.*.meals.*.meal_type
                            days.*.meals.*.ingredients
                            days.*.meals.*.ingredients.*.ingredient_name
                            days.*.meals.*.ingredients.*.quantity
                            days.*.meals.*.ingredients.*.unit
                            macro values
                
                3. Duplicate day validation
                            Add validation to stop duplicate day numbers in the same request.
                
                4. UpdateMealPlanRequest
                            Validate only:
                                name
                                description
                                                                                                    35 mins

----------------------------------------------------------------------------------------------------------------

Step 7
    
    Controller and Routes

                1. Create controller
                2. Add routes in routes/api.php
                            Route::middleware('auth:sanctum')->group(function () {
                                Route::get('/meal-plans', [MealPlanController::class, 'index']);
                                Route::post('/meal-plans', [MealPlanController::class, 'store']);
                                Route::get('/meal-plans/{mealPlan}', [MealPlanController::class, 'show']);
                                Route::put('/meal-plans/{mealPlan}', [MealPlanController::class, 'update']);
                                Route::delete('/meal-plans/{mealPlan}', [MealPlanController::class, 'destroy']);
                                Route::get('/meal-plans/{mealPlan}/shopping-list', [MealPlanController::class, 'shoppingList']);
                                Route::get('/meal-plans/{mealPlan}/nutrition-summary', [MealPlanController::class, 'nutritionSummary']);
                            });

                3. Add ownership checks
                                                                                                    30 mins

----------------------------------------------------------------------------------------------------------------

Step 8 

    Implement Endpoints

                1. index
                    Return only authenticated coach’s meal plans.
                    Shallow list only.
                2. store
                    Create meal plan.
                    Create nested days.
                    Create nested meals.
                    Create nested ingredients.
                    Wrap in database transaction.
                3. show
                    Check ownership.
                    Return full nested plan.
                4. update
                    Check ownership.
                    Update name and description.
                5. destroy
                    Check ownership.
                    Delete meal plan.
                    Rely on cascade deletes.
                6. shoppingList
                    Check ownership.
                    Join through ingredients → meals → days.
                    Filter by meal plan ID.
                    Group by lowercased ingredient name and unit.
                    Sum quantity.
                7. nutritionSummary
                    Check ownership.
                    Join through ingredients → meals → days.
                    Group by day number.
                    Sum protein, carbs, fat.
                    Calculate calories using 4 / 4 / 9.
                                                                                                    45 mins

----------------------------------------------------------------------------------------------------------------

Step 9

    Tests

                1. Create feature test
                2. Test authentication
                        Guest cannot access meal plan endpoints.
                        Authenticated user can access own endpoints.

                3. Test creating a meal plan
                        Can create plan with days, meals, ingredients.
                        Nested records are saved.

                4. Test duplicate day number
                        Creating two Day 3 entries returns 422.

                5. Test quantity validation
                        Negative or zero quantity returns 422.

                        Cover path:
                        
                        days.*.meals.*.ingredients.*.quantity

                6. Test show full plan
                        Owner can see nested plan.

                7. Test update metadata
                        Owner can update name and description.
                    
                8. Test delete
                        Owner can delete plan.
                        Nested records are removed.
                9. Test ownership
                        Coach B accessing Coach A’s plan returns 403.
                        Test show, update, delete, shopping list, and nutrition summary if possible.
                10. Test shopping list aggregation

                        Cover:

                        Same name and same unit combines.
                        Different case combines.
                        Same name but different unit stays separate.

                        Example:

                        Oats 100g + oats 200g = oats 300g
                        Oats 2 cup remains separate
                        11. Test nutrition summary
                        Day 1 with 165g protein, 220g carbs, 70g fat returns 2170.
                        Null macro values count as 0.
                        Per-day totals are grouped correctly.
                                                                                                    120 mins

----------------------------------------------------------------------------------------------------------------

Step 15
    
    Before/After Evidence
            1. Create BEFORE-AFTER.md
                                                                                                        20 mins

----------------------------------------------------------------------------------------------------------------

                                                                                                    7.5 hrs

---------------------------------------------------------------------------------------------------------------- 

## AI Estimate

Step	                                  |  My estimate	       |         AI estimate


Step 1 — Project setup	                  |     10 mins	           |         15 mins

Step 2 — Documentation	                  |     120 mins           | 	     90–120 mins

Step 3 — Finish setup / Sanctum / Pest	  |     20 mins	           |         25 mins

Step 4 — Database migrations	          |     30 mins	           |         35–45 mins

Step 5 — Models	                          |     30 mins	           |         25–35 mins

Step 6 — Form Requests	                  |     35 mins	           |         45–60 mins

Step 7 — Controller and routes	          |     30 mins	           |         30–40 mins

Step 8 — Implement endpoints	          |     45 mins	           |         75–90 mins

Step 9 — Tests	                          |     120 mins	       |         150–180 mins

Step 10 — BEFORE-AFTER.md	              |     20 mins	           |         20–30 mins

AI total estimate

Best case: 8 hours
Realistic quote: 9 hours
Safe quote: 9.5–10 hours

AI Estimated total: 9 hours

The original 7.5 hour estimate is possible if setup goes smoothly, but I would quote 9 hours because this task has several areas that can take longer than expected:

- nested create validation for days, meals, and ingredients
- duplicate day validation returning 422
- ownership checks that must return 403 instead of 404
- shopping list aggregation using case-insensitive ingredient names
- nutrition summary calculations using correct kcal/g values
- feature tests covering authentication, ownership, aggregation, and validation

The highest-risk sections are Form Requests, endpoint implementation, and tests.
<?php

namespace Tests\Feature;

use App\Models\Meal;
use App\Models\MealIngredient;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPlanApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'        => 'High Protein Week',
            'description' => 'Test plan',
            'days'        => [
                [
                    'day_number' => 1,
                    'meals'      => [
                        [
                            'name'        => 'Breakfast',
                            'meal_type'   => 'breakfast',
                            'ingredients' => [
                                [
                                    'ingredient_name' => 'oats',
                                    'quantity'        => 100,
                                    'unit'            => 'g',
                                    'protein_g'       => 17,
                                    'carbs_g'         => 66,
                                    'fat_g'           => 7,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $overrides);
    }

    private function createPlanForCoach(User $coach, array $overrides = []): MealPlan
    {
        $plan = MealPlan::create([
            'coach_id'    => $coach->id,
            'name'        => $overrides['name'] ?? 'Test Plan',
            'description' => $overrides['description'] ?? null,
        ]);

        $day = MealPlanDay::create([
            'meal_plan_id' => $plan->id,
            'day_number'   => 1,
        ]);

        $meal = Meal::create([
            'meal_plan_day_id' => $day->id,
            'name'             => 'Breakfast',
            'meal_type'        => 'breakfast',
        ]);

        MealIngredient::create([
            'meal_id'         => $meal->id,
            'ingredient_name' => 'oats',
            'quantity'        => 100,
            'unit'            => 'g',
            'protein_g'       => 17,
            'carbs_g'         => 66,
            'fat_g'           => 7,
        ]);

        return $plan;
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_meal_plan_endpoints(): void
    {
        $this->getJson('/api/meal-plans')->assertUnauthorized();
        $this->postJson('/api/meal-plans')->assertUnauthorized();
    }

    public function test_authenticated_coach_can_access_index(): void
    {
        $coach = User::factory()->create();

        $this->actingAs($coach)->getJson('/api/meal-plans')->assertOk();
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_coach_can_create_meal_plan_with_nested_records(): void
    {
        $coach = User::factory()->create();

        $response = $this->actingAs($coach)
            ->postJson('/api/meal-plans', $this->validPayload());

        $response->assertStatus(201);

        $this->assertDatabaseHas('meal_plans', ['name' => 'High Protein Week', 'coach_id' => $coach->id]);
        $this->assertDatabaseHas('meal_plan_days', ['day_number' => 1]);
        $this->assertDatabaseHas('meals', ['name' => 'Breakfast', 'meal_type' => 'breakfast']);
        $this->assertDatabaseHas('meal_ingredients', ['ingredient_name' => 'oats', 'quantity' => 100]);
    }

    public function test_store_returns_nested_plan_in_response(): void
    {
        $coach = User::factory()->create();

        $response = $this->actingAs($coach)
            ->postJson('/api/meal-plans', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'name', 'coach_id',
                'days' => [
                    '*' => [
                        'day_number',
                        'meals' => [
                            '*' => [
                                'name', 'meal_type',
                                'ingredients' => [
                                    '*' => ['ingredient_name', 'quantity', 'unit'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    // -------------------------------------------------------------------------
    // Duplicate day number
    // -------------------------------------------------------------------------

    public function test_duplicate_day_number_returns_422(): void
    {
        $coach = User::factory()->create();

        $payload = $this->validPayload();
        $payload['days'][1] = $payload['days'][0];

        $this->actingAs($coach)
            ->postJson('/api/meal-plans', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['days.0.day_number', 'days.1.day_number']);
    }

    // -------------------------------------------------------------------------
    // Quantity validation
    // -------------------------------------------------------------------------

    public function test_zero_quantity_returns_422(): void
    {
        $coach = User::factory()->create();

        $payload = $this->validPayload();
        $payload['days'][0]['meals'][0]['ingredients'][0]['quantity'] = 0;

        $this->actingAs($coach)
            ->postJson('/api/meal-plans', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['days.0.meals.0.ingredients.0.quantity']);
    }

    public function test_negative_quantity_returns_422(): void
    {
        $coach = User::factory()->create();

        $payload = $this->validPayload();
        $payload['days'][0]['meals'][0]['ingredients'][0]['quantity'] = -5;

        $this->actingAs($coach)
            ->postJson('/api/meal-plans', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['days.0.meals.0.ingredients.0.quantity']);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_owner_can_see_full_nested_plan(): void
    {
        $coach = User::factory()->create();
        $plan  = $this->createPlanForCoach($coach);

        $this->actingAs($coach)
            ->getJson("/api/meal-plans/{$plan->id}")
            ->assertOk()
            ->assertJsonStructure([
                'id', 'name',
                'days' => [
                    '*' => [
                        'day_number',
                        'meals' => [
                            '*' => [
                                'name',
                                'ingredients' => [['ingredient_name', 'quantity', 'unit']],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_owner_can_update_plan_metadata(): void
    {
        $coach = User::factory()->create();
        $plan  = $this->createPlanForCoach($coach);

        $this->actingAs($coach)
            ->putJson("/api/meal-plans/{$plan->id}", [
                'name'        => 'Updated Name',
                'description' => 'Updated description',
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('meal_plans', ['id' => $plan->id, 'name' => 'Updated Name']);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function test_owner_can_delete_plan_and_nested_records_are_removed(): void
    {
        $coach = User::factory()->create();
        $plan  = $this->createPlanForCoach($coach);

        $this->actingAs($coach)
            ->deleteJson("/api/meal-plans/{$plan->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('meal_plans', ['id' => $plan->id]);
        $this->assertDatabaseMissing('meal_plan_days', ['meal_plan_id' => $plan->id]);
        $this->assertDatabaseMissing('meals', ['name' => 'Breakfast']);
        $this->assertDatabaseMissing('meal_ingredients', ['ingredient_name' => 'oats']);
    }

    // -------------------------------------------------------------------------
    // Ownership — 403 vs 404
    // -------------------------------------------------------------------------

    public function test_coach_b_accessing_coach_a_plan_returns_403_on_show(): void
    {
        $coachA = User::factory()->create();
        $coachB = User::factory()->create();
        $plan   = $this->createPlanForCoach($coachA);

        $this->actingAs($coachB)
            ->getJson("/api/meal-plans/{$plan->id}")
            ->assertForbidden();
    }

    public function test_coach_b_cannot_update_coach_a_plan(): void
    {
        $coachA = User::factory()->create();
        $coachB = User::factory()->create();
        $plan   = $this->createPlanForCoach($coachA);

        $this->actingAs($coachB)
            ->putJson("/api/meal-plans/{$plan->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_coach_b_cannot_delete_coach_a_plan(): void
    {
        $coachA = User::factory()->create();
        $coachB = User::factory()->create();
        $plan   = $this->createPlanForCoach($coachA);

        $this->actingAs($coachB)
            ->deleteJson("/api/meal-plans/{$plan->id}")
            ->assertForbidden();
    }

    public function test_coach_b_cannot_access_coach_a_shopping_list(): void
    {
        $coachA = User::factory()->create();
        $coachB = User::factory()->create();
        $plan   = $this->createPlanForCoach($coachA);

        $this->actingAs($coachB)
            ->getJson("/api/meal-plans/{$plan->id}/shopping-list")
            ->assertForbidden();
    }

    public function test_coach_b_cannot_access_coach_a_nutrition_summary(): void
    {
        $coachA = User::factory()->create();
        $coachB = User::factory()->create();
        $plan   = $this->createPlanForCoach($coachA);

        $this->actingAs($coachB)
            ->getJson("/api/meal-plans/{$plan->id}/nutrition-summary")
            ->assertForbidden();
    }

    public function test_nonexistent_plan_returns_404(): void
    {
        $coach = User::factory()->create();

        $this->actingAs($coach)
            ->getJson('/api/meal-plans/9999')
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Shopping list aggregation
    // -------------------------------------------------------------------------

    public function test_shopping_list_combines_same_name_and_unit(): void
    {
        $coach = User::factory()->create();
        $plan  = MealPlan::create(['coach_id' => $coach->id, 'name' => 'Plan']);

        $day1 = MealPlanDay::create(['meal_plan_id' => $plan->id, 'day_number' => 1]);
        $day2 = MealPlanDay::create(['meal_plan_id' => $plan->id, 'day_number' => 2]);

        $meal1 = Meal::create(['meal_plan_day_id' => $day1->id, 'name' => 'Breakfast', 'meal_type' => 'breakfast']);
        $meal2 = Meal::create(['meal_plan_day_id' => $day2->id, 'name' => 'Lunch', 'meal_type' => 'lunch']);

        MealIngredient::create(['meal_id' => $meal1->id, 'ingredient_name' => 'oats', 'quantity' => 100, 'unit' => 'g']);
        MealIngredient::create(['meal_id' => $meal2->id, 'ingredient_name' => 'oats', 'quantity' => 200, 'unit' => 'g']);

        $this->actingAs($coach)
            ->getJson("/api/meal-plans/{$plan->id}/shopping-list")
            ->assertOk()
            ->assertJsonFragment(['ingredient_name' => 'oats', 'quantity' => '300.00', 'unit' => 'g']);
    }

    public function test_shopping_list_combines_different_case_ingredient_names(): void
    {
        $coach = User::factory()->create();
        $plan  = MealPlan::create(['coach_id' => $coach->id, 'name' => 'Plan']);

        $day1 = MealPlanDay::create(['meal_plan_id' => $plan->id, 'day_number' => 1]);
        $day2 = MealPlanDay::create(['meal_plan_id' => $plan->id, 'day_number' => 2]);

        $meal1 = Meal::create(['meal_plan_day_id' => $day1->id, 'name' => 'Breakfast', 'meal_type' => 'breakfast']);
        $meal2 = Meal::create(['meal_plan_day_id' => $day2->id, 'name' => 'Lunch', 'meal_type' => 'lunch']);

        MealIngredient::create(['meal_id' => $meal1->id, 'ingredient_name' => 'Chicken Breast', 'quantity' => 200, 'unit' => 'g']);
        MealIngredient::create(['meal_id' => $meal2->id, 'ingredient_name' => 'chicken breast', 'quantity' => 300, 'unit' => 'g']);

        $this->actingAs($coach)
            ->getJson("/api/meal-plans/{$plan->id}/shopping-list")
            ->assertOk()
            ->assertJsonFragment(['ingredient_name' => 'chicken breast', 'quantity' => '500.00', 'unit' => 'g']);
    }

    public function test_shopping_list_keeps_same_name_different_unit_separate(): void
    {
        $coach = User::factory()->create();
        $plan  = MealPlan::create(['coach_id' => $coach->id, 'name' => 'Plan']);

        $day1 = MealPlanDay::create(['meal_plan_id' => $plan->id, 'day_number' => 1]);
        $day2 = MealPlanDay::create(['meal_plan_id' => $plan->id, 'day_number' => 2]);

        $meal1 = Meal::create(['meal_plan_day_id' => $day1->id, 'name' => 'Breakfast', 'meal_type' => 'breakfast']);
        $meal2 = Meal::create(['meal_plan_day_id' => $day2->id, 'name' => 'Lunch', 'meal_type' => 'lunch']);

        MealIngredient::create(['meal_id' => $meal1->id, 'ingredient_name' => 'oats', 'quantity' => 100, 'unit' => 'g']);
        MealIngredient::create(['meal_id' => $meal2->id, 'ingredient_name' => 'oats', 'quantity' => 2,   'unit' => 'cup']);

        $response = $this->actingAs($coach)
            ->getJson("/api/meal-plans/{$plan->id}/shopping-list")
            ->assertOk();

        $items = $response->json('items');
        $this->assertCount(2, $items);
    }

    // -------------------------------------------------------------------------
    // Nutrition summary
    // -------------------------------------------------------------------------

    public function test_nutrition_summary_calculates_calories_correctly(): void
    {
        $coach = User::factory()->create();
        $plan  = MealPlan::create(['coach_id' => $coach->id, 'name' => 'Plan']);
        $day   = MealPlanDay::create(['meal_plan_id' => $plan->id, 'day_number' => 1]);
        $meal  = Meal::create(['meal_plan_day_id' => $day->id, 'name' => 'Lunch', 'meal_type' => 'lunch']);

        MealIngredient::create([
            'meal_id'         => $meal->id,
            'ingredient_name' => 'chicken',
            'quantity'        => 100,
            'unit'            => 'g',
            'protein_g'       => 165,
            'carbs_g'         => 220,
            'fat_g'           => 70,
        ]);

        $this->actingAs($coach)
            ->getJson("/api/meal-plans/{$plan->id}/nutrition-summary")
            ->assertOk()
            ->assertJsonFragment([
                'day_number'      => 1,
                'total_protein_g' => '165.00',
                'total_carbs_g'   => '220.00',
                'total_fat_g'     => '70.00',
                'total_calories'  => '2170.00',
            ]);
    }

    public function test_nutrition_summary_treats_null_macros_as_zero(): void
    {
        $coach = User::factory()->create();
        $plan  = MealPlan::create(['coach_id' => $coach->id, 'name' => 'Plan']);
        $day   = MealPlanDay::create(['meal_plan_id' => $plan->id, 'day_number' => 1]);
        $meal  = Meal::create(['meal_plan_day_id' => $day->id, 'name' => 'Lunch', 'meal_type' => 'lunch']);

        MealIngredient::create([
            'meal_id'         => $meal->id,
            'ingredient_name' => 'water',
            'quantity'        => 500,
            'unit'            => 'ml',
            'protein_g'       => null,
            'carbs_g'         => null,
            'fat_g'           => null,
        ]);

        $this->actingAs($coach)
            ->getJson("/api/meal-plans/{$plan->id}/nutrition-summary")
            ->assertOk()
            ->assertJsonFragment([
                'total_protein_g' => '0.00',
                'total_carbs_g'   => '0.00',
                'total_fat_g'     => '0.00',
                'total_calories'  => '0.00',
            ]);
    }

    public function test_nutrition_summary_groups_by_day(): void
    {
        $coach = User::factory()->create();
        $plan  = MealPlan::create(['coach_id' => $coach->id, 'name' => 'Plan']);

        foreach ([1, 2] as $dayNumber) {
            $day  = MealPlanDay::create(['meal_plan_id' => $plan->id, 'day_number' => $dayNumber]);
            $meal = Meal::create(['meal_plan_day_id' => $day->id, 'name' => 'Lunch', 'meal_type' => 'lunch']);
            MealIngredient::create([
                'meal_id'         => $meal->id,
                'ingredient_name' => 'chicken',
                'quantity'        => 100,
                'unit'            => 'g',
                'protein_g'       => 30,
                'carbs_g'         => 0,
                'fat_g'           => 5,
            ]);
        }

        $response = $this->actingAs($coach)
            ->getJson("/api/meal-plans/{$plan->id}/nutrition-summary")
            ->assertOk();

        $this->assertCount(2, $response->json('days'));
    }
}

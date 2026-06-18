 PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response                                                                                                      0.14s  

   FAIL  Tests\Feature\MealPlanApiTest
  ✓ guest cannot access meal plan endpoints                                                                                                            0.08s  
  ✓ authenticated coach can access index                                                                                                               0.03s  
  ✓ coach can create meal plan with nested records                                                                                                     0.02s  
  ✓ store returns nested plan in response                                                                                                              0.01s  
  ✓ duplicate day number returns 422                                                                                                                   0.01s  
  ✓ zero quantity returns 422                                                                                                                          0.01s  
  ✓ negative quantity returns 422                                                                                                                      0.01s  
  ✓ owner can see full nested plan                                                                                                                     0.01s  
  ✓ owner can update plan metadata                                                                                                                     0.01s  
  ✓ owner can delete plan and nested records are removed                                                                                               0.01s  
  ✓ coach b accessing coach a plan returns 403 on show                                                                                                 0.01s  
  ✓ coach b cannot update coach a plan                                                                                                                 0.01s  
  ✓ coach b cannot delete coach a plan                                                                                                                 0.01s  
  ✓ coach b cannot access coach a shopping list                                                                                                        0.01s  
  ✓ coach b cannot access coach a nutrition summary                                                                                                    0.01s  
  ✓ nonexistent plan returns 404                                                                                                                       0.01s  
  ⨯ shopping list combines same name and unit                                                                                                          0.02s  
  ⨯ shopping list combines different case ingredient names                                                                                             0.02s  
  ✓ shopping list keeps same name different unit separate                                                                                              0.01s  
  ⨯ nutrition summary calculates calories correctly                                                                                                    0.01s  
  ⨯ nutrition summary treats null macros as zero                                                                                                       0.01s  
  ✓ nutrition summary groups by day                                                                                                                    0.01s  
  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\MealPlanApiTest > shopping list combines same name and unit                                                                          
  Unable to find JSON fragment: 

[{"quantity":"300.00"}]

within

[{"items":[{"ingredient_name":"oats","quantity":300,"unit":"g"}]}].
Failed asserting that false is true.

  at tests\Feature\MealPlanApiTest.php:348
    344▕ 
    345▕         $this->actingAs($coach)
    346▕             ->getJson("/api/meal-plans/{$plan->id}/shopping-list")
    347▕             ->assertOk()
  ➜ 348▕             ->assertJsonFragment(['ingredient_name' => 'oats', 'quantity' => '300.00', 'unit' => 'g']);
    349▕     }
    350▕ 
    351▕     public function test_shopping_list_combines_different_case_ingredient_names(): void
    352▕     {

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\MealPlanApiTest > shopping list combines different case ingredient names                                                             
  Unable to find JSON fragment: 

[{"quantity":"500.00"}]

within

[{"items":[{"ingredient_name":"chicken breast","quantity":500,"unit":"g"}]}].
Failed asserting that false is true.

  at tests\Feature\MealPlanApiTest.php:368
    364▕ 
    365▕         $this->actingAs($coach)
    366▕             ->getJson("/api/meal-plans/{$plan->id}/shopping-list")
    367▕             ->assertOk()
  ➜ 368▕             ->assertJsonFragment(['ingredient_name' => 'chicken breast', 'quantity' => '500.00', 'unit' => 'g']);
    369▕     }
    370▕ 
    371▕     public function test_shopping_list_keeps_same_name_different_unit_separate(): void
    372▕     {

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\MealPlanApiTest > nutrition summary calculates calories correctly                                                                    
  Unable to find JSON fragment: 

[{"total_calories":"2170.00"}]

within

[{"days":[{"day_number":1,"total_calories":2170,"total_carbs_g":220,"total_fat_g":70,"total_protein_g":165}]}].
Failed asserting that false is true.

  at tests\Feature\MealPlanApiTest.php:417
    413▕ 
    414▕         $this->actingAs($coach)
    415▕             ->getJson("/api/meal-plans/{$plan->id}/nutrition-summary")
    416▕             ->assertOk()
  ➜ 417▕             ->assertJsonFragment([
    418▕                 'day_number'      => 1,
    419▕                 'total_protein_g' => '165.00',
    420▕                 'total_carbs_g'   => '220.00',
    421▕                 'total_fat_g'     => '70.00',

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\MealPlanApiTest > nutrition summary treats null macros as zero                                                                       
  Unable to find JSON fragment: 

[{"total_calories":"0.00"}]

within

[{"days":[{"day_number":1,"total_calories":0,"total_carbs_g":0,"total_fat_g":0,"total_protein_g":0}]}].
Failed asserting that false is true.

  at tests\Feature\MealPlanApiTest.php:446
    442▕ 
    443▕         $this->actingAs($coach)
    444▕             ->getJson("/api/meal-plans/{$plan->id}/nutrition-summary")
    445▕             ->assertOk()
  ➜ 446▕             ->assertJsonFragment([
    447▕                 'total_protein_g' => '0.00',
    448▕                 'total_carbs_g'   => '0.00',
    449▕                 'total_fat_g'     => '0.00',
    450▕                 'total_calories'  => '0.00',


  Tests:    4 failed, 20 passed (80 assertions)
  Duration: 0.67s

  
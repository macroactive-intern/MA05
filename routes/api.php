<?php

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

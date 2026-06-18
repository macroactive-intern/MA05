<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMealPlanRequest;
use App\Http\Requests\UpdateMealPlanRequest;
use App\Models\MealPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MealPlanController extends Controller
{
    public function index(Request $request)
    {
        $plans = MealPlan::where('coach_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['data' => $plans]);
    }

    public function store(StoreMealPlanRequest $request)
    {
        $validated = $request->validated();

        $mealPlan = DB::transaction(function () use ($validated, $request) {
            $mealPlan = MealPlan::create([
                'coach_id'    => $request->user()->id,
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            foreach ($validated['days'] as $dayData) {
                $day = $mealPlan->days()->create([
                    'day_number' => $dayData['day_number'],
                ]);

                foreach ($dayData['meals'] as $mealData) {
                    $meal = $day->meals()->create([
                        'name'      => $mealData['name'],
                        'meal_type' => $mealData['meal_type'],
                    ]);

                    foreach ($mealData['ingredients'] as $ingredientData) {
                        $meal->ingredients()->create([
                            'ingredient_name' => $ingredientData['ingredient_name'],
                            'quantity'        => $ingredientData['quantity'],
                            'unit'            => strtolower($ingredientData['unit']),
                            'protein_g'       => $ingredientData['protein_g'] ?? null,
                            'carbs_g'         => $ingredientData['carbs_g'] ?? null,
                            'fat_g'           => $ingredientData['fat_g'] ?? null,
                        ]);
                    }
                }
            }

            return $mealPlan;
        });

        $mealPlan->load('days.meals.ingredients');

        return response()->json($mealPlan, 201);
    }

    public function show(Request $request, MealPlan $mealPlan)
    {
        $this->authoriseMealPlan($mealPlan, $request);

        $mealPlan->load('days.meals.ingredients');

        return response()->json($mealPlan);
    }

    public function update(UpdateMealPlanRequest $request, MealPlan $mealPlan)
    {
        $this->authoriseMealPlan($mealPlan, $request);

        $mealPlan->update($request->validated());

        return response()->json($mealPlan);
    }

    public function destroy(Request $request, MealPlan $mealPlan)
    {
        $this->authoriseMealPlan($mealPlan, $request);

        $mealPlan->delete();

        return response()->json(['message' => 'Meal plan deleted.']);
    }

    public function shoppingList(Request $request, MealPlan $mealPlan)
    {
        $this->authoriseMealPlan($mealPlan, $request);

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

        return response()->json(['items' => $items]);
    }

    public function nutritionSummary(Request $request, MealPlan $mealPlan)
    {
        $this->authoriseMealPlan($mealPlan, $request);

        $days = DB::table('meal_plan_days')
            ->leftJoin('meals', 'meal_plan_days.id', '=', 'meals.meal_plan_day_id')
            ->leftJoin('meal_ingredients', 'meals.id', '=', 'meal_ingredients.meal_id')
            ->where('meal_plan_days.meal_plan_id', $mealPlan->id)
            ->select('meal_plan_days.day_number')
            ->selectRaw('COALESCE(SUM(meal_ingredients.protein_g), 0) as total_protein_g')
            ->selectRaw('COALESCE(SUM(meal_ingredients.carbs_g), 0) as total_carbs_g')
            ->selectRaw('COALESCE(SUM(meal_ingredients.fat_g), 0) as total_fat_g')
            ->selectRaw('
                COALESCE(SUM(meal_ingredients.protein_g), 0) * 4
                + COALESCE(SUM(meal_ingredients.carbs_g), 0) * 4
                + COALESCE(SUM(meal_ingredients.fat_g), 0) * 9
                as total_calories
            ')
            ->groupBy('meal_plan_days.day_number')
            ->orderBy('meal_plan_days.day_number')
            ->get();

        return response()->json(['days' => $days]);
    }

    private function authoriseMealPlan(MealPlan $mealPlan, Request $request): void
    {
        if ($mealPlan->coach_id !== $request->user()->id) {
            abort(403);
        }
    }
}

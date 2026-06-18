<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\MealPlanAccessDeniedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMealPlanRequest;
use App\Http\Requests\UpdateMealPlanRequest;
use App\Http\Resources\MealPlanResource;
use App\Models\MealPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MealPlanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $plans = MealPlan::where('coach_id', $request->user()->id)
            ->latest()
            ->get();

        return MealPlanResource::collection($plans);
    }

    public function store(StoreMealPlanRequest $request): JsonResponse
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

        Log::info('meal_plan.created', [
            'meal_plan_id' => $mealPlan->id,
            'coach_id'     => $request->user()->id,
        ]);

        $mealPlan->load('days.meals.ingredients');

        return MealPlanResource::make($mealPlan)->response()->setStatusCode(201);
    }

    public function show(Request $request, MealPlan $mealPlan): JsonResponse
    {
        $this->authoriseMealPlan($mealPlan, $request);

        $mealPlan->load('days.meals.ingredients');

        return MealPlanResource::make($mealPlan)->response();
    }

    public function update(UpdateMealPlanRequest $request, MealPlan $mealPlan): JsonResponse
    {
        $updated = DB::transaction(function () use ($request, $mealPlan) {
            $plan = MealPlan::lockForUpdate()->findOrFail($mealPlan->id);
            $this->authoriseMealPlan($plan, $request);
            $plan->update($request->validated());
            return $plan;
        });

        Log::info('meal_plan.updated', [
            'meal_plan_id' => $updated->id,
            'coach_id'     => $request->user()->id,
        ]);

        return MealPlanResource::make($updated)->response();
    }

    public function destroy(Request $request, MealPlan $mealPlan): JsonResponse
    {
        DB::transaction(function () use ($request, $mealPlan) {
            $plan = MealPlan::lockForUpdate()->findOrFail($mealPlan->id);
            $this->authoriseMealPlan($plan, $request);
            $plan->delete();
        });

        Log::info('meal_plan.deleted', [
            'meal_plan_id' => $mealPlan->id,
            'coach_id'     => $request->user()->id,
        ]);

        return response()->json(null, 204);
    }

    public function shoppingList(Request $request, MealPlan $mealPlan): JsonResponse
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
            ->get()
            ->map(function ($item) {
                $item->quantity = number_format((float) $item->quantity, 2, '.', '');
                return $item;
            });

        return response()->json(['items' => $items]);
    }

    public function nutritionSummary(Request $request, MealPlan $mealPlan): JsonResponse
    {
        $this->authoriseMealPlan($mealPlan, $request);

        $protein = config('meal_plans.kcal_per_gram.protein');
        $carbs   = config('meal_plans.kcal_per_gram.carbs');
        $fat     = config('meal_plans.kcal_per_gram.fat');

        $days = DB::table('meal_plan_days')
            ->leftJoin('meals', 'meal_plan_days.id', '=', 'meals.meal_plan_day_id')
            ->leftJoin('meal_ingredients', 'meals.id', '=', 'meal_ingredients.meal_id')
            ->where('meal_plan_days.meal_plan_id', $mealPlan->id)
            ->select('meal_plan_days.day_number')
            ->selectRaw('COALESCE(SUM(meal_ingredients.protein_g), 0) as total_protein_g')
            ->selectRaw('COALESCE(SUM(meal_ingredients.carbs_g), 0) as total_carbs_g')
            ->selectRaw('COALESCE(SUM(meal_ingredients.fat_g), 0) as total_fat_g')
            ->selectRaw("
                COALESCE(SUM(meal_ingredients.protein_g), 0) * {$protein}
                + COALESCE(SUM(meal_ingredients.carbs_g), 0) * {$carbs}
                + COALESCE(SUM(meal_ingredients.fat_g), 0) * {$fat}
                as total_calories
            ")
            ->groupBy('meal_plan_days.day_number')
            ->orderBy('meal_plan_days.day_number')
            ->get()
            ->map(function ($day) {
                $day->total_protein_g = number_format((float) $day->total_protein_g, 2, '.', '');
                $day->total_carbs_g   = number_format((float) $day->total_carbs_g,   2, '.', '');
                $day->total_fat_g     = number_format((float) $day->total_fat_g,     2, '.', '');
                $day->total_calories  = number_format((float) $day->total_calories,  2, '.', '');
                return $day;
            });

        return response()->json(['days' => $days]);
    }

    private function authoriseMealPlan(MealPlan $mealPlan, Request $request): void
    {
        if ($mealPlan->coach_id !== $request->user()->id) {
            throw new MealPlanAccessDeniedException();
        }
    }
}

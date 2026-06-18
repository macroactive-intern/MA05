<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMealPlanRequest;
use App\Http\Requests\UpdateMealPlanRequest;
use App\Models\MealPlan;
use Illuminate\Http\Request;

class MealPlanController extends Controller
{
    public function index(Request $request)
    {
        //
    }

    public function store(StoreMealPlanRequest $request)
    {
        //
    }

    public function show(Request $request, MealPlan $mealPlan)
    {
        $this->authoriseMealPlan($mealPlan, $request);
    }

    public function update(UpdateMealPlanRequest $request, MealPlan $mealPlan)
    {
        $this->authoriseMealPlan($mealPlan, $request);
    }

    public function destroy(Request $request, MealPlan $mealPlan)
    {
        $this->authoriseMealPlan($mealPlan, $request);
    }

    public function shoppingList(Request $request, MealPlan $mealPlan)
    {
        $this->authoriseMealPlan($mealPlan, $request);
    }

    public function nutritionSummary(Request $request, MealPlan $mealPlan)
    {
        $this->authoriseMealPlan($mealPlan, $request);
    }

    private function authoriseMealPlan(MealPlan $mealPlan, Request $request): void
    {
        if ($mealPlan->coach_id !== $request->user()->id) {
            abort(403);
        }
    }
}

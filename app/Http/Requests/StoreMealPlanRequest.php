<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMealPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxName       = config('meal_plans.max_name_length');
        $maxMealName   = config('meal_plans.max_meal_name_length');
        $maxIngredient = config('meal_plans.max_ingredient_name_length');
        $minDay        = config('meal_plans.min_day_number');
        $maxDay        = config('meal_plans.max_day_number');
        $units         = implode(',', config('meal_plans.allowed_units'));

        return [
            'name'        => ['required', 'string', "max:{$maxName}"],
            'description' => ['nullable', 'string'],

            'days'              => ['required', 'array', 'min:1'],
            'days.*.day_number' => ['required', 'integer', "between:{$minDay},{$maxDay}", 'distinct'],
            'days.*.meals'      => ['required', 'array', 'min:1'],

            'days.*.meals.*.name'        => ['required', 'string', "max:{$maxMealName}"],
            'days.*.meals.*.meal_type'   => ['required', 'string', 'in:breakfast,lunch,dinner,snack'],
            'days.*.meals.*.ingredients' => ['required', 'array', 'min:1'],

            'days.*.meals.*.ingredients.*.ingredient_name' => ['required', 'string', "max:{$maxIngredient}"],
            'days.*.meals.*.ingredients.*.quantity'        => ['required', 'numeric', 'gt:0'],
            'days.*.meals.*.ingredients.*.unit'            => ['required', 'string', "in:{$units}"],

            'days.*.meals.*.ingredients.*.protein_g' => ['nullable', 'numeric', 'min:0'],
            'days.*.meals.*.ingredients.*.carbs_g'   => ['nullable', 'numeric', 'min:0'],
            'days.*.meals.*.ingredients.*.fat_g'     => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

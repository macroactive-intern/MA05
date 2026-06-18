<?php

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
        return [
            'name'        => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],

            'days'                => ['required', 'array', 'min:1'],
            'days.*.day_number'   => ['required', 'integer', 'between:1,7', 'distinct'],
            'days.*.meals'        => ['required', 'array', 'min:1'],

            'days.*.meals.*.name'      => ['required', 'string', 'max:80'],
            'days.*.meals.*.meal_type' => ['required', 'string', 'in:breakfast,lunch,dinner,snack'],
            'days.*.meals.*.ingredients' => ['required', 'array', 'min:1'],

            'days.*.meals.*.ingredients.*.ingredient_name' => ['required', 'string', 'max:100'],
            'days.*.meals.*.ingredients.*.quantity'        => ['required', 'numeric', 'gt:0'],
            'days.*.meals.*.ingredients.*.unit'            => ['required', 'string', 'in:g,ml,cup,tbsp,tsp,piece'],

            'days.*.meals.*.ingredients.*.protein_g' => ['nullable', 'numeric', 'min:0'],
            'days.*.meals.*.ingredients.*.carbs_g'   => ['nullable', 'numeric', 'min:0'],
            'days.*.meals.*.ingredients.*.fat_g'     => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

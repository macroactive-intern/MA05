<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMealPlanRequest extends FormRequest
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
        $maxName = config('meal_plans.max_name_length');

        return [
            'name'        => ['sometimes', 'required', 'string', "max:{$maxName}"],
            'description' => ['nullable', 'string'],
        ];
    }
}

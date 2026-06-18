<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'meal_type'   => $this->meal_type,
            'ingredients' => MealIngredientResource::collection($this->whenLoaded('ingredients')),
        ];
    }
}

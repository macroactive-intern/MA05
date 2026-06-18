<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealIngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'ingredient_name' => $this->ingredient_name,
            'quantity'        => $this->quantity,
            'unit'            => $this->unit,
            'protein_g'       => $this->protein_g,
            'carbs_g'         => $this->carbs_g,
            'fat_g'           => $this->fat_g,
        ];
    }
}

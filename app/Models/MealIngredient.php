<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealIngredient extends Model
{
    protected $fillable = [
        'meal_id',
        'ingredient_name',
        'quantity',
        'unit',
        'protein_g',
        'carbs_g',
        'fat_g',
    ];

    protected $casts = [
        'quantity'  => 'decimal:2',
        'protein_g' => 'decimal:2',
        'carbs_g'   => 'decimal:2',
        'fat_g'     => 'decimal:2',
    ];

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }
}

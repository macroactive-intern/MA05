<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meal extends Model
{
    protected $fillable = [
        'meal_plan_day_id',
        'name',
        'meal_type',
    ];

    public function day(): BelongsTo
    {
        return $this->belongsTo(MealPlanDay::class, 'meal_plan_day_id');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(MealIngredient::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealPlanDay extends Model
{
    protected $fillable = [
        'meal_plan_id',
        'day_number',
    ];

    public function mealPlan()
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function meals()
    {
        return $this->hasMany(Meal::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meal extends Model
{
    protected $fillable = [
        'meal_plan_day_id',
        'name',
        'meal_type',
    ];

    public function day()
    {
        return $this->belongsTo(MealPlanDay::class, 'meal_plan_day_id');
    }

    public function ingredients()
    {
        return $this->hasMany(MealIngredient::class);
    }
}

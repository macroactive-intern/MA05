<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealPlan extends Model
{
    protected $fillable = [
        'coach_id',
        'name',
        'description',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function days()
    {
        return $this->hasMany(MealPlanDay::class);
    }
}

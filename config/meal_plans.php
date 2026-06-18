<?php

return [
    'max_name_length'       => 120,
    'max_meal_name_length'  => 80,
    'max_ingredient_name_length' => 100,
    'min_day_number'        => 1,
    'max_day_number'        => 7,
    'allowed_units'         => ['g', 'ml', 'cup', 'tbsp', 'tsp', 'piece'],
    'kcal_per_gram' => [
        'protein' => 4,
        'carbs'   => 4,
        'fat'     => 9,
    ],
];

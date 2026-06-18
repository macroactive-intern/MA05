<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class MealPlanAccessDeniedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You do not have permission to access this meal plan.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 403);
    }
}

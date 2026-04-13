<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Level;
use Illuminate\Http\JsonResponse;

class LevelController extends Controller
{
    /**
     * Get all levels
     */
    public function index(): JsonResponse
    {
        $levels = Level::orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data' => $levels,
        ]);
    }
}

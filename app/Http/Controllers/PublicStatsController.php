<?php

namespace App\Http\Controllers;

use App\Models\Wedding;
use Illuminate\Http\JsonResponse;

class PublicStatsController extends Controller
{
    /**
     * Aggregate, non-sensitive platform stats for the public marketing site
     * (sls-web). No auth — exposes only headline counts, never per-wedding data.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                // Each wedding belongs to one couple, so the wedding count is
                // the number of couples using the platform.
                'couples' => Wedding::query()->count(),
            ],
        ]);
    }
}

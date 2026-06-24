<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function overview(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->dashboardService->overview($request->user())]);
    }

    public function platformAnalytics(): JsonResponse
    {
        return response()->json(['data' => $this->dashboardService->platformAnalytics()]);
    }
}

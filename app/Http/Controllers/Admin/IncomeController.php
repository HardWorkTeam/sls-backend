<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlatformIncomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function __construct(private readonly PlatformIncomeService $income) {}

    /**
     * Per-event platform income rows (each wedding with a package = a purchase).
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->income->rows(
                $request->query('status'),
                (int) $request->query('per_page', '15'),
            ),
        );
    }

    /**
     * Summary cards: total platform income and a per-package breakdown.
     */
    public function summary(): JsonResponse
    {
        return response()->json(['data' => $this->income->summary()]);
    }
}

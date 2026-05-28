<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn (): JsonResponse => response()->json([
    'ok' => true,
    'service' => config('app.name'),
    'timestamp' => now()->toIso8601String(),
]));

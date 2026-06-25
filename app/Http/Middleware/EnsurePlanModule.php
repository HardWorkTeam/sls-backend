<?php

namespace App\Http\Middleware;

use App\Models\Wedding;
use App\Support\PlanCapabilities;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanModule
{
    /**
     * Block access to a plan-gated module ('seating' | 'gallery' | 'gifts')
     * unless the bound wedding's PAID package includes it. Runs after
     * `wedding.access`, so the {wedding} binding is already authorized.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $wedding = $request->route('wedding');

        if ($wedding instanceof Wedding && ! PlanCapabilities::forWedding($wedding)->allows($module)) {
            abort(403, 'Your current plan does not include this feature. Upgrade your plan to unlock it.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Wedding;
use App\Repositories\WeddingRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWeddingAccess
{
    public function __construct(private readonly WeddingRepository $weddings) {}

    /**
     * Guard nested wedding routes: the bound {wedding} must be visible to
     * the authenticated user (creator, member, or super admin).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $wedding = $request->route('wedding');
        $user = $request->user();

        if (! $wedding instanceof Wedding || ! $user || ! $this->weddings->canAccess($user, $wedding)) {
            abort(404, 'Wedding not found.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $requiredPermission): Response
    {
        $user = auth()->user();
        $permissions = $user?->permissions()?? [];

        if (!in_array($requiredPermission, $permissions)) {
            abort(403, 'Vous n\'avez pas les permissions nécéssaires.');
        }
        return $next($request);
    }
}
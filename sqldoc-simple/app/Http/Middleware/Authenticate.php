<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // ✅ Si l'agent est connecté, rediriger vers user.login
        if (agentConnected()) {
            return route('user.login');
        }

        // ✅ Sinon, rediriger vers agent.login
        return route('agent.login');
    }
}
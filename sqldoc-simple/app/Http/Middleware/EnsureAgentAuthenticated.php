<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Enums\AppMode;

class EnsureAgentAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        // Vérifier si on est en mode agent
        if (appMode() !== AppMode::AGENT) {
            // Si pas en mode agent, laisser passer (mode web classique)
            return $next($request);
        }

        // En mode agent, vérifier la connexion
        if (!agentConnected() && 
            !$request->routeIs('agent.login') && 
            !$request->routeIs('agent.login.submit') && 
            !$request->routeIs('agent.reconnect')) {
            return redirect()->route('agent.login');
        }

        return $next($request);
    }
}


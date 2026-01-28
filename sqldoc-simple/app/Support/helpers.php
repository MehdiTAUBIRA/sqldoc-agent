<?php

use App\Enums\AppMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

if (!function_exists('agentConnected')) {
    /**
     * Vérifier si l'agent est connecté
     */
    function agentConnected(): bool
    {
        try {
            return app(\App\Services\AgentAuthService::class)->isConnected();
        } catch (\Exception $e) {
            Log::debug('agentConnected() error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('agentIdentity')) {
    /**
     * Obtenir l'identité de l'agent actuel
     */
    function agentIdentity(): ?\App\Models\AgentIdentity
    {
        try {
            return app(\App\Services\AgentAuthService::class)->getIdentity();
        } catch (\Exception $e) {
            Log::debug('agentIdentity() error: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('appMode')) {
    /**
     * Obtenir le mode de l'application (web ou agent)
     */
    function appMode(): string
    {
        return env('APP_MODE', 'web');
    }
}

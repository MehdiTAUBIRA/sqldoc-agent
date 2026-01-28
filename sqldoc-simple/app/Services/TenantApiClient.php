<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\PendingRequest;

class TenantApiClient
{
    /**
     * Créer un client HTTP configuré pour les appels vers les tenants
     */
    public static function make(): PendingRequest
    {
        $options = [
            'timeout' => 30,
        ];
        
        // En mode agent (Electron), désactiver la vérification SSL
        if (env('APP_MODE') === 'agent') {
            $options['verify'] = false;
            Log::debug('TenantApiClient: SSL verification disabled (agent mode)');
        }
        
        return Http::withOptions($options)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
    }
    
    /**
     * POST vers un tenant
     */
    public static function post(string $url, array $data = [])
    {
        Log::info('TenantApiClient POST', [
            'url' => $url,
            'ssl_verify' => env('APP_MODE') !== 'agent',
        ]);
        
        try {
            $response = self::make()->post($url, $data);
            
            if (!$response->successful()) {
                Log::error('TenantApiClient POST failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error('TenantApiClient POST exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * GET vers un tenant
     */
    public static function get(string $url, array $query = [])
    {
        Log::info('TenantApiClient GET', [
            'url' => $url,
            'ssl_verify' => env('APP_MODE') !== 'agent',
        ]);
        
        try {
            $response = self::make()->get($url, $query);
            
            if (!$response->successful()) {
                Log::error('TenantApiClient GET failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error('TenantApiClient GET exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
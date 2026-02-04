<?php

namespace App\Services;

use App\Models\AgentIdentity;
use Illuminate\Support\Facades\Log;

class ApiService
{
    private ?AgentIdentity $identity;

    public function __construct()
    {
        $this->identity = AgentIdentity::first();
    }

    /**
     * Effectuer une requête GET vers l'API web
     */
    public function get(string $endpoint, array $params = [])
    {
        if (!$this->identity || !$this->identity->isConnected()) {
            throw new \Exception('Agent not connected');
        }

        // Décrypter le token
        try {
            $token = decrypt($this->identity->token_encrypted);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt token', ['error' => $e->getMessage()]);
            throw new \Exception('Failed to decrypt agent token');
        }

        $url = $this->identity->api_url . $endpoint;

        Log::info('📥 API GET request', [
            'endpoint' => $endpoint,
            'url' => $url,
            'params' => $params,
        ]);

        try {
            // ✅ UTILISER TenantApiClient
            $response = TenantApiClient::make()
                ->timeout(600)
                ->connectTimeout(60)
                ->withHeaders([
                    'X-Agent-Token' => $token,
                    'X-Tenant-ID' => $this->identity->tenant_id,
                    'X-Agent-ID' => $this->identity->agent_id,
                    'Accept' => 'application/json',
                ])
                ->get($url, $params);

            if ($response->failed()) {
                Log::error('API GET request failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception("API request failed: {$response->status()}");
            }

            Log::info('✅ API GET request successful', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);

            return $response->json();

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('API GET connection exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("API connection failed: {$e->getMessage()}");

        } catch (\Exception $e) {
            Log::error('API GET request exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Effectuer une requête POST vers l'API web
     */
    public function post(string $endpoint, array $data = [])
    {
        if (!$this->isConnected()) {
            throw new \Exception('Agent not connected');
        }

        // Construire l'URL complète
        $url = $this->identity->api_url . $endpoint;

        // Décrypter le token
        try {
            $token = decrypt($this->identity->token_encrypted);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt token', ['error' => $e->getMessage()]);
            throw new \Exception('Failed to decrypt agent token');
        }

        Log::info('📤 API POST request', [
            'endpoint' => $endpoint,
            'url' => $url,
            'data_preview' => array_keys($data),
        ]);

        try {
            // ✅ UTILISER TenantApiClient
            $response = TenantApiClient::make()
                ->timeout(600)
                ->connectTimeout(60)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-Agent-Token' => $token,
                    'X-Tenant-ID' => $this->identity->tenant_id,
                    'X-Agent-ID' => $this->identity->agent_id,
                ])
                ->post($url, $data);

            if ($response->failed()) {
                $errorBody = $response->body();
                Log::error('API POST request failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $errorBody,
                ]);
                throw new \Exception("API request failed: {$response->status()} - {$errorBody}");
            }

            Log::info('✅ API POST request successful', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);

            return $response->json();

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('API POST connection exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("API connection failed: {$e->getMessage()}");

        } catch (\Exception $e) {
            Log::error('API POST request exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Effectuer une requête PUT vers l'API web
     */
    public function put(string $endpoint, array $data = [])
    {
        if (!$this->identity || !$this->identity->isConnected()) {
            throw new \Exception('Agent not connected');
        }

        // Décrypter le token
        try {
            $token = decrypt($this->identity->token_encrypted);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt token', ['error' => $e->getMessage()]);
            throw new \Exception('Failed to decrypt agent token');
        }

        $url = $this->identity->api_url . $endpoint;

        Log::info('🔄 API PUT request', [
            'endpoint' => $endpoint,
            'url' => $url,
            'data_preview' => array_keys($data),
        ]);

        try {
            // ✅ UTILISER TenantApiClient
            $response = TenantApiClient::make()
                ->timeout(600)
                ->connectTimeout(60)
                ->withHeaders([
                    'X-Agent-Token' => $token,
                    'X-Tenant-ID' => $this->identity->tenant_id,
                    'X-Agent-ID' => $this->identity->agent_id,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->put($url, $data);

            if ($response->failed()) {
                Log::error('API PUT request failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception("API request failed: {$response->status()} - {$response->body()}");
            }

            Log::info('✅ API PUT request successful', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);

            return $response->json();

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('API PUT connection exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("API connection failed: {$e->getMessage()}");

        } catch (\Exception $e) {
            Log::error('API PUT request exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Effectuer une requête DELETE vers l'API web
     */
    public function delete(string $endpoint)
    {
        if (!$this->identity || !$this->identity->isConnected()) {
            throw new \Exception('Agent not connected');
        }

        // Décrypter le token
        try {
            $token = decrypt($this->identity->token_encrypted);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt token', ['error' => $e->getMessage()]);
            throw new \Exception('Failed to decrypt agent token');
        }

        $url = $this->identity->api_url . $endpoint;

        Log::info('🗑️ API DELETE request', [
            'endpoint' => $endpoint,
            'url' => $url,
        ]);

        try {
            // ✅ UTILISER TenantApiClient
            $response = TenantApiClient::make()
                ->timeout(600)
                ->connectTimeout(60)
                ->withHeaders([
                    'X-Agent-Token' => $token,
                    'X-Tenant-ID' => $this->identity->tenant_id,
                    'X-Agent-ID' => $this->identity->agent_id,
                    'Accept' => 'application/json',
                ])
                ->delete($url);

            if ($response->failed()) {
                Log::error('API DELETE request failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception("API request failed: {$response->status()} - {$response->body()}");
            }

            Log::info('✅ API DELETE request successful', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);

            return $response->json();

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('API DELETE connection exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("API connection failed: {$e->getMessage()}");

        } catch (\Exception $e) {
            Log::error('API DELETE request exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Vérifier si l'agent est connecté
     */
    public function isConnected(): bool
    {
        return $this->identity && $this->identity->isConnected();
    }

    /**
     * Obtenir l'URL de l'API
     */
    public function getApiUrl(): ?string
    {
        return $this->identity?->api_url;
    }

    /**
     * Obtenir le tenant ID
     */
    public function getTenantId(): ?string
    {
        return $this->identity?->tenant_id;
    }

    /**
     * Obtenir l'agent ID
     */
    public function getAgentId(): ?string
    {
        return $this->identity?->agent_id;
    }
}
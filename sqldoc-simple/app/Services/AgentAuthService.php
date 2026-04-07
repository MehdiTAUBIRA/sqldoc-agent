<?php
// app/Services/AgentAuthService.php

namespace App\Services;

use App\Models\AgentIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AgentAuthService
{
    /**
     * ✅ Obtenir l'historique des connexions
     */
    public function getHistory()
    {
        return AgentIdentity::getHistory();
    }

    /**
     * ✅ Authentifier un agent (nouveau ou existant)
     */
    public function authenticate(string $token, string $apiUrl): array
    {
        // Nettoyer l'URL
        $apiUrl = $this->cleanApiUrl($apiUrl);
        
        // Construire l'URL complète
        $fullUrl = "{$apiUrl}/api/agent/login";

        Log::info('🔍 Agent authentication attempt', [
            'api_url' => $apiUrl,
            'full_url' => $fullUrl,
            'token_preview' => substr($token, 0, 10) . '...',
            'app_mode' => env('APP_MODE'),
        ]);

        try {
            $response = TenantApiClient::make()
                ->timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($fullUrl, [
                    'token' => $token,
                ]);

            Log::info('🔍 API Response received', [
                'status' => $response->status(),
                'success' => $response->successful(),
            ]);

            if ($response->failed()) {
                Log::error('❌ Agent authentication failed', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);

                $message = $response->json('message') ?? 'Connection failed. Please check your API URL and token.';
                
                return [
                    'success' => false,
                    'message' => $message,
                ];
            }

            $data = $response->json();

            // ✅ Vérifier si cet agent existe déjà dans l'historique
            $existingAgent = AgentIdentity::where('api_url', $apiUrl)
                ->where('agent_id', $data['agent_id'])
                ->first();

            if ($existingAgent) {
                // ✅ Agent existe déjà, le réactiver et mettre à jour
                Log::info('♻️ Reconnecting to existing agent', [
                    'agent_id' => $data['agent_id'],
                    'organization' => $existingAgent->organization_name,
                ]);

                $existingAgent->update([
                    'token_encrypted' => encrypt($token),
                    'tenant_id' => $data['tenant_id'],
                    'tenant_name' => $data['tenant_name'] ?? null,
                    'connected_at' => now(),
                ]);

                $existingAgent->setActive();

                Log::info('✅ Agent reconnected successfully', [
                    'agent_id' => $data['agent_id'],
                    'tenant_id' => $data['tenant_id'],
                ]);

                return [
                    'success' => true,
                    'message' => 'Reconnected successfully!',
                    'data' => $data,
                ];
            }

            // ✅ Nouvel agent, le créer
            Log::info('🆕 Creating new agent identity', [
                'agent_id' => $data['agent_id'],
                'tenant_name' => $data['tenant_name'] ?? 'N/A',
            ]);

            // Désactiver l'agent actuel s'il y en a un
            AgentIdentity::where('is_active', true)->update(['is_active' => false]);

            $identity = AgentIdentity::create([
                'api_url' => $apiUrl,
                'token_encrypted' => encrypt($token),
                'agent_id' => $data['agent_id'],
                'tenant_id' => $data['tenant_id'],
                'tenant_name' => $data['tenant_name'] ?? null,
                'organization_name' => $data['tenant_name'] ?? null,
                'is_active' => true,
                'connected_at' => now(),
                'last_connected_at' => now(),
            ]);

            Log::info('✅ Agent authenticated successfully', [
                'agent_id' => $data['agent_id'],
                'tenant_id' => $data['tenant_id'],
            ]);

            return [
                'success' => true,
                'message' => 'Connected successfully!',
                'data' => $data,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('❌ Connection exception', [
                'error' => $e->getMessage(),
                'url' => $fullUrl,
            ]);

            return [
                'success' => false,
                'message' => 'Unable to connect to the API. Check your internet connection and the API URL.',
            ];

        } catch (\Exception $e) {
            Log::error('❌ Unexpected authentication error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ Reconnexion rapide avec un agent de l'historique
     */
    public function reconnect(int $agentIdentityId): array
    {
        try {
            $agent = AgentIdentity::find($agentIdentityId);
            
            if (!$agent) {
                return [
                    'success' => false,
                    'message' => 'Agent not found'
                ];
            }

            // Vérifier que le token est toujours valide
            $token = decrypt($agent->token_encrypted);
            
            $response = TenantApiClient::make()
                ->timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$agent->api_url}/api/agent/login", [
                    'token' => $token,
                ]);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'message' => 'Token expired or invalid. Please login again with a new token.'
                ];
            }

            $data = $response->json();

            Log::info('♻️ Reconnecting to agent', [
                'agent_id' => $agent->agent_id,
                'organization' => $agent->organization_name,
            ]);

            // Mettre à jour avec les nouvelles données
            $agent->update([
                'tenant_id' => $data['tenant_id'],
                'tenant_name' => $data['tenant_name'] ?? $agent->tenant_name,
                'connected_at' => now(),
            ]);

            $agent->setActive();

            // Synchroniser les utilisateurs
            $this->syncUsers();

            return [
                'success' => true,
                'message' => 'Agent reconnected',
            ];

        } catch (\Exception $e) {
            Log::error('❌ Error reconnecting agent', [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ Supprimer un agent de l'historique
     */
    public function deleteFromHistory(int $agentIdentityId): bool
    {
        try {
            $agent = AgentIdentity::where('id', $agentIdentityId)
                ->where('is_active', false) // Seulement les inactifs
                ->first();
            
            if (!$agent) {
                return false;
            }

            Log::info('🗑️ Deleting agent from history', [
                'agent_id' => $agent->agent_id,
                'organization' => $agent->organization_name,
            ]);

            // Supprimer les utilisateurs de ce tenant
            User::where('tenant_id', $agent->tenant_id)->delete();

            // Supprimer l'agent
            $agent->delete();

            return true;

        } catch (\Exception $e) {
            Log::error('❌ Error deleting agent', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * ✅ Mettre à jour le nom de l'organisation
     */
    public function updateOrganizationName(int $agentIdentityId, string $name): bool
    {
        try {
            $agent = AgentIdentity::find($agentIdentityId);
            
            if (!$agent) {
                return false;
            }

            $agent->update(['organization_name' => $name]);
            
            return true;

        } catch (\Exception $e) {
            Log::error('❌ Error updating organization name', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Nettoyer l'URL de l'API
     */
    private function cleanApiUrl(string $url): string
    {
        $url = trim($url);
        $url = rtrim($url, '/');
        
        $parsed = parse_url($url);
        
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return $url;
        }
        
        $cleanUrl = $parsed['scheme'] . '://' . $parsed['host'];
        
        if (isset($parsed['port'])) {
            $cleanUrl .= ':' . $parsed['port'];
        }
        
        Log::info('🧹 URL cleaned', [
            'original' => $url,
            'cleaned' => $cleanUrl,
        ]);
        
        return $cleanUrl;
    }

    public function syncUsers(): bool
    {
        try {
            $identity = $this->getIdentity();
            
            if (!$identity) {
                Log::error('❌ Cannot sync users: agent not connected');
                return false;
            }

            Log::info('📥 Fetching users from API', [
                'api_url' => $identity->api_url,
                'tenant_id' => $identity->tenant_id,
            ]);

            $token = decrypt($identity->token_encrypted);

            $response = TenantApiClient::make()
                ->timeout(30)
                ->withHeaders([
                    'X-Agent-Token' => $token,
                    'Accept' => 'application/json',
                ])
                ->get("{$identity->api_url}/api/agent/users");

            if ($response->failed()) {
                Log::error('❌ Failed to sync users - API error', [
                    'status' => $response->status(),
                ]);
                return false;
            }

            $data = $response->json();
            $users = $data['users'] ?? [];

            Log::info('📥 Users received from API', ['count' => count($users)]);

            if (empty($users)) {
                Log::warning('⚠️ No users returned from API');
                return false;
            }

            // ✅ Supprimer uniquement les users du tenant actif
            DB::statement('PRAGMA foreign_keys = OFF');
            User::where('tenant_id', $identity->tenant_id)->delete();
            DB::statement('PRAGMA foreign_keys = ON');

            Log::info('✅ Existing users deleted');

            // Créer les nouveaux utilisateurs
            $createdCount = 0;
            foreach ($users as $user) {
                try {
                    User::updateOrCreate(
                        ['email' => $user['email']],
                        [
                            'id' => $user['id'],
                            'tenant_id' => $identity->tenant_id,
                            'name' => $user['name'],
                            'role_id' => $user['role_id'] ?? null,
                            'password' => '',
                        ]
                    );
                    
                    $createdCount++;
                    
                } catch (\Exception $e) {
                    Log::error('❌ Failed to create user', [
                        'email' => $user['email'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('✅ Users synced successfully', [
                'created' => $createdCount,
                'total' => count($users),
            ]);
            
            return $createdCount > 0;

        } catch (\Exception $e) {
            Log::error('❌ Unexpected error syncing users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    public function isConnected(): bool
    {
        $identity = $this->getIdentity();
        
        return $identity 
            && $identity->token_encrypted
            && $identity->api_url 
            && $identity->tenant_id;
    }

    public function getIdentity(): ?AgentIdentity
    {
        return AgentIdentity::getActive();
    }

    /**
     * ✅ Déconnecter l'agent actif (le désactiver, garde l'historique)
     */
    public function disconnect(): void
    {
        try {
            Log::info('🔌 Disconnecting active agent');
            
            // Désactiver l'agent actif (reste dans l'historique)
            AgentIdentity::where('is_active', true)->update(['is_active' => false]);
            
            Log::info('✅ Agent disconnected successfully');
            
        } catch (\Exception $e) {
            Log::error('❌ Error during disconnect', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
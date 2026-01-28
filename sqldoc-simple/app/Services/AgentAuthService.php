<?php

namespace App\Services;

use App\Models\AgentIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AgentAuthService
{
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
            // ✅ UTILISER TenantApiClient au lieu de Http::
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

            // Sauvegarder l'identité de l'agent
            AgentIdentity::updateOrCreate(
                ['id' => 1],
                [
                    'api_url' => $apiUrl,
                    'token_encrypted' => encrypt($token),
                    'agent_id' => $data['agent_id'],
                    'tenant_id' => $data['tenant_id'],
                    'tenant_name' => $data['tenant_name'] ?? null,
                    'connected_at' => now(),
                ]
            );

            Log::info('✅ Agent authenticated successfully', [
                'agent_id' => $data['agent_id'],
                'tenant_id' => $data['tenant_id'],
                'tenant_name' => $data['tenant_name'] ?? 'N/A',
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
                'app_mode' => env('APP_MODE'),
            ]);

            try {
                $token = decrypt($identity->token_encrypted);
                Log::info('✅ Token decrypted successfully');
            } catch (\Exception $e) {
                Log::error('❌ Failed to decrypt token', [
                    'error' => $e->getMessage(),
                ]);
                return false;
            }

            try {
                // ✅ UTILISER TenantApiClient au lieu de Http::
                $response = TenantApiClient::make()
                    ->timeout(30)
                    ->withHeaders([
                        'X-Agent-Token' => $token,
                        'Accept' => 'application/json',
                    ])
                    ->get("{$identity->api_url}/api/agent/users");

                Log::info('📥 API response received', [
                    'status' => $response->status(),
                    'successful' => $response->successful(),
                ]);

                if ($response->failed()) {
                    Log::error('❌ Failed to sync users - API error', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return false;
                }
            } catch (\Exception $e) {
                Log::error('❌ Failed to call API', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return false;
            }

            try {
                $data = $response->json();
                $users = $data['users'] ?? [];

                Log::info('📥 Users received from API', [
                    'count' => count($users),
                ]);

                if (empty($users)) {
                    Log::warning('⚠️ No users returned from API');
                    return false;
                }
            } catch (\Exception $e) {
                Log::error('❌ Failed to parse API response', [
                    'error' => $e->getMessage(),
                ]);
                return false;
            }

            // ✅ Supprimer les users avec FK désactivées
            try {
                DB::statement('PRAGMA foreign_keys = OFF');
                $deletedCount = User::count();
                User::query()->delete();
                DB::statement('PRAGMA foreign_keys = ON');
                Log::info('✅ Existing users deleted', ['count' => $deletedCount]);
            } catch (\Exception $e) {
                Log::error('❌ Failed to delete existing users', [
                    'error' => $e->getMessage(),
                ]);
                // Réactiver FK même en cas d'erreur
                DB::statement('PRAGMA foreign_keys = ON');
            }

            // ✅ Créer les nouveaux utilisateurs
            $createdCount = 0;
            foreach ($users as $user) {
                try {
                    Log::info('💾 Creating user', [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'name' => $user['name'],
                    ]);
                    
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
                    Log::info('✅ User created/updated successfully', ['email' => $user['email']]);
                    
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
        return AgentIdentity::first();
    }

    public function disconnect(): void
    {
        try 
        {
            User::query()->delete();
            AgentIdentity::query()->delete();
            
            Log::info('✅ Agent disconnected successfully');
            
        } catch (\Exception $e) {
            Log::error('❌ Error during disconnect', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
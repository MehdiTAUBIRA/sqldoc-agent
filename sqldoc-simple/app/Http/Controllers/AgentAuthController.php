<?php
// app/Http/Controllers/AgentAuthController.php

namespace App\Http\Controllers;

use App\Services\AgentAuthService;
use App\Services\TenantApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AgentAuthController extends Controller
{
    public function __construct(
        private AgentAuthService $agentAuth
    ) {}

    /**
     * ✅ Afficher le formulaire de connexion agent avec historique
     */
    public function showAgentLoginForm()
    {
        if ($this->agentAuth->isConnected()) {
            return redirect()->route('user.login');
        }

        $history = $this->agentAuth->getHistory();

        return Inertia::render('Agent/AgentLogin', [
            'agentHistory' => $history->map(fn($agent) => [
                'id' => $agent->id,
                'organization_name' => $agent->organization_name,
                'tenant_name' => $agent->tenant_name,
                'api_url' => $agent->api_url,
                'last_connected_at' => $agent->last_connected_at?->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Connecter l'agent (nouveau ou existant)
     */
    public function agentLogin(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|min:64',
            'api_url' => 'required|url',
        ]);

        Log::info('🔐 Agent login attempt', [
            'api_url' => $validated['api_url'],
        ]);

        $result = $this->agentAuth->authenticate(
            $validated['token'],
            $validated['api_url']
        );

        if (!$result['success']) {
            return back()->withErrors([
                'token' => $result['message']
            ])->withInput();
        }

        // Synchroniser les utilisateurs
        $this->agentAuth->syncUsers();

        return redirect()
            ->route('user.login')
            ->with('success', 'Agent connected successfully!');
    }

    /**
     * ✅ Reconnexion rapide à un agent de l'historique
     */
    public function reconnect(Request $request)
    {
        $validated = $request->validate([
            'agent_id' => 'required|integer|exists:agent_identity,id',
        ]);

        $result = $this->agentAuth->reconnect($validated['agent_id']);

        if (!$result['success']) {
            return back()->withErrors([
                'message' => $result['message']
            ]);
        }

        return redirect()
            ->route('user.login')
            ->with('success', 'Agent reconnected successfully!');
    }

    /**
     * ✅ Supprimer un agent de l'historique
     */
    public function deleteAgent(int $agent_id)
    {
        $success = $this->agentAuth->deleteFromHistory($agent_id);

        if (!$success) {
            return back()->withErrors([
                'message' => 'Failed to delete agent or agent is active'
            ]);
        }

        return back()->with('success', 'Agent deleted from history!');
    }

    /**
     * ✅ Renommer une organisation
     */
    public function updateOrganizationName(Request $request, int $agent_id)
    {
        $validated = $request->validate([
            'organization_name' => 'required|string|max:255',
        ]);

        $success = $this->agentAuth->updateOrganizationName(
            $agent_id,
            $validated['organization_name']
        );

        if (!$success) {
            return back()->withErrors([
                'organization_name' => 'Failed to update name'
            ]);
        }

        return back()->with('success', 'Organization name updated!');
    }

    /**
     * Afficher le formulaire de login utilisateur
     */
    public function showUserLoginForm()
    {
        if (!$this->agentAuth->isConnected()) {
            return redirect()->route('agent.login');
        }

        if (Auth::check()) {
            return redirect()->route('projects.index');
        }

        $identity = $this->agentAuth->getIdentity();

        return Inertia::render('Agent/UserLogin', [
            'tenantName' => $identity->tenant_name ?? 'Unknown',
            'organizationName' => $identity->organization_name ?? $identity->tenant_name,
        ]);
    }

    /**
     * ✅ Login utilisateur (authentification via API web)
     */
    public function userLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        Log::info('👤 User login attempt', [
            'email' => $credentials['email'],
        ]);

        $identity = $this->agentAuth->getIdentity();

        if (!$identity) {
            Log::error('❌ Agent not connected');
            return back()->withErrors([
                'email' => 'Agent not connected.',
            ]);
        }

        // ✅ Authentifier via l'API web avec TenantApiClient
        try {
            $token = decrypt($identity->token_encrypted);
            $url = "{$identity->api_url}/api/agent/auth";
            
            Log::info('🌐 Calling tenant API for authentication', [
                'url' => $url,
                'email' => $credentials['email'],
            ]);
            
            $response = TenantApiClient::make()
                ->timeout(30)
                ->withHeaders([
                    'X-Agent-Token' => $token,
                    'Accept' => 'application/json',
                ])
                ->post($url, [
                    'email' => $credentials['email'],
                    'password' => $credentials['password'],
                ]);

            if ($response->failed()) {
                Log::warning('❌ Authentication failed', [
                    'email' => $credentials['email'],
                    'status' => $response->status(),
                ]);
                
                return back()->withErrors([
                    'email' => 'Invalid credentials.',
                ])->withInput();
            }

            $data = $response->json();
            $userData = $data['user'] ?? null;

            if (!$userData) {
                Log::error('❌ No user data in response');
                return back()->withErrors([
                    'email' => 'Authentication error.',
                ])->withInput();
            }

            // ✅ Vérifier que l'utilisateur existe localement
            $user = \App\Models\User::where('email', $credentials['email'])
                ->where('tenant_id', $identity->tenant_id)
                ->first();

            if (!$user) {
                Log::warning('❌ User not found locally', [
                    'email' => $credentials['email'],
                    'tenant_id' => $identity->tenant_id,
                ]);
                
                return back()->withErrors([
                    'email' => 'User not found. Please sync users.',
                ])->withInput();
            }

            Log::info('✅ User authenticated via API', [
                'user_id' => $user->id,
                'name' => $user->name,
            ]);

            // ✅ Authentifier l'utilisateur localement (session Laravel)
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            Log::info('✅ User session created');

            return redirect()->intended(route('projects.index'));

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('❌ Connection error during authentication', [
                'error' => $e->getMessage(),
            ]);
            
            return back()->withErrors([
                'email' => 'Cannot connect to server. Please check your connection.',
            ])->withInput();
            
        } catch (\Exception $e) {
            Log::error('❌ Exception during authentication', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return back()->withErrors([
                'email' => 'Authentication error: ' . $e->getMessage(),
            ])->withInput();
        }
    }

    /**
     * Déconnecter l'utilisateur (garde l'agent connecté)
     */
    public function userLogout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('user.login');
    }

    /**
     * ✅ Déconnecter l'agent (le met dans l'historique)
     */
    public function agentLogout(Request $request)
    {
        Log::info('🔄 Agent logout request');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        // Désactiver l'agent (reste dans l'historique)
        $this->agentAuth->disconnect();

        Log::info('✅ Agent disconnected');

        return redirect()->route('agent.login');
    }
}


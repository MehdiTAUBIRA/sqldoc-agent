<?php

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
     * Afficher le formulaire de connexion agent
     */
    public function showAgentLoginForm()
    {
        if ($this->agentAuth->isConnected()) {
            return redirect()->route('user.login');
        }

        $identity = $this->agentAuth->getIdentity();
        $hasCredentials = $identity && $identity->token_encrypted && $identity->api_url; 

        return Inertia::render('Agent/AgentLogin', [
            'hasCredentials' => $hasCredentials,
            'savedApiUrl' => $identity?->api_url,
        ]);
    }

    /**
     * Connecter l'agent
     */
    public function agentLogin(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|min:64',
            'api_url' => 'required|url',
        ]);

        Log::info('🔐 Agent login attempt', [
            'api_url' => $validated['api_url'],
            'app_mode' => env('APP_MODE'),
        ]);

        $result = $this->agentAuth->authenticate(
            $validated['token'],
            $validated['api_url']
        );

        if (!$result['success']) {
            Log::error('❌ Agent authentication failed', [
                'message' => $result['message'],
            ]);
            
            return back()->withErrors([
                'token' => $result['message']
            ])->withInput();
        }

        Log::info('✅ Agent authenticated, syncing users...');

        // Synchroniser les utilisateurs
        $syncSuccess = $this->agentAuth->syncUsers();
        
        if (!$syncSuccess) {
            Log::warning('⚠️ User sync failed, but continuing...');
        }

        return redirect()
            ->route('user.login')
            ->with('success', 'Agent connected successfully!');
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
        ]);
    }

    /**
     * Login utilisateur (authentification via API web)
     */
    public function userLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        Log::info('👤 User login attempt', [
            'email' => $credentials['email'],
            'app_mode' => env('APP_MODE'),
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
            
            // ✅ UTILISER TenantApiClient au lieu de Http::
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
                    'body' => $response->body(),
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
                'url' => $identity->api_url ?? 'unknown',
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
     * Déconnecter l'agent ET l'utilisateur
     */
    public function agentLogout(Request $request)
    {
        Log::info('🔄 Agent logout request');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        $this->agentAuth->disconnect();

        Log::info('✅ Agent disconnected, redirecting to agent login');

        return redirect()->route('agent.login');
    }
}
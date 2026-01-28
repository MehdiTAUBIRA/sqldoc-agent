<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ApiService;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    protected $apiService;

    public function __construct()
    {
        $this->apiService = app(ApiService::class);
    }

    /**
     * Synchroniser après la création d'un utilisateur
     */
    public function created(User $user)
    {
        if (!$this->apiService->isConnected()) {
            Log::warning('⚠️ Cannot sync user: agent not connected', ['user_id' => $user->id]);
            return;
        }

        try {
            Log::info('📤 Syncing new user to web', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            $this->apiService->post('/api/admin/sync-user', [
                'action' => 'create',
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $user->password, // Déjà hashé
                    'role_id' => $user->role_id,
                ],
            ]);

            Log::info('✅ User synced to web', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::error('❌ Failed to sync user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Synchroniser après la mise à jour d'un utilisateur
     */
    public function updated(User $user)
    {
        if (!$this->apiService->isConnected()) {
            return;
        }

        try {
            Log::info('📤 Syncing updated user to web', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            $this->apiService->post('/api/admin/sync-user', [
                'action' => 'update',
                'user' => [
                    'email' => $user->email, // Identifier
                    'name' => $user->name,
                    'role_id' => $user->role_id,
                ],
            ]);

            Log::info('✅ User update synced to web', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::error('❌ Failed to sync user update', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Synchroniser après la suppression d'un utilisateur
     */
    public function deleted(User $user)
    {
        if (!$this->apiService->isConnected()) {
            return;
        }

        try {
            Log::info('📤 Syncing user deletion to web', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            $this->apiService->post('/api/admin/sync-user', [
                'action' => 'delete',
                'user' => [
                    'email' => $user->email,
                ],
            ]);

            Log::info('✅ User deletion synced to web', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::error('❌ Failed to sync user deletion', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

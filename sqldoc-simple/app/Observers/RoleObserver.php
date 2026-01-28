<?php

namespace App\Observers;

use App\Models\Role;
use App\Services\ApiService;
use Illuminate\Support\Facades\Log;

class RoleObserver
{
    protected $apiService;

    public function __construct()
    {
        $this->apiService = app(ApiService::class);
    }

    public function created(Role $role)
    {
        if (!$this->apiService->isConnected()) {
            return;
        }

        try {
            Log::info('📤 Syncing new role to web', ['role_id' => $role->id]);

            $this->apiService->post('/api/admin/sync-role', [
                'action' => 'create',
                'role' => [
                    'name' => $role->name,
                    'description' => $role->description,
                ],
            ]);

            Log::info('✅ Role synced to web', ['role_id' => $role->id]);
        } catch (\Exception $e) {
            Log::error('❌ Failed to sync role', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Role $role)
    {
        if (!$this->apiService->isConnected()) {
            return;
        }

        try {
            Log::info('📤 Syncing updated role to web', ['role_id' => $role->id]);

            $this->apiService->post('/api/admin/sync-role', [
                'action' => 'update',
                'role' => [
                    'name' => $role->name, // Identifier
                    'description' => $role->description,
                ],
            ]);

            Log::info('✅ Role update synced to web', ['role_id' => $role->id]);
        } catch (\Exception $e) {
            Log::error('❌ Failed to sync role update', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleted(Role $role)
    {
        if (!$this->apiService->isConnected()) {
            return;
        }

        try {
            Log::info('📤 Syncing role deletion to web', ['role_id' => $role->id]);

            $this->apiService->post('/api/admin/sync-role', [
                'action' => 'delete',
                'role' => [
                    'name' => $role->name,
                ],
            ]);

            Log::info('✅ Role deletion synced to web', ['role_id' => $role->id]);
        } catch (\Exception $e) {
            Log::error('❌ Failed to sync role deletion', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

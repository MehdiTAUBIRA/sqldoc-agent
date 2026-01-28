<?php

namespace App\Observers;

use App\Models\UserProjectAccess;
use App\Models\SyncMapping;
use App\Services\ApiService;
use Illuminate\Support\Facades\Log;

class UserProjectAccessObserver
{
    protected $apiService;

    public function __construct()
    {
        $this->apiService = app(ApiService::class);
    }

    public function created(UserProjectAccess $access)
    {
        $this->syncAccess('create', $access);
    }

    public function updated(UserProjectAccess $access)
    {
        $this->syncAccess('update', $access);
    }

    public function deleted(UserProjectAccess $access)
    {
        $this->syncAccess('delete', $access);
    }

    protected function syncAccess(string $action, UserProjectAccess $access)
    {
        if (!$this->apiService->isConnected()) {
            return;
        }

        try {
            // Charger les relations si pas déjà chargées
            $access->loadMissing(['user', 'project']);

            $remoteProjectId = SyncMapping::getRemoteId('project', $access->project_id);

            if (!$remoteProjectId) {
                Log::warning('⚠️ Remote project ID not found for access sync', [
                    'project_id' => $access->project_id,
                ]);
                return;
            }

            Log::info('📤 Syncing project access to web', [
                'action' => $action,
                'user_email' => $access->user->email,
                'project_id' => $access->project_id,
            ]);

            $this->apiService->post('/api/admin/sync-project-access', [
                'action' => $action,
                'access' => [
                    'user_email' => $access->user->email,
                    'project_id' => $access->project_id,
                    'remote_project_id' => $remoteProjectId,
                    'access_level' => $access->access_level,
                ],
            ]);

            Log::info('✅ Project access synced to web');
        } catch (\Exception $e) {
            Log::error('❌ Failed to sync project access', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\PendingSync;
use App\Services\ApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncPendingChangesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        
        if (!agentConnected()) {
            Log::info('Agent not connected, skipping sync');
            return;
        }

        $apiService = app(ApiService::class);

        
        $pendingChanges = PendingSync::whereNull('synced_at')
            ->where('retry_count', '<', 3) 
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        if ($pendingChanges->isEmpty()) {
            Log::info('No pending changes to sync');
            return;
        }

        Log::info("Syncing {$pendingChanges->count()} pending changes");

        foreach ($pendingChanges as $change) {
            try {
                
                $response = match($change->method) {
                    'POST' => $apiService->post($change->endpoint, $change->data),
                    'PUT' => $apiService->put($change->endpoint, $change->data),
                    'DELETE' => $apiService->delete($change->endpoint),
                    default => throw new \Exception("Unsupported method: {$change->method}"),
                };

                
                $change->markAsSynced();

                Log::info("Synced {$change->entity_type} #{$change->entity_id}");

            } catch (\Exception $e) {
                Log::error("Failed to sync {$change->entity_type} #{$change->entity_id}", [
                    'error' => $e->getMessage(),
                ]);

                $change->incrementRetry($e->getMessage());

                
                if ($change->retry_count >= 3) {
                    Log::error("Max retries reached for {$change->entity_type} #{$change->entity_id}");
                }
            }
        }

        Log::info('Sync completed');
    }
}

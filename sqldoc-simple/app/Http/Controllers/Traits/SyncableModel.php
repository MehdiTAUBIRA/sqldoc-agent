<?php

namespace App\Http\Controllers\Traits;

use App\Models\PendingSync;
use Illuminate\Support\Facades\Log;

trait SyncableModel
{
    protected static function bootSyncableModel()
    {
        
        static::created(function ($model) {
            if (method_exists($model, 'shouldSync') && $model->shouldSync()) {
                $model->queueSync('create');
            }
        });

        
        static::updated(function ($model) {
            if (method_exists($model, 'shouldSync') && $model->shouldSync()) {
                $model->queueSync('update');
            }
        });

        
        static::deleted(function ($model) {
            if (method_exists($model, 'shouldSync') && $model->shouldSync()) {
                $model->queueSync('delete');
            }
        });
    }

    /**
     * Ajouter l'opération à la file de synchronisation
     */
    public function queueSync(string $action)
    {
        try {
            
            $data = method_exists($this, 'getSyncData') 
                ? $this->getSyncData() 
                : $this->toArray();

            PendingSync::create([
                'entity_type' => $this->table ?? class_basename($this),
                'entity_id' => $this->getKey(),
                'action' => $action,
                'data' => $data,
                'endpoint' => $this->getSyncEndpoint($action),
                'method' => $this->getSyncMethod($action),
            ]);

            Log::info("Queued sync for {$this->table}:{$this->getKey()}", [
                'action' => $action,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to queue sync for {$this->table}:{$this->getKey()}", [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Endpoint API pour la synchronisation
     */
    protected function getSyncEndpoint(string $action): string
    {
        $entityType = $this->table ?? strtolower(class_basename($this));
        $id = $this->getKey();

        return match($action) {
            'create' => "/api/{$entityType}",
            'update' => "/api/{$entityType}/{$id}",
            'delete' => "/api/{$entityType}/{$id}",
        };
    }

    /**
     * Méthode HTTP pour la synchronisation
     */
    protected function getSyncMethod(string $action): string
    {
        return match($action) {
            'create' => 'POST',
            'update' => 'PUT',
            'delete' => 'DELETE',
        };
    }

    /**
     * Déterminer si ce modèle doit être synchronisé
     */
    public function shouldSync(): bool
    {
        
        return agentConnected();
    }

    /**
     * Obtenir les données à synchroniser
     * Override cette méthode dans le modèle pour personnaliser
     */
    protected function getSyncData(): array
    {
        return $this->toArray();
    }
}
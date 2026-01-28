<?php

namespace App\Jobs;

use App\Models\DbDescription;
use App\Models\SyncMapping;
use App\Services\ApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SyncProjectToWebJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dbDescriptionId;

    public function __construct(int $dbDescriptionId)
    {
        $this->dbDescriptionId = $dbDescriptionId;
    }

    public function handle()
    {
        Log::info('🔄 SyncProjectToWebJob: Début de la synchronisation', [
            'db_description_id' => $this->dbDescriptionId
        ]);

        $apiService = app(ApiService::class);

        if (!$apiService->isConnected()) {
            Log::warning('⚠️ Agent non connecté, synchronisation annulée');
            return;
        }

        try {
            $dbDescription = DbDescription::with([
                'tableDescriptions.structures',
                'tableDescriptions.indexes',
                'tableDescriptions.relations',
                'viewDescriptions.columns',
                'viewDescriptions.information',
                'functionDescriptions.information',
                'functionDescriptions.parameters',
                'psDescriptions.information',
                'psDescriptions.parameters',
                'triggerDescriptions.information',
            ])->findOrFail($this->dbDescriptionId);

            Log::info('📦 DbDescription chargé avec relations', [
                'dbname' => $dbDescription->dbname,
                'project_id' => $dbDescription->project_id,
                'tables' => $dbDescription->tableDescriptions->count(),
                'views' => $dbDescription->viewDescriptions->count(),
                'functions' => $dbDescription->functionDescriptions->count(),
                'procedures' => $dbDescription->psDescriptions->count(),
                'triggers' => $dbDescription->triggerDescriptions->count(),
            ]);

            // ÉTAPE 0 : Synchroniser le projet parent (si existe)
            if ($dbDescription->project_id) {
                $this->syncProjectParent($apiService, $dbDescription);
            }

            // ÉTAPE 1 : Synchroniser le db_description
            $this->syncProject($apiService, $dbDescription);

            // ÉTAPE 2 : Synchroniser toutes les entités en batch
            $this->syncTablesBatch($apiService, $dbDescription);
            $this->syncViewsBatch($apiService, $dbDescription);
            $this->syncFunctionsBatch($apiService, $dbDescription);
            $this->syncProceduresBatch($apiService, $dbDescription);
            $this->syncTriggersBatch($apiService, $dbDescription);

            Log::info(' SyncProjectToWebJob: Synchronisation terminée avec succès');

        } catch (\Exception $e) {
            Log::error(' SyncProjectToWebJob: Erreur', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Synchroniser le projet parent
     */
    protected function syncProjectParent(ApiService $apiService, DbDescription $dbDescription)
    {
        try {
            $remoteProjectId = SyncMapping::getRemoteId('project', $dbDescription->project_id);
            
            if ($remoteProjectId) {
                Log::info(' Project parent already synced', [
                    'local_id' => $dbDescription->project_id,
                    'remote_id' => $remoteProjectId,
                ]);
                return $remoteProjectId;
            }

            $project = DB::table('projects')
                ->where('id', $dbDescription->project_id)
                ->first();
            
            if (!$project) {
                Log::warning('⚠️ Project parent not found locally', [
                    'project_id' => $dbDescription->project_id
                ]);
                return null;
            }

            Log::info(' Synchronisation du projet parent', [
                'local_project_id' => $project->id,
                'project_name' => $project->name ?? 'N/A',
            ]);

            $response = $apiService->post('/api/project-parents', [
                'name' => $project->name ?? null,
                'description' => $project->description ?? null,
                'user_id' => $project->user_id ?? null,
                'db_type' => $project->db_type ?? null,
                'release' => $project->release ?? null,
            ]);

            $remoteId = $response['id'] ?? null;
            
            if ($remoteId) {
                SyncMapping::saveMapping('project', $project->id, $remoteId);
                
                Log::info(' Projet parent synchronisé', [
                    'local_id' => $project->id,
                    'remote_id' => $remoteId,
                ]);
                
                return $remoteId;
            }
            
        } catch (\Exception $e) {
            Log::error(' Erreur sync projet parent', [
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }

    /**
     * Synchroniser le db_description
     */
    protected function syncProject(ApiService $apiService, DbDescription $dbDescription)
    {
        try {
            Log::info(' Synchronisation du db_description', [
                'dbname' => $dbDescription->dbname
            ]);

            $remoteProjectId = null;
            if ($dbDescription->project_id) {
                $remoteProjectId = SyncMapping::getRemoteId('project', $dbDescription->project_id);
            }

            $response = $apiService->post('/api/projects', [
                'user_id' => $dbDescription->user_id,
                'dbname' => $dbDescription->dbname,
                'description' => $dbDescription->description,
                'project_id' => $remoteProjectId,
            ]);

            $remoteId = $response['id'] ?? null;
            if ($remoteId) {
                SyncMapping::saveMapping('db_description', $dbDescription->id, $remoteId);
            }

            Log::info(' DbDescription synchronisé', [
                'local_id' => $dbDescription->id,
                'remote_id' => $remoteId,
            ]);
            
        } catch (\Exception $e) {
            Log::error(' Erreur sync db_description', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     *  Synchroniser toutes les tables en batch
     */
    protected function syncTablesBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            Log::error(' Remote DB ID not found');
            return;
        }

        // 1. Préparer toutes les tables
        $tablesData = [];
        foreach ($dbDescription->tableDescriptions as $table) {
            $tablesData[] = [
                'local_id' => $table->id,
                'dbid' => $remoteDbId,
                'tablename' => $table->tablename,
                'language' => $table->language,
                'description' => $table->description,
            ];
        }

        if (empty($tablesData)) {
            Log::info('⚠️ No tables to sync');
            return;
        }

        // 2. Envoyer toutes les tables en 1 appel
        Log::info(' Sync tables batch', ['count' => count($tablesData)]);
        
        $response = $apiService->post('/api/batch/tables', [
            'tables' => $tablesData,
        ]);

        $results = $response['results'] ?? [];
        
        // 3. Sauvegarder les mappings
        foreach ($results as $result) {
            if ($result['success'] && isset($result['local_id'], $result['remote_id'])) {
                SyncMapping::saveMapping('table', $result['local_id'], $result['remote_id']);
            }
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        Log::info(' Tables synced', [
            'total' => count($tablesData),
            'success' => $successCount,
            'errors' => count($tablesData) - $successCount,
        ]);

        // 4. Synchroniser les détails (colonnes, indexes, relations)
        $this->syncTableDetailsBatch($apiService, $dbDescription);
    }

    /**
     *  Synchroniser les détails des tables (colonnes, indexes, relations) en batch
     */
    protected function syncTableDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $allColumns = [];
        $allIndexes = [];
        $allRelations = [];

        // Collecter toutes les données
        foreach ($dbDescription->tableDescriptions as $table) {
            $remoteTableId = SyncMapping::getRemoteId('table', $table->id);
            
            if (!$remoteTableId) {
                Log::warning('⚠️ Remote table ID not found', ['table' => $table->tablename]);
                continue;
            }

            // Colonnes
            foreach ($table->structures as $column) {
                $allColumns[] = [
                    'id_table' => $remoteTableId,
                    'column' => $column->column,
                    'type' => $column->type,
                    'nullable' => $column->nullable,
                    'key' => $column->key,
                    'description' => $column->description,
                    'rangevalues' => $column->rangevalues,
                    'release_id' => $column->release_id,
                ];
            }

            // Indexes
            foreach ($table->indexes as $index) {
                $allIndexes[] = [
                    'id_table' => $remoteTableId,
                    'name' => $index->name,
                    'type' => $index->type,
                    'column' => $index->column,
                    'properties' => $index->properties,
                ];
            }

            // Relations
            foreach ($table->relations as $relation) {
                $allRelations[] = [
                    'id_table' => $remoteTableId,
                    'constraints' => $relation->constraints,
                    'column' => $relation->column,
                    'referenced_table' => $relation->referenced_table,
                    'referenced_column' => $relation->referenced_column,
                    'action' => $relation->action,
                ];
            }
        }

        // Envoyer en batch
        if (!empty($allColumns)) {
            Log::info(' Sync columns batch', ['count' => count($allColumns)]);
            $apiService->post('/api/batch/columns', ['columns' => $allColumns]);
        }

        if (!empty($allIndexes)) {
            Log::info(' Sync indexes batch', ['count' => count($allIndexes)]);
            $apiService->post('/api/batch/indexes', ['indexes' => $allIndexes]);
        }

        if (!empty($allRelations)) {
            Log::info(' Sync relations batch', ['count' => count($allRelations)]);
            $apiService->post('/api/batch/relations', ['relations' => $allRelations]);
        }
    }

    /**
     *  Synchroniser toutes les vues en batch
     */
    protected function syncViewsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            Log::error(' Remote DB ID not found');
            return;
        }

        // 1. Préparer toutes les vues
        $viewsData = [];
        foreach ($dbDescription->viewDescriptions as $view) {
            $viewsData[] = [
                'local_id' => $view->id,
                'dbid' => $remoteDbId,
                'viewname' => $view->viewname,
                'language' => $view->language,
                'description' => $view->description,
            ];
        }

        if (empty($viewsData)) {
            Log::info('⚠️ No views to sync');
            return;
        }

        // 2. Envoyer toutes les vues en 1 appel
        Log::info(' Sync views batch', ['count' => count($viewsData)]);
        
        $response = $apiService->post('/api/batch/views', [
            'views' => $viewsData,
        ]);

        $results = $response['results'] ?? [];
        
        // 3. Sauvegarder les mappings
        foreach ($results as $result) {
            if ($result['success'] && isset($result['local_id'], $result['remote_id'])) {
                SyncMapping::saveMapping('view', $result['local_id'], $result['remote_id']);
            }
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        Log::info(' Views synced', [
            'total' => count($viewsData),
            'success' => $successCount,
        ]);

        // 4. Synchroniser les détails (colonnes, information)
        $this->syncViewDetailsBatch($apiService, $dbDescription);
    }

    /**
     *  Synchroniser les détails des vues en batch
     */
    protected function syncViewDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $allColumns = [];
        $allInformation = [];

        foreach ($dbDescription->viewDescriptions as $view) {
            $remoteViewId = SyncMapping::getRemoteId('view', $view->id);
            
            if (!$remoteViewId) {
                continue;
            }

            // Colonnes
            foreach ($view->columns as $column) {
                $allColumns[] = [
                    'id_view' => $remoteViewId,
                    'name' => $column->name,
                    'type' => $column->type,
                    'is_nullable' => $column->is_nullable,
                    'max_lengh' => $column->max_lengh,
                    'description' => $column->description,
                    'precision' => $column->precision,
                    'scale' => $column->scale,
                ];
            }

            // Information
            if ($view->information) {
                $allInformation[] = [
                    'id_view' => $remoteViewId,
                    'schema_name' => $view->information->schema_name,
                    'definition' => $view->information->definition,
                    //  Utiliser une helper function pour convertir les dates
                    'creation_date' => $this->formatDate($view->information->creation_date),
                    'last_change_date' => $this->formatDate($view->information->last_change_date),
                ];
            }
        }

        if (!empty($allColumns)) {
            Log::info(' Sync view columns batch', ['count' => count($allColumns)]);
            $apiService->post('/api/batch/view-columns', ['columns' => $allColumns]);
        }

        if (!empty($allInformation)) {
            Log::info(' Sync view information batch', ['count' => count($allInformation)]);
            $apiService->post('/api/batch/view-information', ['informations' => $allInformation]);
        }
    }

    /**
     *  Synchroniser toutes les fonctions en batch
     */
    protected function syncFunctionsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            return;
        }

        // 1. Préparer toutes les fonctions
        $functionsData = [];
        foreach ($dbDescription->functionDescriptions as $function) {
            $functionsData[] = [
                'local_id' => $function->id,
                'dbid' => $remoteDbId,
                'functionname' => $function->functionname,
                'language' => $function->language,
                'description' => $function->description,
            ];
        }

        if (empty($functionsData)) {
            return;
        }

        // 2. Envoyer en batch
        Log::info(' Sync functions batch', ['count' => count($functionsData)]);
        
        $response = $apiService->post('/api/batch/functions', [
            'functions' => $functionsData,
        ]);

        $results = $response['results'] ?? [];
        
        // 3. Sauvegarder les mappings
        foreach ($results as $result) {
            if ($result['success'] && isset($result['local_id'], $result['remote_id'])) {
                SyncMapping::saveMapping('function', $result['local_id'], $result['remote_id']);
            }
        }

        Log::info(' Functions synced', ['count' => count(array_filter($results, fn($r) => $r['success']))]);

        // 4. Synchroniser les détails
        $this->syncFunctionDetailsBatch($apiService, $dbDescription);
    }

    /**
     *  Synchroniser les détails des fonctions en batch
     */
    protected function syncFunctionDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $allInformation = [];
        $allParameters = [];

        foreach ($dbDescription->functionDescriptions as $function) {
            $remoteFuncId = SyncMapping::getRemoteId('function', $function->id);
            
            if (!$remoteFuncId) {
                continue;
            }

            // Information
            if ($function->information) {
                $allInformation[] = [
                    'id_func' => $remoteFuncId,
                    'type' => $function->information->type,
                    'return_type' => $function->information->return_type,
                    'definition' => $function->information->definition,
                    'creation_date' => $this->formatDate($function->information->creation_date),  // 
                    'last_change_date' => $this->formatDate($function->information->last_change_date),  // 
                ];
            }

            // Parameters
            foreach ($function->parameters as $parameter) {
                $allParameters[] = [
                    'id_func' => $remoteFuncId,
                    'name' => $parameter->name,
                    'type' => $parameter->type,
                    'output' => $parameter->output,
                    'description' => $parameter->description,
                ];
            }
        }

        if (!empty($allInformation)) {
            Log::info(' Sync function information batch', ['count' => count($allInformation)]);
            $apiService->post('/api/batch/function-information', ['informations' => $allInformation]);
        }

        if (!empty($allParameters)) {
            Log::info(' Sync function parameters batch', ['count' => count($allParameters)]);
            $apiService->post('/api/batch/function-parameters', ['parameters' => $allParameters]);
        }
    }

    /**
     *  Synchroniser toutes les procédures en batch
     */
    protected function syncProceduresBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            return;
        }

        // 1. Préparer toutes les procédures
        $proceduresData = [];
        foreach ($dbDescription->psDescriptions as $procedure) {
            $proceduresData[] = [
                'local_id' => $procedure->id,
                'dbid' => $remoteDbId,
                'psname' => $procedure->psname,
                'language' => $procedure->language,
                'description' => $procedure->description,
            ];
        }

        if (empty($proceduresData)) {
            return;
        }

        // 2. Envoyer en batch
        Log::info(' Sync procedures batch', ['count' => count($proceduresData)]);
        
        $response = $apiService->post('/api/batch/procedures', [
            'procedures' => $proceduresData,
        ]);

        $results = $response['results'] ?? [];
        
        // 3. Sauvegarder les mappings
        foreach ($results as $result) {
            if ($result['success'] && isset($result['local_id'], $result['remote_id'])) {
                SyncMapping::saveMapping('procedure', $result['local_id'], $result['remote_id']);
            }
        }

        Log::info(' Procedures synced', ['count' => count(array_filter($results, fn($r) => $r['success']))]);

        // 4. Synchroniser les détails
        $this->syncProcedureDetailsBatch($apiService, $dbDescription);
    }

    /**
     *  Synchroniser les détails des procédures en batch
     */
    protected function syncProcedureDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $allInformation = [];
        $allParameters = [];

        foreach ($dbDescription->psDescriptions as $procedure) {
            $remotePsId = SyncMapping::getRemoteId('procedure', $procedure->id);
            
            if (!$remotePsId) {
                continue;
            }

            // Information
            if ($procedure->information) {
                $allInformation[] = [
                    'id_ps' => $remotePsId,
                    'schema' => $procedure->information->schema,
                    'creation_date' => $this->formatDate($procedure->information->creation_date),  
                    'last_change_date' => $this->formatDate($procedure->information->last_change_date),  
                    'definition' => $procedure->information->definition,
                ];
            }

            // Parameters
            foreach ($procedure->parameters as $parameter) {
                $allParameters[] = [
                    'id_ps' => $remotePsId,
                    'name' => $parameter->name,
                    'type' => $parameter->type,
                    'output' => $parameter->output,
                    'default_value' => $parameter->default_value,
                    'description' => $parameter->description,
                    'release_id' => $parameter->release_id,
                ];
            }
        }

        if (!empty($allInformation)) {
            Log::info(' Sync procedure information batch', ['count' => count($allInformation)]);
            $apiService->post('/api/batch/procedure-information', ['informations' => $allInformation]);
        }

        if (!empty($allParameters)) {
            Log::info(' Sync procedure parameters batch', ['count' => count($allParameters)]);
            $apiService->post('/api/batch/procedure-parameters', ['parameters' => $allParameters]);
        }
    }

    /**
     *  Synchroniser tous les triggers en batch
     */
    protected function syncTriggersBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            return;
        }

        // 1. Préparer tous les triggers
        $triggersData = [];
        foreach ($dbDescription->triggerDescriptions as $trigger) {
            $triggersData[] = [
                'local_id' => $trigger->id,
                'dbid' => $remoteDbId,
                'triggername' => $trigger->triggername,
                'language' => $trigger->language,
                'description' => $trigger->description,
            ];
        }

        if (empty($triggersData)) {
            return;
        }

        // 2. Envoyer en batch
        Log::info(' Sync triggers batch', ['count' => count($triggersData)]);
        
        $response = $apiService->post('/api/batch/triggers', [
            'triggers' => $triggersData,
        ]);

        $results = $response['results'] ?? [];
        
        // 3. Sauvegarder les mappings
        foreach ($results as $result) {
            if ($result['success'] && isset($result['local_id'], $result['remote_id'])) {
                SyncMapping::saveMapping('trigger', $result['local_id'], $result['remote_id']);
            }
        }

        Log::info(' Triggers synced', ['count' => count(array_filter($results, fn($r) => $r['success']))]);

        // 4. Synchroniser les détails
        $this->syncTriggerDetailsBatch($apiService, $dbDescription);
    }

    /**
     *  Synchroniser les détails des triggers en batch
     */
    protected function syncTriggerDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $allInformation = [];

        foreach ($dbDescription->triggerDescriptions as $trigger) {
            $remoteTriggerId = SyncMapping::getRemoteId('trigger', $trigger->id);
            
            if (!$remoteTriggerId) {
                continue;
            }

            // Information
            if ($trigger->information) {
                $allInformation[] = [
                    'id_trigger' => $remoteTriggerId,
                    'schema' => $trigger->information->schema,
                    'table' => $trigger->information->table,
                    'type' => $trigger->information->type,
                    'event' => $trigger->information->event,
                    'state' => $trigger->information->state,
                    'definition' => $trigger->information->definition,
                    'is_disabled' => $trigger->information->is_disabled,
                    'creation_date' => $this->formatDate($trigger->information->creation_date),  // 
                    'last_change_date' => $this->formatDate($trigger->information->last_change_date),  // 
                ];
            }
        }

        if (!empty($allInformation)) {
            Log::info(' Sync trigger information batch', ['count' => count($allInformation)]);
            $apiService->post('/api/batch/trigger-information', ['informations' => $allInformation]);
        }
    }

    protected function formatDate($date): ?string
    {
        if (empty($date)) {
            return null;
        }
        
        if (is_string($date)) {
            return $date;
        }

        if ($date instanceof \Carbon\Carbon || $date instanceof \DateTime) {
            return $date->toIso8601String();
        }

        try {
            return \Carbon\Carbon::parse($date)->toIso8601String();
        } catch (\Exception $e) {
            Log::warning(' Cannot format date', [
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
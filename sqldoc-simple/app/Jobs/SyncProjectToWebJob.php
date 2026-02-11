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

    protected const BATCH_SIZE_TABLES = 100;
    protected const BATCH_SIZE_COLUMNS = 100;
    protected const BATCH_SIZE_INDEXES = 100;
    protected const BATCH_SIZE_RELATIONS = 100;
    protected const BATCH_SIZE_VIEWS = 100;
    protected const BATCH_SIZE_VIEW_COLUMNS = 100;
    protected const BATCH_SIZE_VIEW_INFO = 100;
    protected const BATCH_SIZE_FUNCTIONS = 50;
    protected const BATCH_SIZE_FUNC_INFO = 50;
    protected const BATCH_SIZE_FUNC_PARAMS = 50;
    protected const BATCH_SIZE_PROCEDURES = 50;
    protected const BATCH_SIZE_PS_INFO = 50;
    protected const BATCH_SIZE_PS_PARAMS = 50;
    protected const BATCH_SIZE_TRIGGERS = 50;
    protected const BATCH_SIZE_TRIGGER_INFO = 50;
    
    protected const DELAY_BETWEEN_BATCHES = 1000000; // 1 seconde

    public $timeout = 3600;
    public $tries = 3;

    public function __construct(int $dbDescriptionId)
    {
        $this->dbDescriptionId = $dbDescriptionId;
    }

    public function handle()
    {
        $startTime = microtime(true);
        
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

            // ÉTAPE 2 : Synchroniser toutes les entités en batch avec chunking
            $this->syncTablesBatch($apiService, $dbDescription);
            $this->syncViewsBatch($apiService, $dbDescription);
            $this->syncFunctionsBatch($apiService, $dbDescription);
            $this->syncProceduresBatch($apiService, $dbDescription);
            $this->syncTriggersBatch($apiService, $dbDescription);

            $duration = round(microtime(true) - $startTime, 2);
            
            Log::info('✅ SyncProjectToWebJob: Synchronisation terminée avec succès', [
                'duration' => $duration . 's',
            ]);

        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);
            
            Log::error('❌ SyncProjectToWebJob: Erreur', [
                'error' => $e->getMessage(),
                'duration' => $duration . 's',
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
                Log::info('✓ Project parent already synced', [
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

            Log::info('📤 Synchronisation du projet parent', [
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
                
                Log::info('✅ Projet parent synchronisé', [
                    'local_id' => $project->id,
                    'remote_id' => $remoteId,
                ]);
                
                return $remoteId;
            }
            
        } catch (\Exception $e) {
            Log::error('❌ Erreur sync projet parent', [
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
            Log::info('📤 Synchronisation du db_description', [
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

            Log::info('✅ DbDescription synchronisé', [
                'local_id' => $dbDescription->id,
                'remote_id' => $remoteId,
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erreur sync db_description', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * ✅ Synchroniser les tables avec chunking
     */
    protected function syncTablesBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            Log::error('❌ Remote DB ID not found');
            return;
        }

        $tables = $dbDescription->tableDescriptions;
        
        if ($tables->isEmpty()) {
            Log::info('⚠️ No tables to sync');
            return;
        }
        
        Log::info('📊 Total tables to sync', ['count' => $tables->count()]);

        // ✅ Diviser en chunks
        $chunks = $tables->chunk(self::BATCH_SIZE_TABLES);
        $processedTables = 0;

        foreach ($chunks as $chunkIndex => $tableChunk) {
            $tablesData = [];
            
            foreach ($tableChunk as $table) {
                $tablesData[] = [
                    'local_id' => $table->id,
                    'dbid' => $remoteDbId,
                    'tablename' => $table->tablename,
                    'language' => $table->language,
                    'description' => $table->description,
                ];
            }

            $processedTables += count($tablesData);
            
            Log::info('📤 Sending tables batch', [
                'batch' => ($chunkIndex + 1) . '/' . $chunks->count(),
                'count' => count($tablesData),
                'progress' => round(($processedTables / $tables->count()) * 100, 1) . '%',
            ]);
            
            try {
                $response = $apiService->post('/api/batch/tables', [
                    'tables' => $tablesData,
                ]);

                $results = $response['results'] ?? [];
                
                foreach ($results as $result) {
                    if ($result['success'] && isset($result['local_id'], $result['remote_id'])) {
                        SyncMapping::saveMapping('table', $result['local_id'], $result['remote_id']);
                    }
                }

                $successCount = count(array_filter($results, fn($r) => $r['success']));
                Log::info('✅ Tables batch synced', [
                    'success' => $successCount,
                    'total' => count($tablesData),
                ]);
                
                usleep(self::DELAY_BETWEEN_BATCHES);
                
            } catch (\Exception $e) {
                Log::error('❌ Failed to sync tables batch', [
                    'batch' => ($chunkIndex + 1),
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        Log::info('✅ All tables synced', ['total' => $processedTables]);

        // Synchroniser les détails
        $this->syncTableDetailsBatch($apiService, $dbDescription);
    }

    /**
     * ✅ Synchroniser les détails des tables avec chunking
     */
    protected function syncTableDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        Log::info('🔧 Syncing table details');

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

        // ✅ Envoyer par chunks avec helper
        if (!empty($allColumns)) {
            $this->sendInBatches($apiService, '/api/batch/columns', 'columns', $allColumns, self::BATCH_SIZE_COLUMNS);
        }

        if (!empty($allIndexes)) {
            $this->sendInBatches($apiService, '/api/batch/indexes', 'indexes', $allIndexes, self::BATCH_SIZE_INDEXES);
        }

        if (!empty($allRelations)) {
            $this->sendInBatches($apiService, '/api/batch/relations', 'relations', $allRelations, self::BATCH_SIZE_RELATIONS);
        }
    }

    /**
     * ✅ Synchroniser les vues avec chunking
     */
    protected function syncViewsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            Log::error('❌ Remote DB ID not found');
            return;
        }

        $views = $dbDescription->viewDescriptions;
        
        if ($views->isEmpty()) {
            Log::info('⚠️ No views to sync');
            return;
        }
        
        Log::info('📊 Total views to sync', ['count' => $views->count()]);

        $chunks = $views->chunk(self::BATCH_SIZE_VIEWS);
        $processedViews = 0;

        foreach ($chunks as $chunkIndex => $viewChunk) {
            $viewsData = [];
            
            foreach ($viewChunk as $view) {
                $viewsData[] = [
                    'local_id' => $view->id,
                    'dbid' => $remoteDbId,
                    'viewname' => $view->viewname,
                    'language' => $view->language,
                    'description' => $view->description,
                ];
            }

            $processedViews += count($viewsData);
            
            Log::info('📤 Sending views batch', [
                'batch' => ($chunkIndex + 1) . '/' . $chunks->count(),
                'count' => count($viewsData),
                'progress' => round(($processedViews / $views->count()) * 100, 1) . '%',
            ]);
            
            try {
                $response = $apiService->post('/api/batch/views', ['views' => $viewsData]);

                $results = $response['results'] ?? [];
                
                foreach ($results as $result) {
                    if ($result['success'] && isset($result['local_id'], $result['remote_id'])) {
                        SyncMapping::saveMapping('view', $result['local_id'], $result['remote_id']);
                    }
                }

                usleep(self::DELAY_BETWEEN_BATCHES);
                
            } catch (\Exception $e) {
                Log::error('❌ Failed to sync views batch', [
                    'batch' => ($chunkIndex + 1),
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        Log::info('✅ All views synced', ['total' => $processedViews]);

        $this->syncViewDetailsBatch($apiService, $dbDescription);
    }

    /**
     * ✅ Synchroniser les détails des vues avec chunking
     */
    protected function syncViewDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        Log::info('🔧 Syncing view details', [
            'total_views' => $dbDescription->viewDescriptions->count()
        ]);

        $allColumns = [];
        $allInformation = [];

        foreach ($dbDescription->viewDescriptions as $view) {
            $remoteViewId = SyncMapping::getRemoteId('view', $view->id);
            
            if (!$remoteViewId) {
                Log::warning('❌ No remote ID found for view', [
                    'local_view_id' => $view->id,
                    'view_name' => $view->name
                ]);
                continue;
            }

            Log::info('✅ Processing view', [
                'local_id' => $view->id,
                'remote_id' => $remoteViewId,
                'view_name' => $view->name,
                'columns_count' => $view->columns->count()
            ]);

            // Colonnes
            foreach ($view->columns as $column) {
                $allColumns[] = [
                    'id_view' => $remoteViewId,
                    'name' => $column->name,
                    'type' => $column->type,
                    'is_nullable' => $column->is_nullable,
                    'max_length' => $column->max_length,
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
                    'creation_date' => $this->formatDate($view->information->creation_date),
                    'last_change_date' => $this->formatDate($view->information->last_change_date),
                ];
            }
        }

        Log::info('📊 Prepared data for sync', [
            'columns_count' => count($allColumns),
            'information_count' => count($allInformation)
        ]);

        if (!empty($allColumns)) {
            Log::info('📤 Sending view columns');
            $response = $this->sendInBatches($apiService, '/api/batch/view-columns', 'columns', $allColumns, self::BATCH_SIZE_VIEW_COLUMNS);
            Log::info('✅ View columns response', ['response' => $response]);
        }

        if (!empty($allInformation)) {
            Log::info('📤 Sending view information');
            $response = $this->sendInBatches($apiService, '/api/batch/view-information', 'informations', $allInformation, self::BATCH_SIZE_VIEW_INFO);
            Log::info('✅ View information response', ['response' => $response]);
        }
    }

    /**
     * ✅ Synchroniser les fonctions avec chunking
     */
    protected function syncFunctionsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            return;
        }

        $functions = $dbDescription->functionDescriptions;
        
        if ($functions->isEmpty()) {
            Log::info('⚠️ No functions to sync');
            return;
        }
        
        Log::info('📊 Total functions to sync', ['count' => $functions->count()]);

        $chunks = $functions->chunk(self::BATCH_SIZE_FUNCTIONS);
        $processedFunctions = 0;

        foreach ($chunks as $chunkIndex => $functionChunk) {
            $functionsData = [];
            
            foreach ($functionChunk as $function) {
                $functionsData[] = [
                    'local_id' => $function->id,
                    'dbid' => $remoteDbId,
                    'functionname' => $function->functionname,
                    'language' => $function->language,
                    'description' => $function->description,
                ];
            }

            $processedFunctions += count($functionsData);
            
            Log::info('📤 Sending functions batch', [
                'batch' => ($chunkIndex + 1) . '/' . $chunks->count(),
                'count' => count($functionsData),
            ]);
            
            try {
                $response = $apiService->post('/api/batch/functions', ['functions' => $functionsData]);

                $results = $response['results'] ?? [];
                
                foreach ($results as $result) {
                    if ($result['success'] && isset($result['local_id'], $result['remote_id'])) {
                        SyncMapping::saveMapping('function', $result['local_id'], $result['remote_id']);
                    }
                }

                usleep(self::DELAY_BETWEEN_BATCHES);
                
            } catch (\Exception $e) {
                Log::error('❌ Failed to sync functions batch', [
                    'batch' => ($chunkIndex + 1),
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        Log::info('✅ All functions synced', ['total' => $processedFunctions]);

        $this->syncFunctionDetailsBatch($apiService, $dbDescription);
    }

    /**
     * ✅ Synchroniser les détails des fonctions avec chunking
     */
    protected function syncFunctionDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        Log::info('🔧 Syncing function details');

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
                    'creation_date' => $this->formatDate($function->information->creation_date),
                    'last_change_date' => $this->formatDate($function->information->last_change_date),
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
            $this->sendInBatches($apiService, '/api/batch/function-information', 'informations', $allInformation, self::BATCH_SIZE_FUNC_INFO);
        }

        if (!empty($allParameters)) {
            $this->sendInBatches($apiService, '/api/batch/function-parameters', 'parameters', $allParameters, self::BATCH_SIZE_FUNC_PARAMS);
        }
    }

    /**
     * ✅ Synchroniser les procédures avec chunking
     */
    protected function syncProceduresBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            return;
        }

        $procedures = $dbDescription->psDescriptions;
        
        if ($procedures->isEmpty()) {
            Log::info('⚠️ No procedures to sync');
            return;
        }
        
        Log::info('📊 Total procedures to sync', ['count' => $procedures->count()]);

        $chunks = $procedures->chunk(self::BATCH_SIZE_PROCEDURES);
        $processedProcedures = 0;

        foreach ($chunks as $chunkIndex => $procedureChunk) {
            $proceduresData = [];
            
            foreach ($procedureChunk as $procedure) {
                $proceduresData[] = [
                    'local_id' => $procedure->id,
                    'dbid' => $remoteDbId,
                    'psname' => $procedure->psname,
                    'language' => $procedure->language,
                    'description' => $procedure->description,
                ];
            }

            $processedProcedures += count($proceduresData);
            
            Log::info('📤 Sending procedures batch', [
                'batch' => ($chunkIndex + 1) . '/' . $chunks->count(),
                'count' => count($proceduresData),
            ]);
            
            try {
                $response = $apiService->post('/api/batch/procedures', ['procedures' => $proceduresData]);

                $results = $response['results'] ?? [];
                
                foreach ($results as $result) {
                    if ($result['success'] && isset($result['local_id'], $result['remote_id'])) {
                        SyncMapping::saveMapping('procedure', $result['local_id'], $result['remote_id']);
                    }
                }

                usleep(self::DELAY_BETWEEN_BATCHES);
                
            } catch (\Exception $e) {
                Log::error('❌ Failed to sync procedures batch', [
                    'batch' => ($chunkIndex + 1),
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        Log::info('✅ All procedures synced', ['total' => $processedProcedures]);

        $this->syncProcedureDetailsBatch($apiService, $dbDescription);
    }

    /**
     * ✅ Synchroniser les détails des procédures avec chunking
     */
    protected function syncProcedureDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        Log::info('🔧 Syncing procedure details');

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
            $this->sendInBatches($apiService, '/api/batch/procedure-information', 'informations', $allInformation, self::BATCH_SIZE_PS_INFO);
        }

        if (!empty($allParameters)) {
            $this->sendInBatches($apiService, '/api/batch/procedure-parameters', 'parameters', $allParameters, self::BATCH_SIZE_PS_PARAMS);
        }
    }

    /**
     * ✅ Synchroniser les triggers avec chunking
     */
    protected function syncTriggersBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            return;
        }

        $triggers = $dbDescription->triggerDescriptions;
        
        if ($triggers->isEmpty()) {
            Log::info('⚠️ No triggers to sync');
            return;
        }
        
        Log::info('📊 Total triggers to sync', ['count' => $triggers->count()]);

        $chunks = $triggers->chunk(self::BATCH_SIZE_TRIGGERS);
        $processedTriggers = 0;

        foreach ($chunks as $chunkIndex => $triggerChunk) {
            $triggersData = [];
            
            foreach ($triggerChunk as $trigger) {
                $triggersData[] = [
                    'local_id' => $trigger->id,
                    'dbid' => $remoteDbId,
                    'triggername' => $trigger->triggername,
                    'language' => $trigger->language,
                    'description' => $trigger->description,
                ];
            }

            $processedTriggers += count($triggersData);
            
            Log::info('📤 Sending triggers batch', [
                'batch' => ($chunkIndex + 1) . '/' . $chunks->count(),
                'count' => count($triggersData),
            ]);
            
            try {
                $response = $apiService->post('/api/batch/triggers', ['triggers' => $triggersData]);

                $results = $response['results'] ?? [];
                
                foreach ($results as $result) {
                    if ($result['success'] && isset($result['local_id'], $result['remote_id'])) {
                        SyncMapping::saveMapping('trigger', $result['local_id'], $result['remote_id']);
                    }
                }

                usleep(self::DELAY_BETWEEN_BATCHES);
                
            } catch (\Exception $e) {
                Log::error('❌ Failed to sync triggers batch', [
                    'batch' => ($chunkIndex + 1),
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        Log::info('✅ All triggers synced', ['total' => $processedTriggers]);

        $this->syncTriggerDetailsBatch($apiService, $dbDescription);
    }

    /**
     * ✅ Synchroniser les détails des triggers avec chunking
     */
    protected function syncTriggerDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        Log::info('🔧 Syncing trigger details');

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
                    'creation_date' => $this->formatDate($trigger->information->creation_date),
                    'last_change_date' => $this->formatDate($trigger->information->last_change_date),
                ];
            }
        }

        if (!empty($allInformation)) {
            $this->sendInBatches($apiService, '/api/batch/trigger-information', 'informations', $allInformation, self::BATCH_SIZE_TRIGGER_INFO);
        }
    }

    /**
     * ✅ Helper pour envoyer des données en batches
     */
    protected function sendInBatches(ApiService $apiService, string $endpoint, string $key, array $data, int $batchSize)
    {
        $total = count($data);
        
        if ($total === 0) {
            return;
        }

        Log::info("📊 Sending {$key} in batches", ['total' => $total]);

        $chunks = array_chunk($data, $batchSize);
        $processed = 0;

        foreach ($chunks as $index => $chunk) {
            $processed += count($chunk);
            
            Log::info("📤 Sending {$key} batch", [
                'batch' => ($index + 1) . '/' . count($chunks),
                'count' => count($chunk),
                'progress' => round(($processed / $total) * 100, 1) . '%',
            ]);
            
            try {
                $apiService->post($endpoint, [$key => $chunk]);
                usleep(self::DELAY_BETWEEN_BATCHES);
            } catch (\Exception $e) {
                Log::error("❌ Failed to sync {$key} batch", [
                    'batch' => ($index + 1),
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }
        
        Log::info("✅ All {$key} synced", ['total' => $processed]);
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
            Log::warning('⚠️ Cannot format date', [
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
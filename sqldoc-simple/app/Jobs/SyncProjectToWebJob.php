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

    public $timeout = 7200; // 2 heures
    public $tries = 3;

    public function __construct(int $dbDescriptionId)
    {
        $this->dbDescriptionId = $dbDescriptionId;
    }

    public function handle()
    {
        $startTime = microtime(true);
    

        $apiService = app(ApiService::class);

        if (!$apiService->isConnected()) {
            Log::warning('⚠️ Agent non connecté, synchronisation annulée');
            return;
        }

        try {
            $dbDescription = DbDescription::findOrFail($this->dbDescriptionId);

            // ÉTAPE 0 : Synchroniser le projet parent
            if ($dbDescription->project_id) {
                try {
                    $this->syncProjectParent($apiService, $dbDescription);
                } catch (\Exception $e) {
                    Log::error('❌ Error syncing project parent', ['error' => $e->getMessage()]);
                }
            }

            // ÉTAPE 1 : Synchroniser le db_description
            try {
                $this->syncProject($apiService, $dbDescription);
            } catch (\Exception $e) {
                Log::error('❌ Error syncing project', ['error' => $e->getMessage()]);
                throw $e;
            }

            // ÉTAPE 2 : Synchroniser les entités
            try {
                $this->syncTablesBatch($apiService, $dbDescription);
            } catch (\Exception $e) {
                Log::error('❌ Error syncing tables', ['error' => $e->getMessage()]);
            }

            try {
                $this->syncViewsBatch($apiService, $dbDescription);
            } catch (\Exception $e) {
                Log::error('❌ Error syncing views', ['error' => $e->getMessage()]);
            }

            try {
                $this->syncFunctionsBatch($apiService, $dbDescription);
            } catch (\Exception $e) {
                Log::error('❌ Error syncing functions', ['error' => $e->getMessage()]);
            }

            try {
                $this->syncProceduresBatch($apiService, $dbDescription);
            } catch (\Exception $e) {
                Log::error('❌ Error syncing procedures', ['error' => $e->getMessage()]);
            }

            try {
                $this->syncTriggersBatch($apiService, $dbDescription);
            } catch (\Exception $e) {
                Log::error('❌ Error syncing triggers', ['error' => $e->getMessage()]);
            }

            $duration = round(microtime(true) - $startTime, 2);
            
            Log::info('✅ SyncProjectToWebJob: Synchronisation terminée avec succès', [
                'duration' => $duration . 's',
                'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB'
            ]);

        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);
            
            Log::error('❌ SyncProjectToWebJob: Erreur générale', [
                'error' => $e->getMessage(),
                'duration' => $duration . 's',
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * ✅ Helper pour upsert les mappings en masse
     */
    protected function upsertMappings(string $entityType, array $results): void
    {
        $mappings = collect($results)
            ->filter(fn($res) => $res['success'] ?? false)
            ->map(fn($res) => [
                'entity_type' => $entityType,
                'local_id' => $res['local_id'],
                'remote_id' => $res['remote_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

        if (!empty($mappings)) {
            SyncMapping::upsert(
                $mappings, 
                ['entity_type', 'local_id'],
                ['remote_id', 'updated_at']
            );
            
        }
    }

    protected function syncProjectParent(ApiService $apiService, DbDescription $dbDescription)
    {
        try {
            $remoteProjectId = SyncMapping::getRemoteId('project', $dbDescription->project_id);
            
            if ($remoteProjectId) {
                
                return $remoteProjectId;
            }

            $project = DB::table('projects')
                ->where('id', $dbDescription->project_id)
                ->first();
            
            if (!$project) {
                
                return null;
            }


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
                
                return $remoteId;
            }
            
        } catch (\Exception $e) {
            Log::error('❌ Erreur sync projet parent', [
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }

    protected function syncProject(ApiService $apiService, DbDescription $dbDescription)
    {
        try {

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
            
        } catch (\Exception $e) {
            Log::error('❌ Erreur sync db_description', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * ✅ OPTIMISÉ : Synchroniser les tables avec upsert
     */
    protected function syncTablesBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
           
            return;
        }

        $total = $dbDescription->tableDescriptions()->count();
        
        if ($total === 0) {
            
            return;
        }
        

        $dbDescription->tableDescriptions()
            ->select('id', 'tablename', 'language', 'description')
            ->orderBy('id')
            ->chunkById(200, function ($tableChunk) use ($apiService, $remoteDbId) {
                
                $tablesData = $tableChunk->map(fn($t) => [
                    'local_id' => $t->id,
                    'dbid' => $remoteDbId,
                    'tablename' => $t->tablename,
                    'language' => $t->language,
                    'description' => $t->description,
                ])->toArray();
                
                $response = $apiService->post('/api/batch/tables', ['tables' => $tablesData]);

                // ✅ UPSERT MAPPINGS
                $this->upsertMappings('table', $response['results'] ?? []);
            });

        $this->syncTableDetailsBatch($apiService, $dbDescription);
    }

    /**
     * ✅ OPTIMISÉ : Synchroniser les détails par chunks
     */
    protected function syncTableDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        
        try {
            $tableMappings = SyncMapping::where('entity_type', 'table')
                ->pluck('remote_id', 'local_id')
                ->toArray();
            
            
            if (empty($tableMappings)) {
                
                return;
            }
            
            $dbDescription->tableDescriptions()
                ->select('id')
                ->orderBy('id')
                ->chunkById(200, function ($tableChunk) use ($apiService, $tableMappings) {
                    
                    $tableIds = $tableChunk->pluck('id');
                    
                    $tables = \App\Models\TableDescription::whereIn('id', $tableIds)
                        ->with(['structures', 'indexes', 'relations'])
                        ->get();
                    
                    $allColumns = [];
                    $allIndexes = [];
                    $allRelations = [];
                    
                    foreach ($tables as $table) {
                        $remoteTableId = $tableMappings[$table->id] ?? null;
                        
                        if (!$remoteTableId) {
                            continue;
                        }
                        
                        foreach ($table->structures as $column) {
                            $allColumns[] = [
                                'id_table' => $remoteTableId,
                                'column' => $column->column,
                                'type' => $column->type,
                                'nullable' => $column->nullable,
                                'key' => $column->key,
                                'description' => $column->description,
                                'rangevalues' => $column->rangevalues ?? null,
                                'release_id' => $column->release_id ?? null,
                            ];
                        }
                        
                        foreach ($table->indexes as $index) {
                            $allIndexes[] = [
                                'id_table' => $remoteTableId,
                                'name' => $index->name,
                                'type' => $index->type,
                                'column' => $index->column,
                                'properties' => $index->properties ?? null,
                            ];
                        }
                        
                        foreach ($table->relations as $relation) {
                            $allRelations[] = [
                                'id_table' => $remoteTableId,
                                'constraints' => $relation->constraints,
                                'column' => $relation->column,
                                'referenced_table' => $relation->referenced_table,
                                'referenced_column' => $relation->referenced_column,
                                'action' => $relation->action ?? null,
                            ];
                        }
                    }
                    
                    if (!empty($allColumns)) {
                        $this->sendInBatches($apiService, '/api/batch/columns', 'columns', $allColumns, 500);
                    }
                    
                    if (!empty($allIndexes)) {
                        $this->sendInBatches($apiService, '/api/batch/indexes', 'indexes', $allIndexes, 500);
                    }
                    
                    if (!empty($allRelations)) {
                        $this->sendInBatches($apiService, '/api/batch/relations', 'relations', $allRelations, 500);
                    }
                    
                    unset($tables, $allColumns, $allIndexes, $allRelations);
                });
            
        } catch (\Exception $e) {
            Log::error('❌ Error in table details sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * ✅ OPTIMISÉ : Vues avec upsert
     */
    protected function syncViewsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            return;
        }

        $total = $dbDescription->viewDescriptions()->count();
        
        if ($total === 0) {
            return;
        }

        $dbDescription->viewDescriptions()
            ->select('id', 'viewname', 'language', 'description')
            ->orderBy('id')
            ->chunkById(200, function ($viewChunk) use ($apiService, $remoteDbId) {
                
                $viewsData = $viewChunk->map(fn($v) => [
                    'local_id' => $v->id,
                    'dbid' => $remoteDbId,
                    'viewname' => $v->viewname,
                    'language' => $v->language,
                    'description' => $v->description,
                ])->toArray();
                
                $response = $apiService->post('/api/batch/views', ['views' => $viewsData]);

                $this->upsertMappings('view', $response['results'] ?? []);
            });

        $this->syncViewDetailsBatch($apiService, $dbDescription);
    }

    protected function syncViewDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        
        try {
            $viewMappings = SyncMapping::where('entity_type', 'view')
                ->pluck('remote_id', 'local_id')
                ->toArray();
            
            if (empty($viewMappings)) {
                return;
            }
            
            $dbDescription->viewDescriptions()
                ->select('id')
                ->orderBy('id')
                ->chunkById(200, function ($viewChunk) use ($apiService, $viewMappings) {
                    
                    $viewIds = $viewChunk->pluck('id');
                    
                    $views = \App\Models\ViewDescription::whereIn('id', $viewIds)
                        ->with(['columns', 'information'])
                        ->get();
                    
                    $allColumns = [];
                    $allInformation = [];
                    
                    foreach ($views as $view) {
                        $remoteViewId = $viewMappings[$view->id] ?? null;
                        
                        if (!$remoteViewId) {
                            continue;
                        }
                        
                        foreach ($view->columns as $column) {
                            $allColumns[] = [
                                'id_view' => $remoteViewId,
                                'name' => $column->name,
                                'type' => $column->type,
                                'is_nullable' => $column->is_nullable ?? false,
                                'max_length' => $column->max_length ?? null,
                                'description' => $column->description ?? null,
                                'precision' => $column->precision ?? null,
                                'scale' => $column->scale ?? null,
                            ];
                        }
                        
                        if ($view->information) {
                            $allInformation[] = [
                                'id_view' => $remoteViewId,
                                'schema_name' => $view->information->schema_name ?? null,
                                'definition' => $view->information->definition ?? null,
                                'creation_date' => $this->formatDate($view->information->creation_date),
                                'last_change_date' => $this->formatDate($view->information->last_change_date),
                            ];
                        }
                    }
                    
                    if (!empty($allColumns)) {
                        $this->sendInBatches($apiService, '/api/batch/view-columns', 'columns', $allColumns, 500);
                    }
                    
                    if (!empty($allInformation)) {
                        $apiService->post('/api/batch/view-information', ['informations' => $allInformation]);
                    }
                    
                    unset($views, $allColumns, $allInformation);
                });
            
        } catch (\Exception $e) {
            Log::error('❌ Error in view details sync', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ✅ OPTIMISÉ : Fonctions avec upsert
     */
    protected function syncFunctionsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            return;
        }

        $total = $dbDescription->functionDescriptions()->count();
        
        if ($total === 0) {
            return;
        }

        $dbDescription->functionDescriptions()
            ->select('id', 'functionname', 'language', 'description')
            ->orderBy('id')
            ->chunkById(200, function ($functionChunk) use ($apiService, $remoteDbId) {
                
                $functionsData = $functionChunk->map(fn($f) => [
                    'local_id' => $f->id,
                    'dbid' => $remoteDbId,
                    'functionname' => $f->functionname,
                    'language' => $f->language,
                    'description' => $f->description,
                ])->toArray();
                
                $response = $apiService->post('/api/batch/functions', ['functions' => $functionsData]);

                $this->upsertMappings('function', $response['results'] ?? []);
            });

        $this->syncFunctionDetailsBatch($apiService, $dbDescription);
    }

    protected function syncFunctionDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        
        try {
            $functionMappings = SyncMapping::where('entity_type', 'function')
                ->pluck('remote_id', 'local_id')
                ->toArray();
            
            if (empty($functionMappings)) {
                return;
            }
            
            $dbDescription->functionDescriptions()
                ->select('id')
                ->orderBy('id')
                ->chunkById(200, function ($functionChunk) use ($apiService, $functionMappings) {
                    
                    $functionIds = $functionChunk->pluck('id');
                    
                    $functions = \App\Models\FunctionDescription::whereIn('id', $functionIds)
                        ->with(['information', 'parameters'])
                        ->get();
                    
                    $allInformation = [];
                    $allParameters = [];
                    
                    foreach ($functions as $function) {
                        $remoteFuncId = $functionMappings[$function->id] ?? null;
                        
                        if (!$remoteFuncId) {
                            continue;
                        }
                        
                        if ($function->information) {
                            $allInformation[] = [
                                'id_func' => $remoteFuncId,
                                'type' => $function->information->type ?? null,
                                'return_type' => $function->information->return_type ?? null,
                                'definition' => $function->information->definition ?? null,
                                'creation_date' => $this->formatDate($function->information->creation_date),
                                'last_change_date' => $this->formatDate($function->information->last_change_date),
                            ];
                        }
                        
                        foreach ($function->parameters as $param) {
                            $allParameters[] = [
                                'id_func' => $remoteFuncId,
                                'name' => $param->name,
                                'type' => $param->type,
                                'output' => $param->output ?? null,
                                'description' => $param->description ?? null,
                            ];
                        }
                    }
                    
                    if (!empty($allInformation)) {
                        $apiService->post('/api/batch/function-information', ['informations' => $allInformation]);
                    }
                    
                    if (!empty($allParameters)) {
                        $this->sendInBatches($apiService, '/api/batch/function-parameters', 'parameters', $allParameters, 500);
                    }
                    
                    unset($functions, $allInformation, $allParameters);
                });
            
        } catch (\Exception $e) {
            Log::error('❌ Error in function details sync', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ✅ OPTIMISÉ : Procédures avec upsert
     */
    protected function syncProceduresBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            return;
        }

        $total = $dbDescription->psDescriptions()->count();
        
        if ($total === 0) {
            return;
        }

        $dbDescription->psDescriptions()
            ->select('id', 'psname', 'language', 'description')
            ->orderBy('id')
            ->chunkById(200, function ($procedureChunk) use ($apiService, $remoteDbId) {
                
                $proceduresData = $procedureChunk->map(fn($p) => [
                    'local_id' => $p->id,
                    'dbid' => $remoteDbId,
                    'psname' => $p->psname,
                    'language' => $p->language,
                    'description' => $p->description,
                ])->toArray();
                
                $response = $apiService->post('/api/batch/procedures', ['procedures' => $proceduresData]);

                $this->upsertMappings('procedure', $response['results'] ?? []);
            });

        $this->syncProcedureDetailsBatch($apiService, $dbDescription);
    }

    protected function syncProcedureDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        
        try {
            $procedureMappings = SyncMapping::where('entity_type', 'procedure')
                ->pluck('remote_id', 'local_id')
                ->toArray();
            
            if (empty($procedureMappings)) {
                return;
            }
            
            $dbDescription->psDescriptions()
                ->select('id')
                ->orderBy('id')
                ->chunkById(500, function ($procedureChunk) use ($apiService, $procedureMappings) {
                    
                    $procedureIds = $procedureChunk->pluck('id');
                    
                    $procedures = \App\Models\PsDescription::whereIn('id', $procedureIds)
                        ->with(['information', 'parameters'])
                        ->get();
                    
                    $allInformation = [];
                    $allParameters = [];
                    
                    foreach ($procedures as $procedure) {
                        $remotePsId = $procedureMappings[$procedure->id] ?? null;
                        
                        if (!$remotePsId) {
                            continue;
                        }
                        
                        if ($procedure->information) {
                            $allInformation[] = [
                                'id_ps' => $remotePsId,
                                'schema' => $procedure->information->schema ?? null,
                                'creation_date' => $this->formatDate($procedure->information->creation_date),
                                'last_change_date' => $this->formatDate($procedure->information->last_change_date),
                                'definition' => $procedure->information->definition ?? null,
                            ];
                        }
                        
                        foreach ($procedure->parameters as $param) {
                            $allParameters[] = [
                                'id_ps' => $remotePsId,
                                'name' => $param->name,
                                'type' => $param->type,
                                'output' => $param->output ?? null,
                                'default_value' => $param->default_value ?? null,
                                'description' => $param->description ?? null,
                                'release_id' => $param->release_id ?? null,
                            ];
                        }
                    }
                    
                    if (!empty($allInformation)) {
                        $apiService->post('/api/batch/procedure-information', ['informations' => $allInformation]);
                    }
                    
                    if (!empty($allParameters)) {
                        $this->sendInBatches($apiService, '/api/batch/procedure-parameters', 'parameters', $allParameters, 1000);
                    }
                    
                    unset($procedures, $allInformation, $allParameters);
                });
            
        } catch (\Exception $e) {
            Log::error('❌ Error in procedure details sync', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ✅ OPTIMISÉ : Triggers avec upsert
     */
    protected function syncTriggersBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            return;
        }

        $total = $dbDescription->triggerDescriptions()->count();
        
        if ($total === 0) {
            return;
        }

        $dbDescription->triggerDescriptions()
            ->select('id', 'triggername', 'language', 'description')
            ->orderBy('id')
            ->chunkById(200, function ($triggerChunk) use ($apiService, $remoteDbId) {
                
                $triggersData = $triggerChunk->map(fn($t) => [
                    'local_id' => $t->id,
                    'dbid' => $remoteDbId,
                    'triggername' => $t->triggername,
                    'language' => $t->language,
                    'description' => $t->description,
                ])->toArray();
                
                $response = $apiService->post('/api/batch/triggers', ['triggers' => $triggersData]);

                $this->upsertMappings('trigger', $response['results'] ?? []);
            });

        $this->syncTriggerDetailsBatch($apiService, $dbDescription);
    }

    protected function syncTriggerDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        
        try {
            $triggerMappings = SyncMapping::where('entity_type', 'trigger')
                ->pluck('remote_id', 'local_id')
                ->toArray();
            
            if (empty($triggerMappings)) {
                return;
            }
            
            $dbDescription->triggerDescriptions()
                ->select('id')
                ->orderBy('id')
                ->chunkById(200, function ($triggerChunk) use ($apiService, $triggerMappings) {
                    
                    $triggerIds = $triggerChunk->pluck('id');
                    
                    $triggers = \App\Models\TriggerDescription::whereIn('id', $triggerIds)
                        ->with('information')
                        ->get();
                    
                    $allInformation = [];
                    
                    foreach ($triggers as $trigger) {
                        $remoteTriggerId = $triggerMappings[$trigger->id] ?? null;
                        
                        if (!$remoteTriggerId) {
                            continue;
                        }
                        
                        if ($trigger->information) {
                            $allInformation[] = [
                                'id_trigger' => $remoteTriggerId,
                                'schema' => $trigger->information->schema ?? null,
                                'table' => $trigger->information->table ?? null,
                                'type' => $trigger->information->type ?? null,
                                'event' => $trigger->information->event ?? null,
                                'state' => $trigger->information->state ?? null,
                                'definition' => $trigger->information->definition ?? null,
                                'is_disabled' => $trigger->information->is_disabled ?? false,
                                'creation_date' => $this->formatDate($trigger->information->creation_date),
                                'last_change_date' => $this->formatDate($trigger->information->last_change_date),
                            ];
                        }
                    }
                    
                    if (!empty($allInformation)) {
                        $apiService->post('/api/batch/trigger-information', ['informations' => $allInformation]);
                    }
                    
                    unset($triggers, $allInformation);
                });
            
        } catch (\Exception $e) {
            Log::error('❌ Error in trigger details sync', ['error' => $e->getMessage()]);
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

        $chunks = array_chunk($data, $batchSize);

        foreach ($chunks as $index => $chunk) {
            try {
                $apiService->post($endpoint, [$key => $chunk]);
            } catch (\Exception $e) {
                Log::error("❌ Failed to sync {$key} batch", [
                    'batch' => ($index + 1),
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
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
            return null;
        }
    }
}
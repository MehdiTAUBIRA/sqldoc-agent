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
    
    // ✅ Configuration des tailles de batch
    protected const BATCH_SIZE_TABLES = 50;
    protected const BATCH_SIZE_COLUMNS = 100;
    protected const BATCH_SIZE_INDEXES = 100;
    protected const BATCH_SIZE_RELATIONS = 100;
    protected const BATCH_SIZE_VIEWS = 50;
    protected const BATCH_SIZE_VIEW_COLUMNS = 100;
    protected const BATCH_SIZE_VIEW_INFO = 50;
    protected const BATCH_SIZE_FUNCTIONS = 50;
    protected const BATCH_SIZE_FUNC_INFO = 50;
    protected const BATCH_SIZE_FUNC_PARAMS = 100;
    protected const BATCH_SIZE_PROCEDURES = 50;
    protected const BATCH_SIZE_PS_INFO = 50;
    protected const BATCH_SIZE_PS_PARAMS = 100;
    protected const BATCH_SIZE_TRIGGERS = 50;
    protected const BATCH_SIZE_TRIGGER_INFO = 50;
    
    // ✅ Timeout augmenté pour les gros projets
    public $timeout = 3600; // 1 heure
    
    // ✅ Nombre de tentatives
    public $tries = 3;
    
    // ✅ Délai entre les batches (microsecondes)
    protected const DELAY_BETWEEN_BATCHES = 500000; // 0.5 seconde

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
            // ✅ Charger SEULEMENT le DbDescription (sans relations eager loading)
            $dbDescription = DbDescription::findOrFail($this->dbDescriptionId);

            Log::info('📦 DbDescription chargé', [
                'dbname' => $dbDescription->dbname,
                'project_id' => $dbDescription->project_id,
            ]);

            // ÉTAPE 0 : Synchroniser le projet parent (si existe)
            if ($dbDescription->project_id) {
                $this->syncProjectParent($apiService, $dbDescription);
            }

            // ÉTAPE 1 : Synchroniser le db_description
            $this->syncProject($apiService, $dbDescription);

            // ÉTAPE 2 : Synchroniser toutes les entités par chunks
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

            $response = $apiService->post('/api/sync/project-parent', [
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

            $response = $apiService->post('/api/sync/project', [
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
     * ✅ Synchroniser les tables par chunks
     */
    protected function syncTablesBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            Log::error('❌ Remote DB ID not found');
            return;
        }

        // ✅ Compter d'abord
        $totalTables = DB::table('table_descriptions')
            ->where('dbid', $dbDescription->id)
            ->count();
        
        if ($totalTables === 0) {
            Log::info('⚠️ No tables to sync');
            return;
        }
        
        Log::info(' Total tables to sync', ['count' => $totalTables]);

        $processedTables = 0;

        // ✅ Traiter par chunks
        DB::table('table_descriptions')
            ->where('dbid', $dbDescription->id)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_TABLES, function ($tables) use ($apiService, $remoteDbId, &$processedTables, $totalTables) {
                
                $tablesData = [];
                
                foreach ($tables as $table) {
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
                    'count' => count($tablesData),
                    'progress' => round(($processedTables / $totalTables) * 100, 1) . '%',
                ]);
                
                try {
                    $response = $apiService->post('/api/sync/tables/batch', [
                        'tables' => $tablesData,
                    ]);

                    $results = $response['results'] ?? [];
                    
                    // Sauvegarder les mappings
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
                    
                    // ✅ Délai entre les batches
                    usleep(self::DELAY_BETWEEN_BATCHES);
                    
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync tables batch', [
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            });

        Log::info('✅ All tables synced', ['total' => $processedTables]);

        // ✅ Synchroniser les détails APRÈS toutes les tables
        $this->syncTableDetailsBatch($apiService, $dbDescription);
    }

    /**
     * ✅ Synchroniser les détails des tables par chunks
     */
    protected function syncTableDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        // ✅ Récupérer les IDs des tables locales
        $tableIds = DB::table('table_descriptions')
            ->where('dbid', $dbDescription->id)
            ->pluck('id');

        Log::info(' Syncing table details', ['tables' => $tableIds->count()]);

        // ✅ COLONNES par batch
        $this->syncColumnsInBatches($apiService, $tableIds);
        
        // ✅ INDEXES par batch
        $this->syncIndexesInBatches($apiService, $tableIds);
        
        // ✅ RELATIONS par batch
        $this->syncRelationsInBatches($apiService, $tableIds);
    }

    /**
     * ✅ Synchroniser les colonnes par batches
     */
    protected function syncColumnsInBatches(ApiService $apiService, $tableIds)
    {
        $totalColumns = DB::table('table_structures')
            ->whereIn('id_table', $tableIds)
            ->count();
        
        if ($totalColumns === 0) {
            Log::info('⚠️ No columns to sync');
            return;
        }
        
        Log::info(' Total columns to sync', ['count' => $totalColumns]);

        $processedColumns = 0;

        DB::table('table_structures')
            ->whereIn('id_table', $tableIds)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_COLUMNS, function ($columns) use ($apiService, &$processedColumns, $totalColumns) {
                
                $columnsData = [];
                
                foreach ($columns as $column) {
                    // ✅ Mapper l'ID de table local → remote
                    $remoteTableId = SyncMapping::getRemoteId('table', $column->id_table);
                    
                    if (!$remoteTableId) {
                        Log::warning('⚠️ Remote table ID not found', ['table_id' => $column->id_table]);
                        continue;
                    }

                    $columnsData[] = [
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

                if (empty($columnsData)) {
                    return;
                }

                $processedColumns += count($columnsData);
                
                Log::info('📤 Sending columns batch', [
                    'count' => count($columnsData),
                    'progress' => round(($processedColumns / $totalColumns) * 100, 1) . '%',
                ]);
                
                try {
                    $apiService->post('/api/sync/columns/batch', ['columns' => $columnsData]);
                    usleep(self::DELAY_BETWEEN_BATCHES);
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync columns batch', ['error' => $e->getMessage()]);
                    throw $e;
                }
            });
        
        Log::info('✅ All columns synced', ['total' => $processedColumns]);
    }

    /**
     * ✅ Synchroniser les indexes par batches
     */
    protected function syncIndexesInBatches(ApiService $apiService, $tableIds)
    {
        $totalIndexes = DB::table('table_indexes')
            ->whereIn('id_table', $tableIds)
            ->count();
        
        if ($totalIndexes === 0) {
            Log::info('⚠️ No indexes to sync');
            return;
        }
        
        Log::info(' Total indexes to sync', ['count' => $totalIndexes]);

        $processedIndexes = 0;

        DB::table('table_indexes')
            ->whereIn('id_table', $tableIds)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_INDEXES, function ($indexes) use ($apiService, &$processedIndexes, $totalIndexes) {
                
                $indexesData = [];
                
                foreach ($indexes as $index) {
                    $remoteTableId = SyncMapping::getRemoteId('table', $index->id_table);
                    
                    if (!$remoteTableId) {
                        continue;
                    }

                    $indexesData[] = [
                        'id_table' => $remoteTableId,
                        'name' => $index->name,
                        'type' => $index->type,
                        'column' => $index->column,
                        'properties' => $index->properties,
                    ];
                }

                if (empty($indexesData)) {
                    return;
                }

                $processedIndexes += count($indexesData);
                
                Log::info('📤 Sending indexes batch', [
                    'count' => count($indexesData),
                    'progress' => round(($processedIndexes / $totalIndexes) * 100, 1) . '%',
                ]);
                
                try {
                    $apiService->post('/api/sync/indexes/batch', ['indexes' => $indexesData]);
                    usleep(self::DELAY_BETWEEN_BATCHES);
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync indexes batch', ['error' => $e->getMessage()]);
                    throw $e;
                }
            });
        
        Log::info('✅ All indexes synced', ['total' => $processedIndexes]);
    }

    /**
     * ✅ Synchroniser les relations par batches
     */
    protected function syncRelationsInBatches(ApiService $apiService, $tableIds)
    {
        $totalRelations = DB::table('table_relations')
            ->whereIn('id_table', $tableIds)
            ->count();
        
        if ($totalRelations === 0) {
            Log::info('⚠️ No relations to sync');
            return;
        }
        
        Log::info(' Total relations to sync', ['count' => $totalRelations]);

        $processedRelations = 0;

        DB::table('table_relations')
            ->whereIn('id_table', $tableIds)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_RELATIONS, function ($relations) use ($apiService, &$processedRelations, $totalRelations) {
                
                $relationsData = [];
                
                foreach ($relations as $relation) {
                    $remoteTableId = SyncMapping::getRemoteId('table', $relation->id_table);
                    
                    if (!$remoteTableId) {
                        continue;
                    }

                    $relationsData[] = [
                        'id_table' => $remoteTableId,
                        'constraints' => $relation->constraints,
                        'column' => $relation->column,
                        'referenced_table' => $relation->referenced_table,
                        'referenced_column' => $relation->referenced_column,
                        'action' => $relation->action,
                    ];
                }

                if (empty($relationsData)) {
                    return;
                }

                $processedRelations += count($relationsData);
                
                Log::info('📤 Sending relations batch', [
                    'count' => count($relationsData),
                    'progress' => round(($processedRelations / $totalRelations) * 100, 1) . '%',
                ]);
                
                try {
                    $apiService->post('/api/sync/relations/batch', ['relations' => $relationsData]);
                    usleep(self::DELAY_BETWEEN_BATCHES);
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync relations batch', ['error' => $e->getMessage()]);
                    throw $e;
                }
            });
        
        Log::info('✅ All relations synced', ['total' => $processedRelations]);
    }

    /**
     * ✅ Synchroniser les vues par chunks
     */
    protected function syncViewsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            return;
        }

        $totalViews = DB::table('view_descriptions')
            ->where('dbid', $dbDescription->id)
            ->count();
        
        if ($totalViews === 0) {
            Log::info('⚠️ No views to sync');
            return;
        }
        
        Log::info(' Total views to sync', ['count' => $totalViews]);

        $processedViews = 0;

        DB::table('view_descriptions')
            ->where('dbid', $dbDescription->id)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_VIEWS, function ($views) use ($apiService, $remoteDbId, &$processedViews, $totalViews) {
                
                $viewsData = [];
                
                foreach ($views as $view) {
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
                    'count' => count($viewsData),
                    'progress' => round(($processedViews / $totalViews) * 100, 1) . '%',
                ]);
                
                try {
                    $response = $apiService->post('/api/sync/views/batch', ['views' => $viewsData]);

                    $results = $response['results'] ?? [];
                    
                    foreach ($results as $result) {
                        if ($result['success'] && isset($result['local_id'], $result['remote_id'])) {
                            SyncMapping::saveMapping('view', $result['local_id'], $result['remote_id']);
                        }
                    }

                    usleep(self::DELAY_BETWEEN_BATCHES);
                    
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync views batch', ['error' => $e->getMessage()]);
                    throw $e;
                }
            });

        Log::info('✅ All views synced', ['total' => $processedViews]);

        // Synchroniser les détails des vues
        $this->syncViewDetailsBatch($apiService, $dbDescription);
    }

    /**
     * ✅ Synchroniser les détails des vues en batch
     */
    protected function syncViewDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $viewIds = DB::table('view_descriptions')
            ->where('dbid', $dbDescription->id)
            ->pluck('id');

        Log::info(' Syncing view details', ['views' => $viewIds->count()]);

        // Colonnes des vues
        $this->syncViewColumnsInBatches($apiService, $viewIds);
        
        // Information des vues
        $this->syncViewInformationInBatches($apiService, $viewIds);
    }

    /**
     * ✅ Synchroniser les colonnes des vues par batches
     */
    protected function syncViewColumnsInBatches(ApiService $apiService, $viewIds)
    {
        $totalColumns = DB::table('view_columns')
            ->whereIn('id_view', $viewIds)
            ->count();
        
        if ($totalColumns === 0) {
            Log::info('⚠️ No view columns to sync');
            return;
        }
        
        Log::info(' Total view columns to sync', ['count' => $totalColumns]);

        $processedColumns = 0;

        DB::table('view_columns')
            ->whereIn('id_view', $viewIds)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_VIEW_COLUMNS, function ($columns) use ($apiService, &$processedColumns, $totalColumns) {
                
                $columnsData = [];
                
                foreach ($columns as $column) {
                    $remoteViewId = SyncMapping::getRemoteId('view', $column->id_view);
                    
                    if (!$remoteViewId) {
                        continue;
                    }

                    $columnsData[] = [
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

                if (empty($columnsData)) {
                    return;
                }

                $processedColumns += count($columnsData);
                
                Log::info('📤 Sending view columns batch', [
                    'count' => count($columnsData),
                    'progress' => round(($processedColumns / $totalColumns) * 100, 1) . '%',
                ]);
                
                try {
                    $apiService->post('/api/sync/view-columns/batch', ['columns' => $columnsData]);
                    usleep(self::DELAY_BETWEEN_BATCHES);
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync view columns batch', ['error' => $e->getMessage()]);
                    throw $e;
                }
            });
        
        Log::info('✅ All view columns synced', ['total' => $processedColumns]);
    }

    /**
     * ✅ Synchroniser les informations des vues par batches
     */
    protected function syncViewInformationInBatches(ApiService $apiService, $viewIds)
    {
        $totalInfo = DB::table('view_information')
            ->whereIn('id_view', $viewIds)
            ->count();
        
        if ($totalInfo === 0) {
            Log::info('⚠️ No view information to sync');
            return;
        }
        
        Log::info(' Total view information to sync', ['count' => $totalInfo]);

        $processedInfo = 0;

        DB::table('view_information')
            ->whereIn('id_view', $viewIds)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_VIEW_INFO, function ($infos) use ($apiService, &$processedInfo, $totalInfo) {
                
                $infoData = [];
                
                foreach ($infos as $info) {
                    $remoteViewId = SyncMapping::getRemoteId('view', $info->id_view);
                    
                    if (!$remoteViewId) {
                        continue;
                    }

                    $infoData[] = [
                        'id_view' => $remoteViewId,
                        'schema_name' => $info->schema_name,
                        'definition' => $info->definition,
                        'creation_date' => $this->formatDate($info->creation_date),
                        'last_change_date' => $this->formatDate($info->last_change_date),
                    ];
                }

                if (empty($infoData)) {
                    return;
                }

                $processedInfo += count($infoData);
                
                Log::info('📤 Sending view information batch', [
                    'count' => count($infoData),
                    'progress' => round(($processedInfo / $totalInfo) * 100, 1) . '%',
                ]);
                
                try {
                    $apiService->post('/api/sync/view-information/batch', ['informations' => $infoData]);
                    usleep(self::DELAY_BETWEEN_BATCHES);
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync view information batch', ['error' => $e->getMessage()]);
                    throw $e;
                }
            });
        
        Log::info('✅ All view information synced', ['total' => $processedInfo]);
    }

    /**
     * ✅ Synchroniser les fonctions par chunks
     */
    protected function syncFunctionsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            return;
        }

        $totalFunctions = DB::table('function_descriptions')
            ->where('dbid', $dbDescription->id)
            ->count();
        
        if ($totalFunctions === 0) {
            Log::info('⚠️ No functions to sync');
            return;
        }
        
        Log::info(' Total functions to sync', ['count' => $totalFunctions]);

        $processedFunctions = 0;

        DB::table('function_descriptions')
            ->where('dbid', $dbDescription->id)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_FUNCTIONS, function ($functions) use ($apiService, $remoteDbId, &$processedFunctions, $totalFunctions) {
                
                $functionsData = [];
                
                foreach ($functions as $function) {
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
                    'count' => count($functionsData),
                    'progress' => round(($processedFunctions / $totalFunctions) * 100, 1) . '%',
                ]);
                
                try {
                    $response = $apiService->post('/api/sync/functions/batch', ['functions' => $functionsData]);

                    $results = $response['results'] ?? [];
                    
                    foreach ($results as $result) {
                        if ($result['success'] && isset($result['local_id'], $result['remote_id'])) {
                            SyncMapping::saveMapping('function', $result['local_id'], $result['remote_id']);
                        }
                    }

                    usleep(self::DELAY_BETWEEN_BATCHES);
                    
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync functions batch', ['error' => $e->getMessage()]);
                    throw $e;
                }
            });

        Log::info('✅ All functions synced', ['total' => $processedFunctions]);

        // Synchroniser les détails
        $this->syncFunctionDetailsBatch($apiService, $dbDescription);
    }

    /**
     * ✅ Synchroniser les détails des fonctions en batch
     */
    protected function syncFunctionDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $functionIds = DB::table('function_descriptions')
            ->where('dbid', $dbDescription->id)
            ->pluck('id');

        Log::info(' Syncing function details', ['functions' => $functionIds->count()]);

        // Information des fonctions
        $this->syncFunctionInformationInBatches($apiService, $functionIds);
        
        // Paramètres des fonctions
        $this->syncFunctionParametersInBatches($apiService, $functionIds);
    }

    /**
     * ✅ Synchroniser les informations des fonctions par batches
     */
    protected function syncFunctionInformationInBatches(ApiService $apiService, $functionIds)
    {
        $totalInfo = DB::table('func_information')
            ->whereIn('id_func', $functionIds)
            ->count();
        
        if ($totalInfo === 0) {
            Log::info('⚠️ No function information to sync');
            return;
        }
        
        Log::info(' Total function information to sync', ['count' => $totalInfo]);

        $processedInfo = 0;

        DB::table('func_information')
            ->whereIn('id_func', $functionIds)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_FUNC_INFO, function ($infos) use ($apiService, &$processedInfo, $totalInfo) {
                
                $infoData = [];
                
                foreach ($infos as $info) {
                    $remoteFuncId = SyncMapping::getRemoteId('function', $info->id_func);
                    
                    if (!$remoteFuncId) {
                        continue;
                    }

                    $infoData[] = [
                        'id_func' => $remoteFuncId,
                        'type' => $info->type,
                        'return_type' => $info->return_type,
                        'definition' => $info->definition,
                        'creation_date' => $this->formatDate($info->creation_date),
                        'last_change_date' => $this->formatDate($info->last_change_date),
                    ];
                }

                if (empty($infoData)) {
                    return;
                }

                $processedInfo += count($infoData);
                
                Log::info('📤 Sending function information batch', [
                    'count' => count($infoData),
                    'progress' => round(($processedInfo / $totalInfo) * 100, 1) . '%',
                ]);
                
                try {
                    $apiService->post('/api/sync/function-information/batch', ['informations' => $infoData]);
                    usleep(self::DELAY_BETWEEN_BATCHES);
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync function information batch', ['error' => $e->getMessage()]);
                    throw $e;
                }
            });
        
        Log::info('✅ All function information synced', ['total' => $processedInfo]);
    }

    /**
     * ✅ Synchroniser les paramètres des fonctions par batches
     */
    protected function syncFunctionParametersInBatches(ApiService $apiService, $functionIds)
    {
        $totalParams = DB::table('func_parameters')
            ->whereIn('id_func', $functionIds)
            ->count();
        
        if ($totalParams === 0) {
            Log::info('⚠️ No function parameters to sync');
            return;
        }
        
        Log::info(' Total function parameters to sync', ['count' => $totalParams]);

        $processedParams = 0;

        DB::table('func_parameters')
            ->whereIn('id_func', $functionIds)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_FUNC_PARAMS, function ($params) use ($apiService, &$processedParams, $totalParams) {
                
                $paramsData = [];
                
                foreach ($params as $param) {
                    $remoteFuncId = SyncMapping::getRemoteId('function', $param->id_func);
                    
                    if (!$remoteFuncId) {
                        continue;
                    }

                    $paramsData[] = [
                        'id_func' => $remoteFuncId,
                        'name' => $param->name,
                        'type' => $param->type,
                        'output' => $param->output,
                        'description' => $param->description,
                    ];
                }

                if (empty($paramsData)) {
                    return;
                }

                $processedParams += count($paramsData);
                
                Log::info('📤 Sending function parameters batch', [
                    'count' => count($paramsData),
                    'progress' => round(($processedParams / $totalParams) * 100, 1) . '%',
                ]);
                
                try {
                    $apiService->post('/api/sync/function-parameters/batch', ['parameters' => $paramsData]);
                    usleep(self::DELAY_BETWEEN_BATCHES);
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync function parameters batch', ['error' => $e->getMessage()]);
                    throw $e;
                }
            });
        
        Log::info('✅ All function parameters synced', ['total' => $processedParams]);
    }

    /**
     * ✅ Synchroniser les procédures par chunks
     */
    protected function syncProceduresBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            return;
        }

        $totalProcedures = DB::table('ps_descriptions')
            ->where('dbid', $dbDescription->id)
            ->count();
        
        if ($totalProcedures === 0) {
            Log::info('⚠️ No procedures to sync');
            return;
        }
        
        Log::info(' Total procedures to sync', ['count' => $totalProcedures]);

        $processedProcedures = 0;

        DB::table('ps_descriptions')
            ->where('dbid', $dbDescription->id)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_PROCEDURES, function ($procedures) use ($apiService, $remoteDbId, &$processedProcedures, $totalProcedures) {
                
                $proceduresData = [];
                
                foreach ($procedures as $procedure) {
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
                    'count' => count($proceduresData),
                    'progress' => round(($processedProcedures / $totalProcedures) * 100, 1) . '%',
                ]);
                
                try {
                    $response = $apiService->post('/api/sync/procedures/batch', ['procedures' => $proceduresData]);

                    $results = $response['results'] ?? [];
                    
                    foreach ($results as $result) {
                        if ($result['success'] && isset($result['local_id'], $result['remote_id'])) {
                            SyncMapping::saveMapping('procedure', $result['local_id'], $result['remote_id']);
                        }
                    }

                    usleep(self::DELAY_BETWEEN_BATCHES);
                    
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync procedures batch', ['error' => $e->getMessage()]);
                    throw $e;
                }
            });

        Log::info('✅ All procedures synced', ['total' => $processedProcedures]);

        // Synchroniser les détails
        $this->syncProcedureDetailsBatch($apiService, $dbDescription);
    }

    /**
     * ✅ Synchroniser les détails des procédures en batch
     */
    protected function syncProcedureDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $procedureIds = DB::table('ps_descriptions')
            ->where('dbid', $dbDescription->id)
            ->pluck('id');

        Log::info(' Syncing procedure details', ['procedures' => $procedureIds->count()]);

        // Information des procédures
        $this->syncProcedureInformationInBatches($apiService, $procedureIds);
        
        // Paramètres des procédures
        $this->syncProcedureParametersInBatches($apiService, $procedureIds);
    }

    /**
     * ✅ Synchroniser les informations des procédures par batches
     */
    protected function syncProcedureInformationInBatches(ApiService $apiService, $procedureIds)
    {
        $totalInfo = DB::table('ps_information')
            ->whereIn('id_ps', $procedureIds)
            ->count();
        
        if ($totalInfo === 0) {
            Log::info('⚠️ No procedure information to sync');
            return;
        }
        
        Log::info(' Total procedure information to sync', ['count' => $totalInfo]);

        $processedInfo = 0;

        DB::table('ps_information')
            ->whereIn('id_ps', $procedureIds)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_PS_INFO, function ($infos) use ($apiService, &$processedInfo, $totalInfo) {
                
                $infoData = [];
                
                foreach ($infos as $info) {
                    $remotePsId = SyncMapping::getRemoteId('procedure', $info->id_ps);
                    
                    if (!$remotePsId) {
                        continue;
                    }

                    $infoData[] = [
                        'id_ps' => $remotePsId,
                        'schema' => $info->schema,
                        'creation_date' => $this->formatDate($info->creation_date),
                        'last_change_date' => $this->formatDate($info->last_change_date),
                        'definition' => $info->definition,
                    ];
                }

                if (empty($infoData)) {
                    return;
                }

                $processedInfo += count($infoData);
                
                Log::info('📤 Sending procedure information batch', [
                    'count' => count($infoData),
                    'progress' => round(($processedInfo / $totalInfo) * 100, 1) . '%',
                ]);
                
                try {
                    $apiService->post('/api/sync/procedure-information/batch', ['informations' => $infoData]);
                    usleep(self::DELAY_BETWEEN_BATCHES);
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync procedure information batch', ['error' => $e->getMessage()]);
                    throw $e;
                }
            });
        
        Log::info('✅ All procedure information synced', ['total' => $processedInfo]);
    }

    /**
     * ✅ Synchroniser les paramètres des procédures par batches
     */
    protected function syncProcedureParametersInBatches(ApiService $apiService, $procedureIds)
    {
        $totalParams = DB::table('ps_parameters')
            ->whereIn('id_ps', $procedureIds)
            ->count();
        
        if ($totalParams === 0) {
            Log::info('⚠️ No procedure parameters to sync');
            return;
        }
        
        Log::info(' Total procedure parameters to sync', ['count' => $totalParams]);

        $processedParams = 0;

        DB::table('ps_parameters')
            ->whereIn('id_ps', $procedureIds)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_PS_PARAMS, function ($params) use ($apiService, &$processedParams, $totalParams) {
                
                $paramsData = [];
                
                foreach ($params as $param) {
                    $remotePsId = SyncMapping::getRemoteId('procedure', $param->id_ps);
                    
                    if (!$remotePsId) {
                        continue;
                    }

                    $paramsData[] = [
                        'id_ps' => $remotePsId,
                        'name' => $param->name,
                        'type' => $param->type,
                        'output' => $param->output,
                        'default_value' => $param->default_value,
                        'description' => $param->description,
                        'release_id' => $param->release_id,
                    ];
                }

                if (empty($paramsData)) {
                    return;
                }

                $processedParams += count($paramsData);
                
                Log::info('📤 Sending procedure parameters batch', [
                    'count' => count($paramsData),
                    'progress' => round(($processedParams / $totalParams) * 100, 1) . '%',
                ]);
                
                try {
                    $apiService->post('/api/sync/procedure-parameters/batch', ['parameters' => $paramsData]);
                    usleep(self::DELAY_BETWEEN_BATCHES);
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync procedure parameters batch', ['error' => $e->getMessage()]);
                    throw $e;
                }
            });
        
        Log::info('✅ All procedure parameters synced', ['total' => $processedParams]);
    }

    /**
     * ✅ Synchroniser les triggers par chunks
     */
    protected function syncTriggersBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $remoteDbId = SyncMapping::getRemoteId('db_description', $dbDescription->id);
        
        if (!$remoteDbId) {
            return;
        }

        $totalTriggers = DB::table('trigger_descriptions')
            ->where('dbid', $dbDescription->id)
            ->count();
        
        if ($totalTriggers === 0) {
            Log::info('⚠️ No triggers to sync');
            return;
        }
        
        Log::info(' Total triggers to sync', ['count' => $totalTriggers]);

        $processedTriggers = 0;

        DB::table('trigger_descriptions')
            ->where('dbid', $dbDescription->id)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_TRIGGERS, function ($triggers) use ($apiService, $remoteDbId, &$processedTriggers, $totalTriggers) {
                
                $triggersData = [];
                
                foreach ($triggers as $trigger) {
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
                    'count' => count($triggersData),
                    'progress' => round(($processedTriggers / $totalTriggers) * 100, 1) . '%',
                ]);
                
                try {
                    $response = $apiService->post('/api/sync/triggers/batch', ['triggers' => $triggersData]);

                    $results = $response['results'] ?? [];
                    
                    foreach ($results as $result) {
                        if ($result['success'] && isset($result['local_id'], $result['remote_id'])) {
                            SyncMapping::saveMapping('trigger', $result['local_id'], $result['remote_id']);
                        }
                    }

                    usleep(self::DELAY_BETWEEN_BATCHES);
                    
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync triggers batch', ['error' => $e->getMessage()]);
                    throw $e;
                }
            });

        Log::info('✅ All triggers synced', ['total' => $processedTriggers]);

        // Synchroniser les détails
        $this->syncTriggerDetailsBatch($apiService, $dbDescription);
    }

    /**
     * ✅ Synchroniser les détails des triggers en batch
     */
    protected function syncTriggerDetailsBatch(ApiService $apiService, DbDescription $dbDescription)
    {
        $triggerIds = DB::table('trigger_descriptions')
            ->where('dbid', $dbDescription->id)
            ->pluck('id');

        if ($triggerIds->isEmpty()) {
            return;
        }

        Log::info(' Syncing trigger details', ['triggers' => $triggerIds->count()]);

        // Information des triggers
        $this->syncTriggerInformationInBatches($apiService, $triggerIds);
    }

    /**
     * ✅ Synchroniser les informations des triggers par batches
     */
    protected function syncTriggerInformationInBatches(ApiService $apiService, $triggerIds)
    {
        $totalInfo = DB::table('trigger_information')
            ->whereIn('id_trigger', $triggerIds)
            ->count();
        
        if ($totalInfo === 0) {
            Log::info('⚠️ No trigger information to sync');
            return;
        }
        
        Log::info(' Total trigger information to sync', ['count' => $totalInfo]);

        $processedInfo = 0;

        DB::table('trigger_information')
            ->whereIn('id_trigger', $triggerIds)
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE_TRIGGER_INFO, function ($infos) use ($apiService, &$processedInfo, $totalInfo) {
                
                $infoData = [];
                
                foreach ($infos as $info) {
                    $remoteTriggerId = SyncMapping::getRemoteId('trigger', $info->id_trigger);
                    
                    if (!$remoteTriggerId) {
                        continue;
                    }

                    $infoData[] = [
                        'id_trigger' => $remoteTriggerId,
                        'schema' => $info->schema,
                        'table' => $info->table,
                        'type' => $info->type,
                        'event' => $info->event,
                        'state' => $info->state,
                        'definition' => $info->definition,
                        'is_disabled' => $info->is_disabled,
                        'creation_date' => $this->formatDate($info->creation_date),
                        'last_change_date' => $this->formatDate($info->last_change_date),
                    ];
                }

                if (empty($infoData)) {
                    return;
                }

                $processedInfo += count($infoData);
                
                Log::info('📤 Sending trigger information batch', [
                    'count' => count($infoData),
                    'progress' => round(($processedInfo / $totalInfo) * 100, 1) . '%',
                ]);
                
                try {
                    $apiService->post('/api/sync/trigger-information/batch', ['informations' => $infoData]);
                    usleep(self::DELAY_BETWEEN_BATCHES);
                } catch (\Exception $e) {
                    Log::error('❌ Failed to sync trigger information batch', ['error' => $e->getMessage()]);
                    throw $e;
                }
            });
        
        Log::info('✅ All trigger information synced', ['total' => $processedInfo]);
    }

    /**
     * Helper pour formater les dates
     */
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
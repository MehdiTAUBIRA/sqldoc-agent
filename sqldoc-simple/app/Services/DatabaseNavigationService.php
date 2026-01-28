<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\DbDescription;
use App\Models\TableDescription;
use App\Models\ViewDescription;
use App\Models\FunctionDescription;
use App\Models\PsDescription;
use App\Models\TriggerDescription;
use App\Models\TableStructure;
use App\Models\TableRelation;

class DatabaseNavigationService
{
    /**
     * Récuperer la structure de navigation avec mise en cache intelligente
     *
     * @param int|null $dbId
     * @param int|null $userId
     * @return array
     */
    public function getNavigationStructure($dbId = null, $userId = null): array
    {
        $dbId = $dbId ?? $this->getCurrentDatabaseId();
        $userId = $userId ?? auth()->id();
        
        // Clé de cache unique par utilisateur et base de données
        $cacheKey = "navigation_structure_{$userId}_{$dbId}";
        
        // Cache pendant 30 minutes
        return Cache::remember($cacheKey, 1800, function () use ($dbId, $userId) {
            Log::info('DatabaseNavigationService - Génération de la structure depuis la base', [
                'user_id' => $userId,
                'db_id' => $dbId,
                'cache_key' => "navigation_structure_{$userId}_{$dbId}"
            ]);
            
            return $this->buildNavigationStructure($dbId);
        });
    }

    /**
     * Construit la structure de navigation optimisée
     *
     * @param int $dbId
     * @return array
     */
    private function buildNavigationStructure(int $dbId): array
    {
        $startTime = microtime(true);
        
        try {
            // Récuperation optimisée des tables avec informations de clés
            $tables = $this->getTablesWithKeyInfo($dbId);
            
            // Récuperation simple des autres objets
            $views = $this->getViews($dbId);
            $functions = $this->getFunctions($dbId);
            $procedures = $this->getProcedures($dbId);
            $triggers = $this->getTriggers($dbId);
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            Log::info('DatabaseNavigationService - Structure générée avec succès', [
                'db_id' => $dbId,
                'execution_time_ms' => round($executionTime, 2),
                'tables_count' => $tables->count(),
                'views_count' => $views->count(),
                'functions_count' => $functions->count(),
                'procedures_count' => $procedures->count(),
                'triggers_count' => $triggers->count()
            ]);
            
            return [
                'tables' => $tables,
                'views' => $views,
                'functions' => $functions,
                'procedures' => $procedures,
                'triggers' => $triggers,
                'metadata' => [
                    'generated_at' => now()->toISOString(),
                    'execution_time_ms' => round($executionTime, 2),
                    'total_objects' => $tables->count() + $views->count() + $functions->count() + $procedures->count() + $triggers->count(),
                    'db_id' => $dbId
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('DatabaseNavigationService - Erreur lors de la génération', [
                'db_id' => $dbId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Retourner une structure vide en cas d'erreur
            return [
                'tables' => collect([]),
                'views' => collect([]),
                'functions' => collect([]),
                'procedures' => collect([]),
                'triggers' => collect([]),
                'metadata' => [
                    'generated_at' => now()->toISOString(),
                    'execution_time_ms' => 0,
                    'total_objects' => 0,
                    'db_id' => $dbId,
                    'error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Récuperer les tables avec informations sur les clés
     *
     * @param int $dbId
     * @return \Illuminate\Support\Collection
     */
    private function getTablesWithKeyInfo(int $dbId)
    {
        // Requête optimisée avec sous-requêtes pour éviter les N+1
        $tables = DB::table('table_description as td')
            ->select([
                'td.id',
                'td.tablename as name',
                'td.description',
                'td.created_at',
                'td.updated_at',
                // Sous-requête pour vérifier l'existence d'une clé primaire
                DB::raw('(SELECT COUNT(*) > 0 FROM table_structure ts WHERE ts.id_table = td.id AND ts.key = "PK") as has_primary_key'),
                // Sous-requête pour vérifier l'existence de clés étrangères
                DB::raw('(SELECT COUNT(*) > 0 FROM table_relation tr WHERE tr.id_table = td.id) as has_foreign_key')
            ])
            ->where('td.dbid', $dbId)
            ->orderBy('td.tablename')
            ->get()
            ->map(function ($table) {
                // Convertir les booléens
                $table->has_primary_key = (bool) $table->has_primary_key;
                $table->has_foreign_key = (bool) $table->has_foreign_key;
                return $table;
            });

        return $tables;
    }

    /**
     * Récuperer les vues
     *
     * @param int $dbId
     * @return \Illuminate\Support\Collection
     */
    private function getViews(int $dbId)
    {
        return DB::table('view_description')
            ->select('id', 'viewname as name', 'description', 'created_at', 'updated_at')
            ->where('dbid', $dbId)
            ->orderBy('viewname')
            ->get();
    }

    /**
     * Récuperer les fonctions
     *
     * @param int $dbId
     * @return \Illuminate\Support\Collection
     */
    private function getFunctions(int $dbId)
    {
        return DB::table('function_description')
            ->select('id', 'functionname as name', 'description', 'created_at', 'updated_at')
            ->where('dbid', $dbId)
            ->orderBy('functionname')
            ->get();
    }

    /**
     * Récuperer les procédures stockées
     *
     * @param int $dbId
     * @return \Illuminate\Support\Collection
     */
    private function getProcedures(int $dbId)
    {
        return DB::table('ps_description')
            ->select('id', 'psname as name', 'description', 'created_at', 'updated_at')
            ->where('dbid', $dbId)
            ->orderBy('psname')
            ->get();
    }

    /**
     * Récuperer les triggers
     *
     * @param int $dbId
     * @return \Illuminate\Support\Collection
     */
    private function getTriggers(int $dbId)
    {
        return DB::table('trigger_description')
            ->select('id', 'triggername as name', 'description', 'created_at', 'updated_at')
            ->where('dbid', $dbId)
            ->orderBy('triggername')
            ->get();
    }

    /**
     * Récupère l'ID de la base de données actuelle
     *
     * @return int
     * @throws \Exception
     */
    private function getCurrentDatabaseId(): int
    {
        // Vérifier d'abord la session
        if (session()->has('current_database_id')) {
            return session('current_database_id');
        }

        // chercher une base par défaut
        $defaultDb = DbDescription::where('is_default', true)->first();
        if ($defaultDb) {
            return $defaultDb->id;
        }

        // Sinon, prendre la première base disponible
        $firstDb = DbDescription::first();
        if ($firstDb) {
            return $firstDb->id;
        }

        throw new \Exception('Aucune base de données configurée');
    }

    /**
     * Vide le cache de navigation pour un utilisateur/base donnée
     *
     * @param int|null $dbId
     * @param int|null $userId
     * @return bool
     */
    public function clearNavigationCache($dbId = null, $userId = null): bool
    {
        $dbId = $dbId ?? $this->getCurrentDatabaseId();
        $userId = $userId ?? auth()->id();
        
        $cacheKey = "navigation_structure_{$userId}_{$dbId}";
        
        $result = Cache::forget($cacheKey);
        
        Log::info('DatabaseNavigationService - Cache vidé', [
            'user_id' => $userId,
            'db_id' => $dbId,
            'cache_key' => $cacheKey,
            'success' => $result
        ]);
        
        return $result;
    }

    /**
     * Rafraîchit le cache de navigation
     *
     * @param int|null $dbId
     * @param int|null $userId
     * @return array
     */
    public function refreshNavigationStructure($dbId = null, $userId = null): array
    {
        // Vider le cache
        $this->clearNavigationCache($dbId, $userId);
        
        // regénérer
        return $this->getNavigationStructure($dbId, $userId);
    }

    /**
     * Vérifie si les données de navigation sont en cache
     *
     * @param int|null $dbId
     * @param int|null $userId
     * @return bool
     */
    public function hasNavigationCache($dbId = null, $userId = null): bool
    {
        $dbId = $dbId ?? $this->getCurrentDatabaseId();
        $userId = $userId ?? auth()->id();
        
        $cacheKey = "navigation_structure_{$userId}_{$dbId}";
        
        return Cache::has($cacheKey);
    }
}
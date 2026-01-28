<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasProjectPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\DbDescription;
use App\Models\TableDescription;
use App\Models\TableStructure;
use App\Models\TableRelation;
use App\Models\ViewDescription;
use App\Models\PsDescription;
use App\Models\FunctionDescription;
use App\Models\TriggerDescription;
use Inertia\Inertia;

class DashboardController extends Controller
{
    

    public function index(Request $request)
    {
        try {
            

            // Récupérer les permissions pour les passer à la vue
            $permissions = $request->get('user_project_permission');
            
            Log::info('Dashboard - Début chargement', [
                'user_id' => auth()->id(),
                'permissions' => $permissions
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                Log::warning('Dashboard - No database selected');
                
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'No database selected'], 400);
                }
                
                return redirect()->route('projects.index')
                    ->with('warning', 'No database selected. Please open a project first.');
            }

            // Récupérer les informations de la base de données
            $dbInfo = DbDescription::find($dbId);
            if (!$dbInfo) {
                Log::error('Dashboard - Base de données non trouvée', ['db_id' => $dbId]);
                
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Base de données non trouvée'], 404);
                }
                
                return redirect()->route('projects.index')
                    ->with('error', 'Database not found. Please select a valid project.');
            }

            Log::info('Dashboard - Base de données trouvée', [
                'db_id' => $dbId,
                'db_name' => $dbInfo->dbname
            ]);

            // Compter les objets de base de données
            $tablesCount = TableDescription::where('dbid', $dbId)->count();
            $viewsCount = ViewDescription::where('dbid', $dbId)->count();
            $proceduresCount = PsDescription::where('dbid', $dbId)->count();
            $functionsCount = FunctionDescription::where('dbid', $dbId)->count();
            $triggersCount = TriggerDescription::where('dbid', $dbId)->count();

            // Compter les colonnes, clés primaires et étrangères
            $tableIds = TableDescription::where('dbid', $dbId)->pluck('id')->toArray();
            
            $columnsCount = TableStructure::whereIn('id_table', $tableIds)->count();
            $primaryKeysCount = TableStructure::whereIn('id_table', $tableIds)
                ->where('key', 'PK')
                ->count();
            $foreignKeysCount = TableRelation::whereIn('id_table', $tableIds)->count();

            // Compter les objets documentés
            $documentedTablesCount = TableDescription::where('dbid', $dbId)
                ->whereNotNull('description')
                ->whereRaw("length(description) > 0")
                ->count();
                
            $documentedColumnsCount = TableStructure::whereIn('id_table', $tableIds)
                ->whereNotNull('description')
                ->whereRaw("length(description) > 0")
                ->count();
            
            $documentedViewsCount = ViewDescription::where('dbid', $dbId)
                ->whereNotNull('description')
                ->whereRaw("length(description) > 0")
                ->count();
                
            $documentedProceduresCount = PsDescription::where('dbid', $dbId)
                ->whereNotNull('description')
                ->whereRaw("length(description) > 0")
                ->count();
                
            $documentedFunctionsCount = FunctionDescription::where('dbid', $dbId)
                ->whereNotNull('description')
                ->whereRaw("length(description) > 0")
                ->count();
                
            $documentedTriggersCount = TriggerDescription::where('dbid', $dbId)
                ->whereNotNull('description')
                ->whereRaw("length(description) > 0")
                ->count();

            // Trouver les tables les plus référencées
            $mostReferencedTables = DB::table('table_relations')
                ->join('table_description', 'table_relations.referenced_table', '=', 'table_description.tablename')
                ->where('table_description.dbid', $dbId)
                ->select('table_description.id', 'table_description.tablename as name')
                ->selectRaw('count(*) as references_count')
                ->groupBy('table_description.id', 'table_description.tablename')
                ->orderByDesc('references_count')
                ->limit(10)
                ->get();

            // Récupérer les descriptions séparément
            $tableIds = $mostReferencedTables->pluck('id')->toArray();
            $descriptions = TableDescription::whereIn('id', $tableIds)
                ->select('id', 'description')
                ->get()
                ->keyBy('id');

            // Combiner les deux résultats
            $mostReferencedTables = $mostReferencedTables->map(function ($table) use ($descriptions) {
                $description = isset($descriptions[$table->id]) ? $descriptions[$table->id]->description : null;
                return [
                    'id' => $table->id,
                    'name' => $table->name,
                    'references_count' => $table->references_count,
                    'is_documented' => !empty($description)
                ];
            });

            // Obtenir les informations du projet actuel
            $currentProject = session('current_project');
            
            // Construire le tableau de bord avec les permissions
            $dashboardData = [
                'database_name' => $dbInfo->dbname,
                'database_description' => $dbInfo->description,
                'project_name' => $currentProject['name'] ?? 'Unknown Project',
                'project_id' => $currentProject['id'] ?? null,
                'tables_count' => $tablesCount,
                'views_count' => $viewsCount,
                'procedures_count' => $proceduresCount,
                'functions_count' => $functionsCount,
                'triggers_count' => $triggersCount,
                'columns_count' => $columnsCount,
                'primary_keys_count' => $primaryKeysCount,
                'foreign_keys_count' => $foreignKeysCount,
                'documented_tables_count' => $documentedTablesCount,
                'documented_columns_count' => $documentedColumnsCount,
                'documented_views_count' => $documentedViewsCount,
                'documented_procedures_count' => $documentedProceduresCount,
                'documented_functions_count' => $documentedFunctionsCount,
                'documented_triggers_count' => $documentedTriggersCount,
                'most_referenced_tables' => $mostReferencedTables,
                // Ajouter les informations de permissions
                'permissions' => $permissions,
                'user_access_level' => $permissions['level'] ?? 'none'
            ];

            Log::info('Dashboard - Données chargées avec succès', [
                'db_id' => $dbId,
                'tables_count' => $tablesCount,
                'user_permissions' => $permissions['level'] ?? 'none'
            ]);

            // Retourner selon le type de requête
            if ($request->wantsJson()) {
                return response()->json($dashboardData);
            }

            // Pour les requêtes web, retourner la vue Inertia
            return Inertia::render('Dashboard', $dashboardData);

        } catch (\Exception $e) {
            Log::error('Dashboard - Erreur lors du chargement', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'Error loading dashboard: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->route('projects.index')
                ->with('error', 'Error loading dashboard. Please try again.');
        }
    }

    /**
     * Méthode pour mettre à jour la description de la base de données
     * (nécessite des permissions d'écriture)
     */
    public function updateDatabaseDescription(Request $request)
    {
        try {
            // Vérifier les permissions d'écriture
            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to update database description.')) {
                return $error;
            }

            $validated = $request->validate([
                'description' => 'nullable|string|max:1000'
            ]);

            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'No database selected'], 400);
            }

            $dbInfo = DbDescription::find($dbId);
            if (!$dbInfo) {
                return response()->json(['error' => 'Database not found'], 404);
            }

            $dbInfo->update([
                'description' => $validated['description']
            ]);

            Log::info('Dashboard - Description de la base de données mise à jour', [
                'user_id' => auth()->id(),
                'db_id' => $dbId,
                'description_length' => strlen($validated['description'] ?? '')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Database description updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard - Erreur lors de la mise à jour de la description', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error updating database description: ' . $e->getMessage()
            ], 500);
        }
    }
}
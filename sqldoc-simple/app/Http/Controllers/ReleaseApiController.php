<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ReleaseApiController extends Controller
{
    /**
     * Renvoie la liste des versions pour l'interface Vue.js (FILTRÉES PAR PROJET ACTUEL)
     */
    public function index()
    {
        try {
            // Récupérer le projet actuel depuis la session
            $currentProject = session('current_project');
            
            if (!$currentProject || !isset($currentProject['id'])) {
                return response()->json([
                    'error' => 'Aucun projet sélectionné. Veuillez vous connecter à un projet.'
                ], 400);
            }
            
            $currentProjectId = $currentProject['id'];
            
            Log::info('Chargement des releases pour le projet actuel', [
                'project_id' => $currentProjectId,
                'project_name' => $currentProject['name'] ?? 'Unknown'
            ]);

            // Récupérer UNIQUEMENT les versions du projet actuel
            $releases = Release::with(['project' => function($query) {
                    $query->whereNull('deleted_at');
                }])
                ->where('project_id', $currentProjectId) // FILTRAGE PAR PROJET ACTUEL
                ->whereHas('project', function($query) {
                    $query->whereNull('deleted_at');
                })
                ->orderBy('version_number', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($release) {
                    return [
                        'id' => $release->id,
                        'version_number' => $release->version_number,
                        'project_id' => $release->project_id,
                        'project_name' => $release->project ? $release->project->name : 'Projet supprimé',
                        'description' => $release->description ?? '',
                        'column_count' => $this->getColumnCountForRelease($release->id),
                        'created_at' => $release->created_at->format('d/m/Y H:i'),
                        'updated_at' => $release->updated_at->format('d/m/Y H:i')
                    ];
                });

            // Obtenir les versions uniques POUR CE PROJET UNIQUEMENT
            $uniqueVersions = Release::where('project_id', $currentProjectId)
                ->whereHas('project', function($query) {
                    $query->whereNull('deleted_at');
                })
                ->distinct()
                ->orderBy('version_number', 'desc')
                ->pluck('version_number');
                
            // Récupérer UNIQUEMENT le projet actuel pour la liste déroulante
            $projects = Project::where('id', $currentProjectId)
                ->whereNull('deleted_at')
                ->select('id', 'name')
                ->get();

            return response()->json([
                'releases' => $releases,
                'uniqueVersions' => $uniqueVersions,
                'projects' => $projects,
                'currentProject' => [
                    'id' => $currentProjectId,
                    'name' => $currentProject['name'] ?? 'Unknown'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseApiController::index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Erreur lors du chargement des versions: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Compte le nombre de colonnes associées à une release
     */
    private function getColumnCountForRelease($releaseId)
    {
        try {
            return DB::table('table_structure')
                ->where('release_id', $releaseId)
                ->count();
        } catch (\Exception $e) {
            Log::warning('Erreur lors du comptage des colonnes pour la release', [
                'release_id' => $releaseId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Renvoie la liste de toutes les versions du projet actuel (pour les listes déroulantes)
     */
    public function getAllVersions()
    {
        try {
            // Récupérer le projet actuel depuis la session
            $currentProject = session('current_project');
            
            if (!$currentProject || !isset($currentProject['id'])) {
                return response()->json([
                    'error' => 'Aucun projet sélectionné'
                ], 400);
            }
            
            $currentProjectId = $currentProject['id'];

            $versions = Release::select('id', 'version_number', 'project_id')
                ->with(['project' => function($query) {
                    $query->select('id', 'name')->whereNull('deleted_at');
                }])
                ->where('project_id', $currentProjectId) // FILTRAGE PAR PROJET ACTUEL
                ->whereHas('project', function($query) {
                    $query->whereNull('deleted_at');
                })
                ->orderBy('version_number', 'desc')
                ->get()
                ->map(function ($release) {
                    return [
                        'id' => $release->id,
                        'version_number' => $release->version_number,
                        'project_name' => $release->project ? $release->project->name : null,
                        'display_name' => $release->version_number // Pas besoin du nom du projet puisque c'est le même
                    ];
                });

            return response()->json($versions);
            
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseApiController::getAllVersions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Erreur lors du chargement des versions: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Enregistre une nouvelle version (POUR LE PROJET ACTUEL UNIQUEMENT)
     */
    public function store(Request $request)
    {
        try {
            // Récupérer le projet actuel depuis la session
            $currentProject = session('current_project');
            
            if (!$currentProject || !isset($currentProject['id'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Aucun projet sélectionné. Veuillez vous connecter à un projet.'
                ], 400);
            }
            
            $currentProjectId = $currentProject['id'];

            // Valider les données (sans project_id car il sera forcé au projet actuel)
            $validated = $request->validate([
                'version_number' => 'required|string|max:20',
                'description' => 'nullable|string'
            ]);

            // FORCER le project_id au projet actuel
            $validated['project_id'] = $currentProjectId;

            // Vérifier que le projet actuel n'est pas supprimé
            $project = Project::find($currentProjectId);
            if (!$project || $project->trashed()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Le projet actuel n\'existe pas ou a été supprimé.'
                ], 400);
            }

            // Vérifier si ce projet a déjà une version identique
            $existingRelease = Release::where('project_id', $currentProjectId)
                ->where('version_number', $validated['version_number'])
                ->first();

            if ($existingRelease) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ce projet possède déjà cette version.'
                ], 400);
            }

            // Vérifier si c'est la première release du projet
            $isFirstRelease = Release::where('project_id', $currentProjectId)->count() === 0;

            // Créer la nouvelle version
            $release = Release::create($validated);

            // Si c'est la première release, l'assigner à toutes les colonnes du projet
            if ($isFirstRelease) {
                $this->assignReleaseToAllColumns($release->id, $currentProjectId);
                
                Log::info('Première release créée et assignée à toutes les colonnes', [
                    'release_id' => $release->id,
                    'version' => $release->version_number,
                    'project_id' => $currentProjectId,
                    'project_name' => $currentProject['name'] ?? 'Unknown'
                ]);
            } else {
                Log::info('Nouvelle release créée', [
                    'release_id' => $release->id,
                    'version' => $release->version_number,
                    'project_id' => $currentProjectId,
                    'project_name' => $currentProject['name'] ?? 'Unknown'
                ]);
            }

            return response()->json([
                'success' => true,
                'release' => $release,
                'is_first_release' => $isFirstRelease,
                'columns_updated' => $isFirstRelease ? $this->getColumnCountForProject($currentProjectId) : 0
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseApiController::store', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la création de la version: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getColumnCountForProject($projectId)
    {
        try {
            $tables = DB::table('tables')
                ->where('id_project', $projectId)
                ->whereNull('deleted_at')
                ->pluck('id');

            if ($tables->isEmpty()) {
                return 0;
            }

            $totalCount = 0;

            // Compter les colonnes dans table_structure
            $totalCount += DB::table('table_structure')
                ->whereIn('id_table', $tables)
                ->count();

            // Compter les colonnes dans view_column
            $totalCount += DB::table('view_column')
                ->whereIn('id_view', function($query) use ($projectId) {
                    $query->select('id')
                        ->from('views')
                        ->where('id_project', $projectId)
                        ->whereNull('deleted_at');
                })
                ->count();

            return $totalCount;
            
        } catch (\Exception $e) {
            Log::warning('Erreur lors du comptage des colonnes du projet', [
                'project_id' => $projectId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    private function assignReleaseToAllColumns($releaseId, $projectId)
    {
        try {
            $updatedCount = 0;

            // 1. Mettre à jour les colonnes de table_structure
            $tables = DB::table('tables')
                ->where('id_project', $projectId)
                ->whereNull('deleted_at')
                ->pluck('id');

            if ($tables->isNotEmpty()) {
                $count = DB::table('table_structure')
                    ->whereIn('id_table', $tables)
                    ->whereNull('release_id') 
                    ->update([
                        'release_id' => $releaseId,
                        'updated_at' => now()
                    ]);
                
                $updatedCount += $count;
                
                Log::info('table_structure mise à jour', [
                    'count' => $count
                ]);
            }

            // 2. Mettre à jour les colonnes de view_column
            $views = DB::table('views')
                ->where('id_project', $projectId)
                ->whereNull('deleted_at')
                ->pluck('id');

            if ($views->isNotEmpty()) {
                $count = DB::table('view_column')
                    ->whereIn('id_view', $views)
                    ->whereNull('release_id') 
                    ->update([
                        'release_id' => $releaseId,
                        'updated_at' => now()
                    ]);
                
                $updatedCount += $count;
                
                Log::info('view_column mise à jour', [
                    'count' => $count
                ]);
            }

            // 3. Mettre à jour les paramètres de functions si vous avez cette table
            $functions = DB::table('functions')
                ->where('id_project', $projectId)
                ->whereNull('deleted_at')
                ->pluck('id');

            if ($functions->isNotEmpty()) {
                $count = DB::table('function_parameters')
                    ->whereIn('id_function', $functions)
                    ->whereNull('release_id')
                    ->update([
                        'release_id' => $releaseId,
                        'updated_at' => now()
                    ]);
                
                $updatedCount += $count;
                
                Log::info('function_parameters mise à jour', [
                    'count' => $count
                ]);
            }

            // 4. Mettre à jour les paramètres de procedures si vous avez cette table
            $procedures = DB::table('procedures')
                ->where('id_project', $projectId)
                ->whereNull('deleted_at')
                ->pluck('id');

            if ($procedures->isNotEmpty()) {
                $count = DB::table('procedure_parameters')
                    ->whereIn('id_procedure', $procedures)
                    ->whereNull('release_id')
                    ->update([
                        'release_id' => $releaseId,
                        'updated_at' => now()
                    ]);
                
                $updatedCount += $count;
                
                Log::info('procedure_parameters mise à jour', [
                    'count' => $count
                ]);
            }

            Log::info('Colonnes mises à jour avec la nouvelle release', [
                'release_id' => $releaseId,
                'project_id' => $projectId,
                'total_updated_count' => $updatedCount
            ]);

            return $updatedCount;
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'assignation de la release aux colonnes', [
                'release_id' => $releaseId,
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 0;
        }
    }

    /**
     * Met à jour une version existante (UNIQUEMENT SI ELLE APPARTIENT AU PROJET ACTUEL)
     */
    public function update(Request $request, $id)
    {
        try {
            // Récupérer le projet actuel depuis la session
            $currentProject = session('current_project');
            
            if (!$currentProject || !isset($currentProject['id'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Aucun projet sélectionné.'
                ], 400);
            }
            
            $currentProjectId = $currentProject['id'];

            // Valider les données
            $validated = $request->validate([
                'version_number' => 'required|string|max:10',
                'description' => 'nullable|string'
            ]);

            // Récupérer la version existante ET vérifier qu'elle appartient au projet actuel
            $release = Release::where('id', $id)
                ->where('project_id', $currentProjectId) // SÉCURITÉ : vérifier l'appartenance
                ->firstOrFail();

            // Vérifier si la combinaison existe déjà (hors cette version)
            $existingRelease = Release::where('project_id', $currentProjectId)
                ->where('version_number', $validated['version_number'])
                ->where('id', '!=', $id)
                ->first();

            if ($existingRelease) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ce projet possède déjà cette version.'
                ], 400);
            }

            // Mettre à jour la version
            $release->update($validated);

            return response()->json([
                'success' => true,
                'release' => $release
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseApiController::update', [
                'id' => $id,
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la mise à jour de la version: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprime une version (UNIQUEMENT SI ELLE APPARTIENT AU PROJET ACTUEL)
     */
    public function destroy($id)
    {
        try {
            // Récupérer le projet actuel depuis la session
            $currentProject = session('current_project');
            
            if (!$currentProject || !isset($currentProject['id'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Aucun projet sélectionné.'
                ], 400);
            }
            
            $currentProjectId = $currentProject['id'];

            // Récupérer la version ET vérifier qu'elle appartient au projet actuel
            $release = Release::where('id', $id)
                ->where('project_id', $currentProjectId) // SÉCURITÉ : vérifier l'appartenance
                ->firstOrFail();

            // Vérifier si des tables/colonnes utilisent cette version
            $usageCount = DB::table('table_structure')
                ->where('release_id', $id)
                ->count();

            if ($usageCount > 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cette version est utilisée par ' . $usageCount . ' colonnes. Veuillez les mettre à jour avant de supprimer cette version.'
                ], 400);
            }

            // Supprimer la version
            $release->delete();

            return response()->json([
                'success' => true
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseApiController::destroy', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la suppression de la version: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Associe une version à une colonne spécifique (DANS LE PROJET ACTUEL)
     */
    public function assignReleaseToColumn(Request $request)
    {
        try {
            Log::info('Début de assignReleaseToColumn', [
                'request_all' => $request->all()
            ]);

            // Récupérer le projet actuel
            $currentProject = session('current_project');
            if (!$currentProject || !isset($currentProject['id'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Aucun projet sélectionné.'
                ], 400);
            }
            
            $currentProjectId = $currentProject['id'];

            // Valider les données
            $validated = $request->validate([
                'release_id' => 'required|exists:release,id',
                'table_id' => 'required|integer',
                'column_name' => 'required|string'
            ]);

            // VÉRIFIER que la release appartient au projet actuel
            $release = Release::where('id', $validated['release_id'])
                ->where('project_id', $currentProjectId)
                ->first();
                
            if (!$release) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cette version n\'appartient pas au projet actuel.'
                ], 403);
            }

            // Récupérer la structure de la table
            $column = DB::table('table_structure')
                ->where('id_table', $validated['table_id'])
                ->where('column', $validated['column_name'])
                ->first();

            if (!$column) {
                return response()->json([
                    'success' => false,
                    'error' => 'Colonne non trouvée'
                ], 404);
            }

            // Mettre à jour la colonne avec l'ID de version
            $updateResult = DB::table('table_structure')
                ->where('id', $column->id)
                ->update(['release_id' => $validated['release_id']]);

            return response()->json([
                'success' => true,
                'update_result' => $updateResult,
                'column_id' => $column->id,
                'new_release_id' => $validated['release_id']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseApiController::assignReleaseToColumn', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de l\'association de la version à la colonne: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retire l'association d'une version à une colonne
     */
    public function removeReleaseFromColumn(Request $request)
    {
        try {
            // Valider les données
            $validated = $request->validate([
                'table_id' => 'required|integer',
                'column_name' => 'required|string'
            ]);

            // Récupérer la structure de la table
            $column = DB::table('table_structure')
                ->where('id_table', $validated['table_id'])
                ->where('column', $validated['column_name'])
                ->first();

            if (!$column) {
                return response()->json([
                    'success' => false,
                    'error' => 'Colonne non trouvée'
                ], 404);
            }

            // Retirer l'association
            DB::table('table_structure')
                ->where('id', $column->id)
                ->update(['release_id' => null]);

            return response()->json([
                'success' => true
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseApiController::removeReleaseFromColumn', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du retrait de l\'association: ' . $e->getMessage()
            ], 500);
        }
    }
}
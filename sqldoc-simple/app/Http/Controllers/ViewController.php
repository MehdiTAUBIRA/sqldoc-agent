<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ViewDescription;
use App\Models\ViewInformation;
use App\Models\ViewColumn;
use Inertia\Inertia;
use App\Http\Controllers\Traits\HasProjectPermissions;
use App\Models\AuditLog;
use App\Models\Release;
use Illuminate\Support\Facades\Auth;

class ViewController extends Controller
{

    use HasProjectPermissions;

    /**
     * Affiche les détails d'une vue spécifique
     */
    public function details(Request $request, $viewName)
    {
        try {

            if ($error = $this->requirePermission($request, 'read')) {
                return $error;
            }

            $permissions = $request->get('user_project_permission');

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            Log::info('Récupération des détails pour viewName: ' . $viewName . ', dbId: ' . $dbId);
            
            // ✅ NOUVEAU : Récupérer les informations du projet actuel
            $currentProject = session('current_project');
            
            // ✅ NOUVEAU : Déterminer les permissions
            $isOwner = $currentProject['is_owner'] ?? false;
            $accessLevel = $currentProject['access_level'] ?? 'read';
            $canEdit = $isOwner || in_array($accessLevel, ['owner', 'Admin', 'write']);
            
            Log::info('Permissions pour la vue', [
                'view_name' => $viewName,
                'is_owner' => $isOwner,
                'access_level' => $accessLevel,
                'can_edit' => $canEdit
            ]);
            
            if (!$dbId) {
                return Inertia::render('ViewDetails', [
                    'viewName' => $viewName,
                    'viewDetails' => [
                        'description' => '',
                        'columns' => [],
                        'definition' => null,
                        'create_date' => null,
                        'modify_date' => null,
                        'schema_name' => null,
                        // ✅ NOUVEAU : Ajouter les permissions
                        'can_edit' => $canEdit,
                        'is_owner' => $isOwner,
                        'access_level' => $accessLevel
                    ],
                    'availableReleases' => $this->getAvailableReleases(),
                    'permissions' => [
                        'can_edit' => $canEdit,
                        'is_owner' => $isOwner,
                        'access_level' => $accessLevel
                    ],
                    'error' => 'Aucune base de données sélectionnée'
                ]);
            }

            // Récupérer la description de la vue
            $viewDesc = ViewDescription::where('dbid', $dbId)
                ->where('viewname', $viewName)
                ->first();

            if (!$viewDesc) {
                return Inertia::render('ViewDetails', [
                    'viewName' => $viewName,
                    'viewDetails' => [
                        'description' => '',
                        'columns' => [],
                        'definition' => null,
                        'create_date' => null,
                        'modify_date' => null,
                        'schema_name' => null,
                        // ✅ NOUVEAU : Ajouter les permissions
                        'can_edit' => $canEdit,
                        'is_owner' => $isOwner,
                        'access_level' => $accessLevel
                    ],
                    'availableReleases' => $this->getAvailableReleases(),
                    'permissions' => [
                        'can_edit' => $canEdit,
                        'is_owner' => $isOwner,
                        'access_level' => $accessLevel
                    ],
                    'error' => 'Vue non trouvée'
                ]);
            }

            // Récupérer les informations de la vue
            $viewInfo = ViewInformation::where('id_view', $viewDesc->id)->first();
            
            // ✅ NOUVEAU : Récupérer les releases disponibles
            $availableReleases = $this->getAvailableReleases();
            
            // Récupérer les colonnes de la vue
            $columns = ViewColumn::where('id_view', $viewDesc->id)
                ->get()
                ->map(function ($column) use ($availableReleases) {
                    // ✅ NOUVEAU : Ajouter les informations de release pour chaque colonne
                    $releaseInfo = null;
                    if ($column->release_id) {
                        $releaseInfo = collect($availableReleases)->firstWhere('id', $column->release_id);
                    }
                    
                    return [
                        'column_name' => $column->name,
                        'type' => $column->type,
                        'is_nullable' => $column->nullable == 1,
                        'description' => $column->description ?? null,
                        'rangevalues' => $column->rangevalues ?? null, // ✅ NOUVEAU : Ajout rangevalues
                        'max_length' => $column->max_length ?? null,
                        'precision' => $column->precision ?? null,
                        'scale' => $column->scale ?? null,
                        'release_id' => $column->release_id ?? null, // ✅ NOUVEAU : Ajout release_id
                        'release_version' => $releaseInfo ? $releaseInfo['version_number'] : null // ✅ NOUVEAU : Ajout version
                    ];
                });

            return Inertia::render('ViewDetails', [
                'viewName' => $viewName,
                'viewDetails' => [
                    'description' => $viewDesc->description,
                    'columns' => $columns,
                    'definition' => $viewInfo ? $viewInfo->definition : null,
                    'create_date' => $viewInfo ? $viewInfo->creation_date : null,
                    'modify_date' => $viewInfo ? $viewInfo->last_change_date : null,
                    'schema_name' => $viewInfo ? $viewInfo->schema_name : null,
                    // ✅ NOUVEAU : Ajouter les permissions
                    'can_edit' => $canEdit,
                    'is_owner' => $isOwner,
                    'access_level' => $accessLevel
                ],
                'availableReleases' => $availableReleases, // ✅ NOUVEAU : Ajouter les releases
                'permissions' => [
                    'can_edit' => $canEdit,
                    'is_owner' => $isOwner,
                    'access_level' => $accessLevel
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur dans ViewController::details', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // ✅ NOUVEAU : Même en cas d'erreur, inclure les permissions
            $currentProject = session('current_project', []);
            $isOwner = $currentProject['is_owner'] ?? false;
            $accessLevel = $currentProject['access_level'] ?? 'read';
            $canEdit = $isOwner || in_array($accessLevel, ['owner', 'Admin', 'write']);
            
            return Inertia::render('ViewDetails', [
                'viewName' => $viewName,
                'viewDetails' => [
                    'description' => '',
                    'columns' => [],
                    'definition' => null,
                    'create_date' => null,
                    'modify_date' => null,
                    'schema_name' => null,
                    'can_edit' => $canEdit,
                    'is_owner' => $isOwner,
                    'access_level' => $accessLevel
                ],
                'availableReleases' => $this->getAvailableReleases(),
                'permissions' => [
                    'can_edit' => $canEdit,
                    'is_owner' => $isOwner,
                    'access_level' => $accessLevel
                ],
                'error' => 'Erreur lors de la récupération des détails de la vue: ' . $e->getMessage()
            ]);
        }
    }

    private function getAvailableReleases()
    {
        try {
            $currentProject = session('current_project');
            if (!$currentProject || !isset($currentProject['id'])) {
                return [];
            }
            
            // Récupérer les releases du projet actuel
            $releases = Release::where('project_id', $currentProject['id'])
                ->orderBy('version_number', 'desc')
                ->get()
                ->map(function ($release) {
                    return [
                        'id' => $release->id,
                        'version_number' => $release->version_number,
                        'display_name' => $release->version_number . ' - ' . ($release->description ?? 'No description'),
                        'description' => $release->description
                    ];
                });
            
            return $releases->toArray();
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des releases', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Sauvegarde la description de la vue
     */
    public function saveDescription(Request $request, $viewName)
    {
        try {
            // Debug - voir ce qui arrive dans la requête
            Log::info('Données reçues:', [
                'viewName' => $viewName,
                'request_data' => $request->all(),
                'description' => $request->input('description'),
                'session_db_id' => session('current_db_id')
            ]);

            // Valider les données
            $validated = $request->validate([
                'description' => 'nullable|string|max:5000' // Ajout d'une limite
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                Log::error('Aucune base de données dans la session');
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la table
            $viewDesc = ViewDescription::where('dbid', $dbId)
                ->where('viewName', $viewName)
                ->first();

            if (!$viewDesc) {
                Log::error('View not found:', ['dbid' => $dbId, 'viewName' => $viewName]);
                
                // Créer une nouvelle entrée si elle n'existe pas
                $viewDesc = new ViewDescription();
                $viewDesc->dbid = $dbId;
                $viewDesc->viewName = $viewName;
            }

            // Log de l'état avant modification
            Log::info('Avant modification:', [
                'ancien_description' => $viewDesc->description,
                'nouveau_description' => $validated['description']
            ]);

            // Mettre à jour la description de la table
            $viewDesc->description = $validated['description'];
            $result = $viewDesc->save();

            // Log du résultat
            Log::info('Résultat sauvegarde:', [
                'save_result' => $result,
                'description_finale' => $viewDesc->description,
                'view_id' => $viewDesc->id ?? 'nouveau'
            ]);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Description sauvegardée avec succès',
                    'description' => $viewDesc->description,
                    'viewDetails' => [
                        'description' => $viewDesc->description,
                        // Ajoutez d'autres champs si nécessaire
                    ]
                ]);
            } else {
                return response()->json(['error' => 'Échec de la sauvegarde'], 500);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Erreur de validation:', $e->errors());
            return response()->json(['error' => 'Données invalides: ' . implode(', ', $e->errors()['description'] ?? [])], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la sauvegarde:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Erreur lors de la sauvegarde de la description: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sauvegarde la description d'une colonne de la vue
     */
    public function saveColumnDescription(Request $request, $viewName, $columnName)
    {
        try {
            // Valider les données
            $validated = $request->validate([
                'description' => 'nullable|string'
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la vue
            $viewDesc = ViewDescription::where('dbid', $dbId)
                ->where('viewname', $viewName)
                ->first();

            if (!$viewDesc) {
                return response()->json(['error' => 'Vue non trouvée'], 404);
            }

            // Mettre à jour la description de la colonne
            $column = ViewColumn::where('id_view', $viewDesc->id)
                ->where('name', $columnName)
                ->first();

            if (!$column) {
                return response()->json(['error' => 'Colonne non trouvée'], 404);
            }

            $column->description = $validated['description'];
            $column->save();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la sauvegarde de la description de la colonne: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sauvegarde toutes les informations de la vue (descriptions uniquement)
     */
    public function saveAll(Request $request, $viewName)
    {
        try {
            // Valider les données
            $validated = $request->validate([
                'description' => 'nullable|string',
                'columns' => 'required|array',
                'columns.*.column_name' => 'required|string',
                'columns.*.description' => 'nullable|string'
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la vue
            $viewDesc = ViewDescription::where('dbid', $dbId)
                ->where('viewname', $viewName)
                ->first();

            if (!$viewDesc) {
                return response()->json(['error' => 'Vue non trouvée'], 404);
            }

            // Mettre à jour la description de la vue
            $viewDesc->description = $validated['description'];
            $viewDesc->save();

            // Mettre à jour les descriptions des colonnes
            foreach ($validated['columns'] as $columnData) {
                $column = ViewColumn::where('id_view', $viewDesc->id)
                    ->where('name', $columnData['column_name'])
                    ->first();

                if ($column) {
                    $column->description = $columnData['description'];
                    $column->save();
                }
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la sauvegarde de toutes les informations: ' . $e->getMessage()], 500);
        }
    }

    private function logAudit($dbId, $viewId, $columnName, $changeType, $oldData, $newData)
    {
        try {
            $userId = Auth::id() ?? null;
            
            AuditLog::create([
                'user_id' => $userId,
                'db_id' => $dbId,
                'view_id' => $viewId,
                'column_name' => $columnName,
                'change_type' => $changeType,
                'old_data' => $oldData,
                'new_data' => $newData
            ]);
            
            Log::info('Audit log créé', [
                'user_id' => $userId,
                'db_id' => $dbId,
                'view_id' => $viewId,
                'column_name' => $columnName,
                'change_type' => $changeType
            ]);
        } catch (\Exception $e) {
            Log::error('Error while creating Audit log', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function getAuditLogs(Request $request, $viewName, $columnName)
    {
        try {
            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'No database selected'], 400);
            }

            // Récupérer la description de la vue
            $viewDesc = ViewDescription::where('dbid', $dbId)
                ->where('viewname', $viewName)
                ->first();

            if (!$viewDesc) {
                return response()->json(['error' => 'Vue non trouvée'], 404);
            }

            $auditLogs = AuditLog::where('db_id', $dbId)
                ->where('view_id', $viewDesc->id)  
                ->where('column_name', 'like', $columnName . '%') 
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($auditLogs); 

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération des logs d\'audit: ' . $e->getMessage()], 500);
        }
    }

    public function saveStructure(Request $request, $viewName)
    {
        try {
            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to modify table structure.')) {
                return $error;
            }

            // Valider les données
            $validated = $request->validate([
                'description' => 'nullable|string',
                'language' => 'nullable|string|size:2',
                'columns' => 'required|array',
                'columns.*.column' => 'required|string',
                'columns.*.description' => 'nullable|string',
                'columns.*.rangevalues' => 'nullable|string',
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la table
            $viewDesc = ViewDescription::where('dbid', $dbId)
                ->where('viewname', $viewName)
                ->first();

            if (!$viewDesc) {
                return response()->json(['error' => 'Table non trouvée'], 404);
            }

            // Mettre à jour la description de la table avec audit
            if ($viewDesc->description !== $validated['description']) {
                $oldDescription = $viewDesc->description;
                $viewDesc->description = $validated['description'];
                
                // Log de l'audit pour la description de la table
                $this->logAudit(
                    $dbId, 
                    $viewDesc->id, 
                    'view_description', 
                    'update', 
                    $oldDescription, 
                    $validated['description']
                );
            }
            
            // Mise à jour de la langue si elle a changé
            if ($viewDesc->language !== $validated['language']) {
                $oldLanguage = $viewDesc->language;
                $viewDesc->language = $validated['language'];
                
                // Log de l'audit pour la langue
                $this->logAudit(
                    $dbId, 
                    $viewDesc->id, 
                    'table_language', 
                    'update', 
                    $oldLanguage, 
                    $validated['language']
                );
            }
            
            $viewDesc->save();

            // Mettre à jour les descriptions et valeurs possibles des colonnes
            foreach ($validated['name'] as $columnData) {
                $column = ViewColumn::where('id_view', $viewDesc->id)
                    ->where('name', $columnData['name'])
                    ->first();

                if ($column) {
                    // Vérifier si la description a changé
                    if ($column->description !== $columnData['description']) {
                        $oldDescription = $column->description;
                        $column->description = $columnData['description'];
                        
                        // Log de l'audit pour la description de la colonne
                        $this->logAudit(
                            $dbId, 
                            $viewDesc->id, 
                            $columnData['column'] . '_description', 
                            'update', 
                            $oldDescription, 
                            $columnData['description']
                        );
                    }
                    
                    // Vérifier si les valeurs possibles ont changé
                    if ($column->rangevalues !== $columnData['rangevalues']) {
                        $oldRangeValues = $column->rangevalues;
                        $column->rangevalues = $columnData['rangevalues'];
                        
                        // Log de l'audit pour les valeurs possibles
                        $this->logAudit(
                            $dbId, 
                            $viewDesc->id, 
                            $columnData['column'] . '_rangevalues', 
                            'update', 
                            $oldRangeValues, 
                            $columnData['rangevalues']
                        );
                    }
                    
                    $column->save();
                }
            }

            Log::info('view Column updated', [
                'user_id' => auth()->id(),
                'viewname' => $viewName,
                'permissions' => $request->get('user_project_permission')['level'] ?? 'none'
            ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error while saving structure: ' . $e->getMessage()], 500);
        }
    }

    public function updateColumnDescription(Request $request, $viewName, $columnName)
    {
        try {

            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to update column descriptions.')) {
                return $error;
            }

            // Valider les données
            $validated = $request->validate([
                'description' => 'nullable|string'
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'No database selected'], 400);
            }

            // Récupérer la description de la table
            $viewDesc = ViewDescription::where('dbid', $dbId)
                ->where('viewname', $viewName)
                ->first();

            if (!$viewDesc) {
                return response()->json(['error' => 'Table non trouvée'], 404);
            }

            // Mettre à jour la description de la colonne
            $column = ViewColumn::where('id_view', $viewDesc->id)
                ->where('name', $columnName)
                ->first();

            if (!$column) {
                return response()->json(['error' => 'Colonne non trouvée'], 404);
            }

            // Vérifier si la description a changé
            if ($column->description !== $validated['description']) {
                $oldDescription = $column->description;
                $column->description = $validated['description'];
                $column->save();
                
                // Log de l'audit pour la description de la colonne
                $this->logAudit(
                    $dbId, 
                    $viewDesc->id, 
                    $columnName . '_description', 
                    'update', 
                    $oldDescription, 
                    $validated['description']
                );
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error while updating description: ' . $e->getMessage()], 500);
        }
    }

    public function updateColumnRangeValues(Request $request, $viewName, $columnName)
    {
        try {

            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to update possible values.')) {
            return $error;
            }

            // Valider les données
            $validated = $request->validate([
                'rangevalues' => 'nullable|string'
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la table
            $viewDesc = ViewDescription::where('dbid', $dbId)
                ->where('viewname', $viewName)
                ->first();

            if (!$viewDesc) {
                return response()->json(['error' => 'View not found'], 404);
            }

            // Mettre à jour les valeurs possibles de la colonne
            $column = ViewColumn::where('id_view', $viewDesc->id)
                ->where('name', $columnName)
                ->first();

            if (!$column) {
                return response()->json(['error' => 'Column not found'], 404);
            }

            // Vérifier si les valeurs possibles ont changé
            if ($column->rangevalues !== $validated['rangevalues']) {
                $oldRangeValues = $column->rangevalues;
                $column->rangevalues = $validated['rangevalues'];
                $column->save();
                
                // Log pour déboguer le résultat de la sauvegarde
                Log::info('Résultat de la sauvegarde', [
                    'name' => $columnName,
                    'rangevalues' => $column->rangevalues,
                    'saveResult' => $column 
                ]);
                
                // Log de l'audit pour les valeurs possibles
                $this->logAudit(
                    $dbId, 
                    $viewDesc->id, 
                    $columnName . '_rangevalues', 
                    'update', 
                    $oldRangeValues, 
                    $validated['rangevalues']
                );
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Error while updating range values', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Error while updating range values: ' . $e->getMessage()], 500);
        }
    }

    public function updateColumnRelease(Request $request, $viewName, $columnName)
    {
        try {

            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to update column release.')) {
            return $error;
            }

            // Valider les données
            $validated = $request->validate([
                'release_id' => 'nullable|exists:release,id'
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'No database selected'], 400);
            }

            // Récupérer la description de la table
            $viewDesc = ViewDescription::where('dbid', $dbId)
                ->where('viewname', $viewName)
                ->first();

            if (!$viewDesc) {
                return response()->json(['error' => 'View not found'], 404);
            }

            // Récupérer la colonne
            $column = ViewColumn::where('id_view', $viewDesc->id)
                ->where('name', $columnName)
                ->first();

            if (!$column) {
                return response()->json(['error' => 'Column not found'], 404);
            }

            // Vérifier si la version a changé
            $newReleaseId = $validated['release_id'];
            if ($column->release_id != $newReleaseId) {
                $oldReleaseId = $column->release_id;
                
                // Récupérer les informations des versions pour le log
                $oldReleaseInfo = null;
                $newReleaseInfo = null;
                
                if ($oldReleaseId) {
                    $oldRelease = Release::find($oldReleaseId);
                    $oldReleaseInfo = $oldRelease ? $oldRelease->version_number : 'Release deleted';
                }
                
                if ($newReleaseId) {
                    $newRelease = Release::find($newReleaseId);
                    $newReleaseInfo = $newRelease ? $newRelease->version_number : 'Release unknow';
                }
                
                // Mettre à jour la colonne
                $column->release_id = $newReleaseId;
                $column->save();
                
                // Log de l'audit pour la version
                $this->logAudit(
                    $dbId, 
                    $viewDesc->id, 
                    $columnName . '_release', 
                    'update', 
                    $oldReleaseInfo ?: 'null', 
                    $newReleaseInfo ?: 'null'
                );
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Error while updating release column', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Error while updating release: ' . $e->getMessage()], 500);
        }
    }

    public function assignReleaseToColumn(Request $request)
    {
        try {
            Log::info('Début de assignReleaseToColumn', [
                'request_all' => $request->all()
            ]);

            // Valider les données
            $validated = $request->validate([
                'release_id' => 'nullable|exists:release,id', // nullable pour permettre la suppression
                'view_id' => 'required|integer',
                'name' => 'required|string'
            ]);

            Log::info('Données validées', [
                'validated' => $validated
            ]);

            // Récupérer les informations de la table
            $viewDesc = DB::table('view_description')
                ->where('id', $validated['view_id'])
                ->first();

            if (!$viewDesc) {
                return response()->json([
                    'success' => false,
                    'error' => 'View not found'
                ], 404);
            }

            // Appeler la méthode du TableController pour la mise à jour avec audit
            $ViewController = new \App\Http\Controllers\ViewController();
            
            // Créer une nouvelle requête avec les bonnes données
            $updateRequest = new Request([
                'release_id' => $validated['release_id']
            ]);

            // Appeler la méthode qui gère l'audit
            $response = $ViewController->updateColumnRelease(
                $updateRequest, 
                $viewDesc->viewname, 
                $validated['name']
            );

            // Retourner la réponse de la méthode d'audit
            return $response;

        } catch (\Exception $e) {
            Log::error('Error in ReleaseApiController::assignReleaseToColumn', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error while associating release to column: ' . $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\HasProjectPermissions;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\FunctionDescription;
use App\Models\FuncInformation;
use App\Models\FuncParameter;
use App\Models\Release;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class FunctionController extends Controller
{

    use HasProjectPermissions;
    /**
     * Récupère les détails d'une fonction
     */
    public function apiDetails(Request $request, $functionName)
    {
        try {

            if ($error = $this->requirePermission($request, 'read')) {
            return $error;
            }

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            Log::info('API - Récupération des détails pour functionName: ' . $functionName . ', dbId: ' . $dbId);
            
            if (!$dbId) {
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la fonction
            $functionDesc = FunctionDescription::where('dbid', $dbId)
                ->where('functionname', $functionName)
                ->first();

            if (!$functionDesc) {
                return response()->json(['error' => 'Fonction non trouvée'], 404);
            }

            // Récupérer les informations de la fonction
            $functionInfo = FuncInformation::where('id_func', $functionDesc->id)->first();
            
            // Récupérer les paramètres de la fonction
            $parameters = FuncParameter::where('id_func', $functionDesc->id)
                ->get()
                ->map(function ($param) {
                    return [
                        'parameter_id' => $param->id,
                        'parameter_name' => $param->name,
                        'data_type' => $param->type,
                        'is_output' => $param->output === 'OUTPUT',
                        'description' => $param->description ?? null,
                        'rangevalue' => $param->range_value, 
                        'release_id' => $param->release_id,
                    ];
                });

            // Construire la réponse
            return response()->json([
                'name' => $functionDesc->functionname,
                'description' => $functionDesc->description,
                'function_type' => $functionInfo ? $functionInfo->type : null,
                'return_type' => $functionInfo ? $functionInfo->return_type : null,
                'create_date' => $functionInfo ? $functionInfo->creation_date : null,
                'modify_date' => $functionInfo ? $functionInfo->last_change_date : null,
                'parameters' => $parameters,
                'definition' => $functionInfo && $functionInfo->definition ? $functionInfo->definition : null
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur dans FunctionController::apiDetails', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Erreur lors de la récupération des détails de la fonction: ' . $e->getMessage()], 500);
        }
    }

// méthode pour le rendu Inertia
    public function details(Request $request, $functionName)
    {
        try {

            if ($error = $this->requirePermission($request, 'read')) {
            return $error;
            }

            // AJOUT MINIMAL : Récupérer les permissions
            $currentProject = session('current_project', []);
            $isOwner = $currentProject['is_owner'] ?? false;
            $accessLevel = $currentProject['access_level'] ?? 'read';
            $canEdit = $isOwner || in_array($accessLevel, ['owner', 'Admin', 'write']);

            Log::info('🔍 PERMISSIONS DEBUG', [
                'is_owner' => $isOwner,
                'access_level' => $accessLevel,
                'can_edit' => $canEdit
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            Log::info('Récupération des détails pour functionName: ' . $functionName . ', dbId: ' . $dbId);
            
            if (!$dbId) {
                return Inertia::render('FunctionDetails', [
                    'functionName' => $functionName,
                    'functionDetails' => [
                        'name' => $functionName,
                        'description' => '',
                        'function_type' => null,
                        'return_type' => null,
                        'create_date' => null,
                        'modify_date' => null,
                        'parameters' => [],
                        'definition' => null,
                        'can_edit' => $canEdit,
                        'is_owner' => $isOwner,
                    ],
                    'permissions' => [
                        'can_edit' => $canEdit,
                        'is_owner' => $isOwner,
                    ],
                    'error' => 'Aucune base de données sélectionnée'
                ]);
            }

            // Récupérer la description de la fonction
            $functionDesc = FunctionDescription::where('dbid', $dbId)
                ->where('functionname', $functionName)
                ->first();

            if (!$functionDesc) {
                return Inertia::render('FunctionDetails', [
                    'functionName' => $functionName,
                    'functionDetails' => [
                        'name' => $functionName,
                        'description' => '',
                        'function_type' => null,
                        'return_type' => null,
                        'create_date' => null,
                        'modify_date' => null,
                        'parameters' => [],
                        'definition' => null,
                        'can_edit' => $canEdit,
                        'is_owner' => $isOwner,
                    ],
                    'permissions' => [
                        'can_edit' => $canEdit,
                        'is_owner' => $isOwner,
                    ],
                    'error' => 'Fonction non trouvée'
                ]);
            }

            // Récupérer les informations de la fonction
            $functionInfo = FuncInformation::where('id_func', $functionDesc->id)->first();
            
            // Récupérer les paramètres de la fonction
            $parameters = FuncParameter::where('id_func', $functionDesc->id)
                ->get()
                ->map(function ($param) {
                    return [
                        'parameter_id' => $param->id,
                        'parameter_name' => $param->name,
                        'data_type' => $param->type,
                        'is_output' => $param->output === 'OUTPUT',
                        'description' => $param->description ?? null,
                        'rangevalue' => $param->range_value, 
                        'release_id' => $param->release_id,
                    ];
                });

            return Inertia::render('FunctionDetails', [
                'functionName' => $functionName,
                'functionDetails' => [
                    'name' => $functionDesc->functionname,
                    'description' => $functionDesc->description,
                    'function_type' => $functionInfo ? $functionInfo->type : null,
                    'return_type' => $functionInfo ? $functionInfo->return_type : null,
                    'create_date' => $functionInfo ? $functionInfo->creation_date : null,
                    'modify_date' => $functionInfo ? $functionInfo->last_change_date : null,
                    'parameters' => $parameters,
                    'definition' => $functionInfo && $functionInfo->definition ? $functionInfo->definition : null,
                    'can_edit' => $canEdit,
                    'is_owner' => $isOwner,
                ],
                'availableReleases' => $this->getAvailableReleases(),
                'permissions' => [
                    'can_edit' => $canEdit,
                    'is_owner' => $isOwner,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur dans FunctionController::details', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Inertia::render('FunctionDetails', [
                'functionName' => $functionName,
                'functionDetails' => [
                    'name' => $functionName,
                    'description' => '',
                    'function_type' => null,
                    'return_type' => null,
                    'create_date' => null,
                    'modify_date' => null,
                    'parameters' => [],
                    'definition' => null,
                    'can_edit' => $canEdit,
                    'is_owner' => $isOwner,
                ],
                'availableReleases' => $this->getAvailableReleases(),
                'permissions' => [
                    'can_edit' => $canEdit,
                    'is_owner' => $isOwner,
                ],
                'error' => 'Erreur lors de la récupération des détails de la fonction: ' . $e->getMessage()
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
     * Sauvegarde la description d'une fonction
     */
    public function saveDescription(Request $request, $functionName)
    {
        try {

            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to update functions.')) {
            return $error;
            }

            // Valider les données
            $validated = $request->validate([
                'description' => 'nullable|string'
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la fonction
            $functionDesc = FunctionDescription::where('dbid', $dbId)
                ->where('functionname', $functionName)
                ->first();

            if (!$functionDesc) {
                return response()->json(['error' => 'Fonction non trouvée'], 404);
            }

            // Mettre à jour la description
            $functionDesc->description = $validated['description'];
            $functionDesc->save();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la sauvegarde de la description: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sauvegarde la description d'un paramètre de fonction
     */
    public function saveColumnDescription(Request $request, $functionName, $columnName)
    {
        try {
            // Valider les données
            $validated = $request->validate([
                'description' => 'nullable|string'
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'No database selected'], 400);
            }

            // Récupérer la description de la function
            $functionDesc = FunctionDescription::where('dbid', $dbId)
                ->where('functionname', $functionName)
                ->first();

            if (!$functionDesc) {
                return response()->json(['error' => 'Function not found'], 404);
            }

            // Mettre à jour la description de la colonne
            $column = FuncParameter::where('id_ps', $functionDesc->id)
                ->where('name', $columnName)
                ->first();

            if (!$column) {
                return response()->json(['error' => 'Column not found'], 404);
            }

            $column->description = $validated['description'];
            $column->save();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la sauvegarde de la description de la colonne: ' . $e->getMessage()], 500);
        }
    }

    private function logAudit($dbId, $fcId, $columnName, $changeType, $oldData, $newData)
{
    try {
        $userId = auth()->id() ?? null;
        
        AuditLog::create([
            'user_id' => $userId,
            'db_id' => $dbId,
            'fc_id' => $fcId,
            'column_name' => $columnName,
            'change_type' => $changeType,
            'old_data' => $oldData,
            'new_data' => $newData
        ]);
        
        Log::info('Audit log créé pour fonction', [
            'user_id' => $userId,
            'db_id' => $dbId,
            'fc_id' => $fcId,
            'column_name' => $columnName,
            'change_type' => $changeType
        ]);
    } catch (\Exception $e) {
        Log::error('Erreur lors de la création de l\'audit log', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

    public function getAuditLogs(Request $request, $functionName, $columnName)
    {
        try {
            Log::info('Récupération audit logs', [
                'functionname' => $functionName,
                'column_name' => $columnName,
                'user_id' => auth()->id()
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                Log::warning('Aucune base de données sélectionnée dans la session');
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la procedure
            $funcDesc = FunctionDescription::where('dbid', $dbId)
                ->where('functionName', $functionName)
                ->first();

            if (!$funcDesc) {
                Log::warning('procedure non trouvée', [
                    'functionName' => $functionName,
                    'db_id' => $dbId
                ]);
                return response()->json(['error' => 'function not found'], 404);
            }

            Log::info('function found', [
                'fc_id' => $funcDesc->id,
                'function' => $funcDesc->functionname
            ]);

            // Récupérer les logs d'audit liés à cette colonne
            $auditLogs = AuditLog::where('db_id', $dbId)
                ->where('fc_id', $funcDesc->id)
                ->where('column_name', 'like', $columnName . '%')
                ->with('user:id,name')
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('Audit logs récupérés', [
                'count' => $auditLogs->count(),
                'logs' => $auditLogs->toArray()
            ]);

            // Formatter les données pour le frontend
            $formattedLogs = $auditLogs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'created_at' => $log->created_at,
                    'user' => [
                        'id' => $log->user->id ?? null,
                        'name' => $log->user->name ?? 'User deleted'
                    ],
                    'change_type' => $log->change_type ?? 'update',
                    'old_data' => $log->old_data,
                    'new_data' => $log->new_data,
                    'column_name' => $log->column_name
                ];
            });

            return response()->json($formattedLogs);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des logs d\'audit', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'functionname' => $functionName,
                'column_name' => $columnName
            ]);
            
            return response()->json([
                'error' => 'Erreur lors de la récupération des logs d\'audit: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateColumnRangeValues(Request $request, $functionName, $columnName)
    {
        try {

            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to update possible values.')) {
            return $error;
            }

            // Valider les données
            $validated = $request->validate([
                'default_value' => 'nullable|string'
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'No database selected'], 400);
            }

            // Récupérer la description de la table
            $functionDesc = FunctionDescription::where('dbid', $dbId)
                ->where('functionname', $functionName)
                ->first();

            if (!$functionDesc) {
                return response()->json(['error' => 'Procedure not found'], 404);
            }

            // Mettre à jour les valeurs possibles de la colonne
            $column = FuncParameter::where('id_func', $functionDesc->id)
                ->where('name', $columnName)
                ->first();

            if (!$column) {
                return response()->json(['error' => 'Column not found'], 404);
            }

            // Vérifier si les valeurs possibles ont changé
            if ($column->range_value !== $validated['range_value']) {
                $oldRangeValue = $column->range_value;
                $column->range_value = $validated['range_value'];
                $column->save();
                
                // Log pour déboguer le résultat de la sauvegarde
                Log::info('Résultat de la sauvegarde', [
                    'column' => $columnName,
                    'range_value' => $column->range_value,
                    'saveResult' => $column 
                ]);
                
                //Log de l'audit pour les valeurs possibles
                $this->logAudit(
                    $dbId, 
                    $functionDesc->id, 
                    $columnName . 'range_value', 
                    'update', 
                    $oldRangeValue, 
                    $validated['range_value']
                );
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour des valeurs possibles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Erreur lors de la mise à jour des valeurs possibles: ' . $e->getMessage()], 500);
        }
    }

    public function updateColumnDescription(Request $request, $functionName, $columnName)
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

            // Récupérer la description de la procedure
            $functionDesc = FunctionDescription::where('dbid', $dbId)
                ->where('functionname', $functionName)
                ->first();

            if (!$functionDesc) {
                return response()->json(['error' => 'Function not found'], 404);
            }

            // Mettre à jour la description de la colonne
            $column = FuncParameter::where('id_func', $functionDesc->id)
                ->where('name', $columnName)
                ->first();

            if (!$column) {
                return response()->json(['error' => 'Column not found'], 404);
            }

            // Vérifier si la description a changé
            if ($column->description !== $validated['description']) {
                $oldDescription = $column->description;
                $column->description = $validated['description'];
                $column->save();
                
                // Log de l'audit pour la description de la colonne
                $this->logAudit(
                    $dbId, 
                    $functionDesc->id, 
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

    public function updateColumnRelease(Request $request, $functionName, $columnName)
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

            // Récupérer la description de la procedure
            $functionDesc = FunctionDescription::where('dbid', $dbId)
                ->where('functionname', $functionName)
                ->first();

            if (!$functionDesc) {
                return response()->json(['error' => 'Function not found'], 404);
            }

            // Récupérer la colonne
            $column = FuncParameter::where('id_func', $functionDesc->id)
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
                    $functionDesc->id, 
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
                'fc_id' => 'required|integer',
                'name' => 'required|string'
            ]);

            Log::info('Données validées', [
                'validated' => $validated
            ]);

            // Récupérer les informations de la table
            $functionDesc = DB::table('function_description')
                ->where('id', $validated['fc_id'])
                ->first();

            if (!$functionDesc) {
                return response()->json([
                    'success' => false,
                    'error' => 'Procedure not found'
                ], 404);
            }

            // Appeler la méthode du TableController pour la mise à jour avec audit
            $FunctionController = new \App\Http\Controllers\FunctionController();
            
            // Créer une nouvelle requête avec les bonnes données
            $updateRequest = new Request([
                'release_id' => $validated['release_id']
            ]);

            // Appeler la méthode qui gère l'audit
            $response = $FunctionController->updateColumnRelease(
                $updateRequest, 
                $functionDesc->functionname, 
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

    public function updateDescription(Request $request, $functionName)
{
    try {
        if ($error = $this->requirePermission($request, 'write')) {
            return $error;
        }

        $request->validate([
            'description' => 'nullable|string|max:2000'
        ]);

        $dbId = session('current_db_id');
        if (!$dbId) {
            return back()->withErrors(['error' => 'Aucune base de données sélectionnée']);
        }

        // Récupérer la description de la fonction
        $functionDesc = FunctionDescription::where('dbid', $dbId)
            ->where('functionname', $functionName)
            ->first();

        if (!$functionDesc) {
            return back()->withErrors(['error' => 'Fonction non trouvée']);
        }

        // Vérifier les permissions
        $currentProject = session('current_project', []);
        $canEdit = $currentProject['is_owner'] ?? false;
        
        if (!$canEdit) {
            return back()->withErrors(['error' => 'Permissions insuffisantes']);
        }

        // Mettre à jour la description
        $oldDescription = $functionDesc->description;
        $functionDesc->description = $request->input('description');
        $functionDesc->save();

        // Log de l'audit
        $this->logAudit(
            $dbId, 
            $functionDesc->id, 
            'description', 
            'update', 
            $oldDescription, 
            $request->input('description')
        );

        return back()->with('success', 'Description de la fonction mise à jour avec succès');

    } catch (\Exception $e) {
        Log::error('Erreur lors de la mise à jour de la description de la fonction', [
            'function_name' => $functionName,
            'error' => $e->getMessage()
        ]);
        
        return back()->withErrors(['error' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()]);
    }
}

/**
 * Mise à jour de la description d'un paramètre (pour Inertia)
 */
public function updateParameterDescription(Request $request, $functionName, $parameterId)
{
    try {
        if ($error = $this->requirePermission($request, 'write')) {
            return $error;
        }

        $request->validate([
            'description' => 'nullable|string|max:2000'
        ]);

        $dbId = session('current_db_id');
        if (!$dbId) {
            return back()->withErrors(['error' => 'Aucune base de données sélectionnée']);
        }

        // Récupérer la fonction
        $functionDesc = FunctionDescription::where('dbid', $dbId)
            ->where('functionname', $functionName)
            ->first();

        if (!$functionDesc) {
            return back()->withErrors(['error' => 'Fonction non trouvée']);
        }

        // Récupérer le paramètre
        $parameter = FuncParameter::where('id_func', $functionDesc->id)
            ->where('id', $parameterId)
            ->first();

        if (!$parameter) {
            return back()->withErrors(['error' => 'Paramètre non trouvé']);
        }

        // Vérifier les permissions
        $currentProject = session('current_project', []);
        $canEdit = $currentProject['is_owner'] ?? false;
        
        if (!$canEdit) {
            return back()->withErrors(['error' => 'Permissions insuffisantes']);
        }

        // Sauvegarder l'ancienne valeur pour l'audit
        $oldDescription = $parameter->description;

        // Mettre à jour la description
        $parameter->description = $request->input('description');
        $parameter->save();

        // Log de l'audit
        $this->logAudit(
            $dbId, 
            $functionDesc->id, 
            $parameter->name . '_description', 
            'update', 
            $oldDescription, 
            $request->input('description')
        );

        return back()->with('success', 'Description du paramètre mise à jour avec succès');

    } catch (\Exception $e) {
        Log::error('Erreur lors de la mise à jour de la description du paramètre', [
            'function_name' => $functionName,
            'parameter_id' => $parameterId,
            'error' => $e->getMessage()
        ]);
        
        return back()->withErrors(['error' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()]);
    }
}


// ✅ AJOUTEZ aussi ces méthodes pour Range Values et Release (versions axios)
public function updateParameterRangeValues(Request $request, $functionName, $parameterName)
{
    try {
        if ($error = $this->requirePermission($request, 'write')) {
            return response()->json(['error' => 'Permissions insuffisantes'], 403);
        }

        $request->validate([
            'rangevalues' => 'nullable|string|max:2000'
        ]);

        $dbId = session('current_db_id');
        if (!$dbId) {
            return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
        }

        $functionDesc = FunctionDescription::where('dbid', $dbId)
            ->where('functionname', $functionName)
            ->first();

        if (!$functionDesc) {
            return response()->json(['error' => 'Fonction non trouvée'], 404);
        }

        $parameter = FuncParameter::where('id_func', $functionDesc->id)
            ->where('name', $parameterName)
            ->first();

        if (!$parameter) {
            return response()->json(['error' => 'Paramètre non trouvé'], 404);
        }

        $oldRangeValues = $parameter->range_value;
        $parameter->range_value = $request->input('rangevalues');
        $parameter->save();

        // Log de l'audit
        $this->logAudit(
            $dbId, 
            $functionDesc->id, 
            $parameterName . '_rangevalues', 
            'update', 
            $oldRangeValues, 
            $request->input('rangevalues')
        );

        return response()->json(['success' => true]);

    } catch (\Exception $e) {
        Log::error('Erreur lors de la mise à jour des range values', [
            'function_name' => $functionName,
            'parameter_name' => $parameterName,
            'error' => $e->getMessage()
        ]);
        
        return response()->json(['error' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()], 500);
    }
}

public function updateParameterRelease(Request $request, $functionName, $parameterName)
{
    try {
        if ($error = $this->requirePermission($request, 'write')) {
            return response()->json(['error' => 'Permissions insuffisantes'], 403);
        }

        $request->validate([
            'release_id' => 'nullable|exists:release,id'
        ]);

        $dbId = session('current_db_id');
        if (!$dbId) {
            return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
        }

        $functionDesc = FunctionDescription::where('dbid', $dbId)
            ->where('functionname', $functionName)
            ->first();

        if (!$functionDesc) {
            return response()->json(['error' => 'Fonction non trouvée'], 404);
        }

        $parameter = FuncParameter::where('id_func', $functionDesc->id)
            ->where('name', $parameterName)
            ->first();

        if (!$parameter) {
            return response()->json(['error' => 'Paramètre non trouvé'], 404);
        }

        $oldReleaseId = $parameter->release_id;
        $newReleaseId = $request->input('release_id');
        
        $parameter->release_id = $newReleaseId;
        $parameter->save();

        // Log de l'audit
        $this->logAudit(
            $dbId, 
            $functionDesc->id, 
            $parameterName . '_release', 
            'update', 
            $oldReleaseId, 
            $newReleaseId
        );

        return response()->json(['success' => true]);

    } catch (\Exception $e) {
        Log::error('Erreur lors de la mise à jour de la release', [
            'function_name' => $functionName,
            'parameter_name' => $parameterName,
            'error' => $e->getMessage()
        ]);
        
        return response()->json(['error' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()], 500);
    }
}
}
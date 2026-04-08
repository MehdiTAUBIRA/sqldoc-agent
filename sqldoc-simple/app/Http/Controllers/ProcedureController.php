<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\HasProjectPermissions;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use App\Models\PsDescription;
use App\Models\PsInformation;
use App\Models\PsParameter;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class ProcedureController extends Controller
{
    use HasProjectPermissions;

    /**
     * Récupère les détails d'une procédure stockée
     */
    public function apiDetails(Request $request, $procedureName)
{
    try {

        if ($error = $this->requirePermission($request, 'read')) {
            return $error;
        }

        // Obtenir l'ID de la base de données actuelle depuis la session
        $dbId = session('current_db_id');
        Log::info('API - Récupération des détails pour procedureName: ' . $procedureName . ', dbId: ' . $dbId);
        
        if (!$dbId) {
            return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
        }

        // Récupérer la description de la procédure
        $procedureDesc = PsDescription::where('dbid', $dbId)
            ->where('psname', $procedureName)
            ->first();

        if (!$procedureDesc) {
            return response()->json(['error' => 'Procédure stockée non trouvée'], 404);
        }

        // Récupérer les informations de la procédure
        $procedureInfo = PsInformation::where('id_ps', $procedureDesc->id)->first();
        
        // Récupérer les paramètres de la procédure
        $parameters = PsParameter::where('id_ps', $procedureDesc->id)
            ->get()
            ->map(function ($param) {
                return [
                    'parameter_id' => $param->id, // Assurez-vous d'envoyer l'ID pour les éditions
                    'parameter_name' => $param->name,
                    'data_type' => $param->type,
                    'is_output' => $param->type === 'OUTPUT',
                    'default_value' => $param->default_value, 
                    'description' => $param->description,
                    'rangevalues' => $param->default_value, 
                    'release_id' => $param->release_id, 
                ];
            });

        // Construire la réponse
        return response()->json([
            'name' => $procedureDesc->psname,
            'description' => $procedureDesc->description,
            'schema' => $procedureInfo ? $procedureInfo->schema : null,
            'create_date' => $procedureInfo ? $procedureInfo->creation_date : null,
            'modify_date' => $procedureInfo ? $procedureInfo->last_change_date : null,
            'parameters' => $parameters,
            'definition' => $procedureInfo ? $procedureInfo->definition : null
        ]);

    } catch (\Exception $e) {
        Log::error('Erreur dans ProcedureController::apiDetails', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'error' => 'Erreur lors de la récupération des détails de la procédure stockée: ' . $e->getMessage()
        ], 500);
    }
}

// méthode pour le rendu Inertia
    public function details(Request $request, $procedureName)
    {
        try {

            if ($error = $this->requirePermission($request, 'read')) {
                return $error;
            }

            // AJOUT MINIMAL : Récupérer les permissions
            $currentProject = session('current_project', []);
            $isOwner = $currentProject['is_owner'] ?? false;
            $accessLevel = $currentProject['access_level'] ?? 'read';
            //$canEdit = $isOwner || in_array($accessLevel, ['owner', 'Admin', 'write']);
             $canEdit = false;


            Log::info('🔍 PERMISSIONS DEBUG', [
                'is_owner' => $isOwner,
                'access_level' => $accessLevel,
                'can_edit' => $canEdit
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            Log::info('Récupération des détails pour procedureName: ' . $procedureName . ', dbId: ' . $dbId);
            
            if (!$dbId) {
                return Inertia::render('ProcedureDetails', [
                    'procedureName' => $procedureName,
                    'procedureDetails' => [
                        'name' => $procedureName,
                        'description' => '',
                        'schema' => null,
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

            // Récupérer la description de la procédure
            $procedureDesc = PsDescription::where('dbid', $dbId)
                ->where('psname', $procedureName)
                ->first();

            if (!$procedureDesc) {
                return Inertia::render('ProcedureDetails', [
                    'procedureName' => $procedureName,
                    'procedureDetails' => [
                        'name' => $procedureName,
                        'description' => '',
                        'schema' => null,
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
                    'error' => 'Procédure stockée non trouvée'
                ]);
            }

            // Récupérer les informations de la procédure
            $procedureInfo = PsInformation::where('id_ps', $procedureDesc->id)->first();
            
            // Récupérer les paramètres de la procédure
            $parameters = PsParameter::where('id_ps', $procedureDesc->id)
                ->get()
                ->map(function ($param) {
                    return [
                        'parameter_id' => $param->id,
                        'parameter_name' => $param->name,
                        'data_type' => $param->type,
                        'is_output' => $param->type === 'OUTPUT',
                        'description' => $param->description,
                        'rangevalues' => $param->default_value, 
                        'release_id' => $param->release_id, 
                    ];
                });

            Log::info('🔍 PROCEDURE DATA DEBUG', [
                'procedure_name' => $procedureName,
                'parameters_count' => $parameters->count(),
                'has_procedure_info' => !!$procedureInfo
            ]);

            return Inertia::render('ProcedureDetails', [
                'procedureName' => $procedureName,
                'procedureDetails' => [
                    'name' => $procedureDesc->psname,
                    'description' => $procedureDesc->description,
                    'schema' => $procedureInfo ? $procedureInfo->schema : null,
                    'create_date' => $procedureInfo ? $procedureInfo->creation_date : null,
                    'modify_date' => $procedureInfo ? $procedureInfo->last_change_date : null,
                    'parameters' => $parameters,
                    'definition' => $procedureInfo ? $procedureInfo->definition : null,
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
            Log::error('Erreur dans ProcedureController::details', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // ✅ AJOUT : Permissions même en cas d'erreur
            $currentProject = session('current_project', []);
            $isOwner = $currentProject['is_owner'] ?? false;
            $canEdit = $isOwner;
            
            return Inertia::render('ProcedureDetails', [
                'procedureName' => $procedureName,
                'procedureDetails' => [
                    'name' => $procedureName,
                    'description' => '',
                    'schema' => null,
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
                'error' => 'Erreur lors de la récupération des détails de la procédure stockée: ' . $e->getMessage()
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
            
            $releases = \App\Models\Release::where('project_id', $currentProject['id'])
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
     * Sauvegarde la description d'une procédure stockée
     */
    public function saveDescription(Request $request, $procedureName)
    {
        try {

            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to update procedures.')) {
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

            // Récupérer la description de la procédure
            $procedureDesc = PsDescription::where('dbid', $dbId)
                ->where('psname', $procedureName)
                ->first();

            if (!$procedureDesc) {
                return response()->json(['error' => 'Procédure stockée non trouvée'], 404);
            }

            // Mettre à jour la description
            $procedureDesc->description = $validated['description'];
            $procedureDesc->save();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la sauvegarde de la description: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sauvegarde la description d'un paramètre de procédure
     */
    public function saveColumnDescription(Request $request, $procedureName, $columnName)
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

            // Récupérer la description de la Procedure
            $procedureDesc = PsDescription::where('dbid', $dbId)
                ->where('psname', $procedureName)
                ->first();

            if (!$procedureDesc) {
                return response()->json(['error' => 'Procedure non trouvée'], 404);
            }

            // Mettre à jour la description de la colonne
            $column = PsParameter::where('id_ps', $procedureDesc->id)
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

    private function logAudit($dbId, $psId, $columnName, $changeType, $oldData, $newData)
    {
        try {
            $userId = Auth::id() ?? null;
            
            AuditLog::create([
                'user_id' => $userId,
                'db_id' => $dbId,
                'ps_id' => $psId,
                'column_name' => $columnName,
                'change_type' => $changeType,
                'old_data' => $oldData,
                'new_data' => $newData
            ]);
            
            Log::info('Audit log créé', [
                'user_id' => $userId,
                'db_id' => $dbId,
                'ps_id' => $psId,
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

    public function getAuditLogs(Request $request, $psName, $columnName)
    {
        try {
            Log::info('Récupération audit logs', [
                'psname' => $psName,
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
            $psDesc = PsDescription::where('dbid', $dbId)
                ->where('psname', $psName)
                ->first();

            if (!$psDesc) {
                Log::warning('procedure non trouvée', [
                    'psname' => $psName,
                    'db_id' => $dbId
                ]);
                return response()->json(['error' => 'procedure non trouvée'], 404);
            }

            Log::info('procedure trouvée', [
                'ps_id' => $psDesc->id,
                'ps_name' => $psDesc->psname
            ]);

            // Récupérer les logs d'audit liés à cette colonne
            $auditLogs = AuditLog::where('db_id', $dbId)
                ->where('ps_id', $psDesc->id)
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
                'ps_name' => $psName,
                'column_name' => $columnName
            ]);
            
            return response()->json([
                'error' => 'Erreur lors de la récupération des logs d\'audit: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateColumnRangeValues(Request $request, $procedureName, $parameterName)
    {
        try {
            if ($error = $this->requirePermission($request, 'write')) {
                return $error;
            }

            $request->validate([
                'default_value' => 'nullable|string|max:2000'
            ]);

            $dbId = session('current_db_id');
            if (!$dbId) {
                return back()->withErrors(['error' => 'Aucune base de données sélectionnée']);
            }

            $procedureDesc = PsDescription::where('dbid', $dbId)
                ->where('psname', $procedureName)
                ->first();

            if (!$procedureDesc) {
                return back()->withErrors(['error' => 'Procédure non trouvée']);
            }

            $parameter = PsParameter::where('id_ps', $procedureDesc->id)
                ->where('name', $parameterName)
                ->first();

            if (!$parameter) {
                return back()->withErrors(['error' => 'Paramètre non trouvé']);
            }

            $currentProject = session('current_project', []);
            $canEdit = $currentProject['is_owner'] ?? false;
            
            if (!$canEdit) {
                return back()->withErrors(['error' => 'Insufficient permissions']);
            }

            $oldDefaultValue = $parameter->default_value;
            $parameter->default_value = $request->input('default_value');
            $parameter->save();

            $this->logAudit(
                $dbId, 
                $procedureDesc->id, 
                $parameterName . '_default_value', 
                'update', 
                $oldDefaultValue, 
                $request->input('default_value')
            );

            return back()->with('success', 'Range values du paramètre mises à jour avec succès');

        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour des range values', [
                'procedure_name' => $procedureName,
                'parameter_name' => $parameterName,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['error' => 'Error while saving: ' . $e->getMessage()]);
        }
    }

    public function updateColumnDescription(Request $request, $procedureName, $parameterName)
    {
        try {
            if ($error = $this->requirePermission($request, 'write')) {
                return $error;
            }

            $request->validate([
                'description' => 'nullable|string|max:2000'
            ]);

            $parameterName = urldecode($parameterName);

            Log::info('🔍 UPDATE PARAMETER DESCRIPTION (Inertia)', [
                'procedure_name' => $procedureName,
                'parameter_name' => $parameterName,
                'description' => $request->input('description')
            ]);

            $dbId = session('current_db_id');
            if (!$dbId) {
                return back()->withErrors(['error' => 'Aucune base de données sélectionnée']);
            }

            $procedureDesc = PsDescription::where('dbid', $dbId)
                ->where('psname', $procedureName)
                ->first();

            if (!$procedureDesc) {
                return back()->withErrors(['error' => 'Procédure non trouvée']);
            }

            $parameter = PsParameter::where('id_ps', $procedureDesc->id)
                ->where('name', $parameterName)
                ->first();

            if (!$parameter) {
                Log::error('Paramètre non trouvé', [
                    'procedure_id' => $procedureDesc->id,
                    'parameter_name' => $parameterName,
                    'available_parameters' => PsParameter::where('id_ps', $procedureDesc->id)->get(['id', 'name'])->toArray()
                ]);
                
                return back()->withErrors(['error' => 'Can find parameter: ' . $parameterName]);
            }

            $currentProject = session('current_project', []);
            $canEdit = $currentProject['is_owner'] ?? false;
            
            if (!$canEdit) {
                return back()->withErrors(['error' => 'Insufficient permissions']);
            }

            $oldDescription = $parameter->description;
            $parameter->description = $request->input('description');
            $parameter->save();

            $this->logAudit(
                $dbId, 
                $procedureDesc->id, 
                $parameterName . '_description', 
                'update', 
                $oldDescription, 
                $request->input('description')
            );

            return back()->with('success', 'Description du paramètre mise à jour avec succès');

        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de la description du paramètre', [
                'procedure_name' => $procedureName,
                'parameter_name' => $parameterName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->withErrors(['error' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()]);
        }
    }


    public function updateColumnRelease(Request $request, $procedureName, $columnName)
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
            $procedureDesc = PsDescription::where('dbid', $dbId)
                ->where('psname', $procedureName)
                ->first();

            if (!$procedureDesc) {
                return response()->json(['error' => 'Procedure not found'], 404);
            }

            // Récupérer la colonne
            $column = PsParameter::where('id_ps', $procedureDesc->id)
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
                    $procedureDesc->id, 
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

    /**
     * Sauvegarde toutes les informations d'une procédure stockée (uniquement la description)
     */
    public function saveAll(Request $request, $procedureName)
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

            // Récupérer la description de la procédure
            $procedureDesc = PsDescription::where('dbid', $dbId)
                ->where('psname', $procedureName)
                ->first();

            if (!$procedureDesc) {
                return response()->json(['error' => 'Procédure stockée non trouvée'], 404);
            }

            // Mettre à jour la description
            $procedureDesc->description = $validated['description'];
            $procedureDesc->save();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la sauvegarde des informations: ' . $e->getMessage()
            ], 500);
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
                'ps_id' => 'required|integer',
                'name' => 'required|string'
            ]);

            Log::info('Données validées', [
                'validated' => $validated
            ]);

            // Récupérer les informations de la table
            $procedureDesc = DB::table('ps_description')
                ->where('id', $validated['ps_id'])
                ->first();

            if (!$procedureDesc) {
                return response()->json([
                    'success' => false,
                    'error' => 'Procedure not found'
                ], 404);
            }

            // Appeler la méthode du TableController pour la mise à jour avec audit
            $ProcedureController = new \App\Http\Controllers\ProcedureController();
            
            // Créer une nouvelle requête avec les bonnes données
            $updateRequest = new Request([
                'release_id' => $validated['release_id']
            ]);

            // Appeler la méthode qui gère l'audit
            $response = $ProcedureController->updateColumnRelease(
                $updateRequest, 
                $procedureDesc->psname, 
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

    public function updateDescription(Request $request, $procedureName)
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

            $procedureDesc = PsDescription::where('dbid', $dbId)
                ->where('psname', $procedureName)
                ->first();

            if (!$procedureDesc) {
                return back()->withErrors(['error' => 'Procédure non trouvée']);
            }

            $currentProject = session('current_project', []);
            $canEdit = $currentProject['is_owner'] ?? false;
            
            if (!$canEdit) {
                return back()->withErrors(['error' => 'Insufficient permissions']);
            }

            $oldDescription = $procedureDesc->description;
            $procedureDesc->description = $request->input('description');
            $procedureDesc->save();

            $this->logAudit(
                $dbId, 
                $procedureDesc->id, 
                'description', 
                'update', 
                $oldDescription, 
                $request->input('description')
            );

            return back()->with('success', 'Description de la procédure mise à jour avec succès');

        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de la description de la procédure', [
                'procedure_name' => $procedureName,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['error' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()]);
        }
    }

    public function updateParameterRelease(Request $request, $procedureName, $parameterName)
    {
        try {
            if ($error = $this->requirePermission($request, 'write')) {
                return $error;
            }

            $request->validate([
                'release_id' => 'nullable|exists:release,id'
            ]);

            $dbId = session('current_db_id');
            if (!$dbId) {
                return back()->withErrors(['error' => 'Aucune base de données sélectionnée']);
            }

            $procedureDesc = PsDescription::where('dbid', $dbId)
                ->where('psname', $procedureName)
                ->first();

            if (!$procedureDesc) {
                return back()->withErrors(['error' => 'Procédure non trouvée']);
            }

            $parameter = PsParameter::where('id_ps', $procedureDesc->id)
                ->where('name', $parameterName)
                ->first();

            if (!$parameter) {
                return back()->withErrors(['error' => 'Paramètre non trouvé']);
            }

            $currentProject = session('current_project', []);
            $canEdit = $currentProject['is_owner'] ?? false;
            
            if (!$canEdit) {
                return back()->withErrors(['error' => 'Insufficient permissions']);
            }

            $oldReleaseId = $parameter->release_id;
            $newReleaseId = $request->input('release_id');
            
            $parameter->release_id = $newReleaseId;
            $parameter->save();

            $this->logAudit(
                $dbId, 
                $procedureDesc->id, 
                $parameterName . '_release', 
                'update', 
                $oldReleaseId, 
                $newReleaseId
            );

            return back()->with('success', 'Release du paramètre mise à jour avec succès');

        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de la release', [
                'procedure_name' => $procedureName,
                'parameter_name' => $parameterName,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['error' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()]);
        }
    }
    
}

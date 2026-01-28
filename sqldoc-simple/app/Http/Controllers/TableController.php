<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\TableDescription;
use App\Models\TableStructure;
use App\Models\Release;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Http\Controllers\Traits\HasProjectPermissions;

class TableController extends Controller
{

    use HasProjectPermissions;
    /**
     * Affiche les détails d'une table spécifique
     */
    public function details(Request $request, $tableName)
    {
        try {
            if ($error = $this->requirePermission($request, 'read')) {
                return $error;
            }

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            Log::info('Récupération des détails pour tableName: ' . $tableName . ', dbId: ' . $dbId);

            if (!$dbId) {
                return Inertia::render('TableDetails', [
                    'tableName' => $tableName,
                    'tableDetails' => $this->getDefaultTableData(),
                    'availableReleases' => $this->getAvailableReleases(),
                    'permissions' => $this->getUserPermissions($request),
                    'error' => 'Aucune base de données sélectionnée'
                ]);
            }

            // Récupérer toutes les données nécessaires
            $tableData = $this->getCompleteTableData($dbId, $tableName, $request);
            $availableReleases = $this->getAvailableReleases();
            $permissions = $this->getUserPermissions($request);

            return Inertia::render('TableDetails', [
                'tableName' => $tableName,
                'tableDetails' => $tableData,
                'availableReleases' => $availableReleases,
                'permissions' => $permissions
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur dans TableController::details', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render('TableDetails', [
                'tableName' => $tableName,
                'tableDetails' => $this->getDefaultTableData(),
                'availableReleases' => [],
                'permissions' => $this->getUserPermissions($request),
                'error' => 'Erreur lors de la récupération des détails de la table: ' . $e->getMessage()
            ]);
        }
    }

    private function getCompleteTableData($dbId, $tableName, $request)
    {
        // Récupérer la description de la table
        $tableDesc = TableDescription::where('dbid', $dbId)
            ->where('tablename', $tableName)
            ->first();

        if (!$tableDesc) {
            throw new \Exception('Table non trouvée');
        }

        // Récupérer les permissions
        $permissions = $this->getUserPermissions($request);
        $isOwner = $this->isProjectOwner($request);
        $canEdit = $isOwner || ($permissions['can_write'] ?? false);

        // Récupérer les colonnes
        $columns = TableStructure::where('id_table', $tableDesc->id)
            ->with('releases')
            ->get()
            ->map(function ($column) use ($canEdit) {
                return [
                    'column_name' => $column->column,
                    'data_type' => $column->type,
                    'is_nullable' => $column->nullable == 1,
                    'is_primary_key' => $column->key === 'PK',
                    'is_foreign_key' => $column->key === 'FK',
                    'description' => $column->description,
                    'possible_values' => $column->rangevalues,
                    'release_id' => $column->release_id,
                    'release_version' => $column->releases ? $column->releases->version_number : null,
                    'can_edit' => $canEdit
                ];
            });

        // Récupérer les index
        $indexes = DB::table('table_index')
            ->where('id_table', $tableDesc->id)
            ->get()
            ->map(function ($index) {
                return [
                    'index_name' => $index->name,
                    'index_type' => $index->type,
                    'columns' => $index->column,
                    'is_primary_key' => strpos($index->properties, 'PRIMARY KEY') !== false,
                    'is_unique' => strpos($index->properties, 'UNIQUE') !== false
                ];
            });

        // Récupérer les relations
        $relations = DB::table('table_relations')
            ->where('id_table', $tableDesc->id)
            ->get()
            ->map(function ($relation) {
                $deleteRule = 'NO ACTION';
                $updateRule = 'NO ACTION';
                
                if ($relation->action) {
                    if (preg_match('/ON DELETE (\w+( \w+)?)/', $relation->action, $deleteMatches)) {
                        $deleteRule = $deleteMatches[1];
                    }
                    if (preg_match('/ON UPDATE (\w+( \w+)?)/', $relation->action, $updateMatches)) {
                        $updateRule = $updateMatches[1];
                    }
                }
                
                return [
                    'constraint_name' => $relation->constraints,
                    'column_name' => $relation->column,
                    'referenced_table' => $relation->referenced_table,
                    'referenced_column' => $relation->referenced_column,
                    'delete_rule' => $deleteRule,
                    'update_rule' => $updateRule
                ];
            });

        return [
            'description' => $tableDesc->description,
            'columns' => $columns,
            'indexes' => $indexes,
            'relations' => $relations,
            'can_edit' => $canEdit,
            'can_add_columns' => $canEdit,
            'can_add_relations' => $canEdit,
            'is_owner' => $isOwner
        ];
    }

    private function getAvailableReleases()
    {
        try {
            $currentProject = session('current_project');
            if (!$currentProject || !isset($currentProject['id'])) {
                return [];
            }
            
            // Récupérer les releases du projet actuel
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

    private function getDefaultTableData()
    {
        return [
            'description' => '',
            'columns' => [],
            'indexes' => [],
            'relations' => [],
            'can_edit' => false,
            'can_add_columns' => false,
            'can_add_relations' => false,
            'is_owner' => false
        ];
    }

    /**
     * Enregistre une action dans les logs d'audit
     */
    private function logAudit($dbId, $tableId, $columnName, $changeType, $oldData, $newData)
    {
        try {
            $userId = Auth::id() ?? null;
            
            AuditLog::create([
                'user_id' => $userId,
                'db_id' => $dbId,
                'table_id' => $tableId,
                'column_name' => $columnName,
                'change_type' => $changeType,
                'old_data' => $oldData,
                'new_data' => $newData
            ]);
            
            Log::info('Audit log créé', [
                'user_id' => $userId,
                'db_id' => $dbId,
                'table_id' => $tableId,
                'column_name' => $columnName,
                'change_type' => $changeType
            ]);
        } catch (\Exception $e) {
            Log::error('Error while creating log Audit', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
    * Récupère les logs d'audit pour une colonne spécifique
    */
    public function getAuditLogs(Request $request, $tableName, $columnName)
    {
        try {
            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'No database selected'], 400);
            }

            // Récupérer la description de la table
            $tableDesc = TableDescription::where('dbid', $dbId)
                ->where('tablename', $tableName)
                ->first();

            if (!$tableDesc) {
                return response()->json(['error' => 'Table not found'], 404);
            }

            // Récupérer les logs d'audit liés à cette colonne
            $auditLogs = AuditLog::where('db_id', $dbId)
                ->where('table_id', $tableDesc->id)
                ->where('column_name', 'like', $columnName . '%')
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($auditLogs); 

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération des logs d\'audit: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sauvegarde la structure de la table (uniquement descriptions et valeurs possibles)
     */
    public function saveStructure(Request $request, $tableName)
    {
        try {
            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to modify table structure.')) {
                return $error;
            }

            // Valider les données
            $validated = $request->validate([
                'description' => 'nullable|string',
                'language' => 'required|string|size:2',
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
            $tableDesc = TableDescription::where('dbid', $dbId)
                ->where('tablename', $tableName)
                ->first();

            if (!$tableDesc) {
                return response()->json(['error' => 'Table non trouvée'], 404);
            }

            // Mettre à jour la description de la table avec audit
            if ($tableDesc->description !== $validated['description']) {
                $oldDescription = $tableDesc->description;
                $tableDesc->description = $validated['description'];
                
                // Log de l'audit pour la description de la table
                $this->logAudit(
                    $dbId, 
                    $tableDesc->id, 
                    'table_description', 
                    'update', 
                    $oldDescription, 
                    $validated['description']
                );
            }
            
            // Mise à jour de la langue si elle a changé
            if ($tableDesc->language !== $validated['language']) {
                $oldLanguage = $tableDesc->language;
                $tableDesc->language = $validated['language'];
                
                // Log de l'audit pour la langue
                $this->logAudit(
                    $dbId, 
                    $tableDesc->id, 
                    'table_language', 
                    'update', 
                    $oldLanguage, 
                    $validated['language']
                );
            }
            
            $tableDesc->save();

            // Mettre à jour les descriptions et valeurs possibles des colonnes
            foreach ($validated['columns'] as $columnData) {
                $column = TableStructure::where('id_table', $tableDesc->id)
                    ->where('column', $columnData['column'])
                    ->first();

                if ($column) {
                    // Vérifier si la description a changé
                    if ($column->description !== $columnData['description']) {
                        $oldDescription = $column->description;
                        $column->description = $columnData['description'];
                        
                        // Log de l'audit pour la description de la colonne
                        $this->logAudit(
                            $dbId, 
                            $tableDesc->id, 
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
                            $tableDesc->id, 
                            $columnData['column'] . '_rangevalues', 
                            'update', 
                            $oldRangeValues, 
                            $columnData['rangevalues']
                        );
                    }
                    
                    $column->save();
                }
            }

            Log::info('Table structure updated', [
                'user_id' => auth()->id(),
                'table_name' => $tableName,
                'permissions' => $request->get('user_project_permission')['level'] ?? 'none'
            ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la sauvegarde de la structure: ' . $e->getMessage()], 500);
        }
    }

    public function saveDescription(Request $request, $tableName)
    {
        try {
            // Debug - voir ce qui arrive dans la requête
            Log::info('Données reçues:', [
                'tableName' => $tableName,
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
            $tableDesc = TableDescription::where('dbid', $dbId)
                ->where('tablename', $tableName)
                ->first();

            if (!$tableDesc) {
                Log::error('Table non trouvée:', ['dbid' => $dbId, 'tablename' => $tableName]);
                
                // Créer une nouvelle entrée si elle n'existe pas
                $tableDesc = new TableDescription();
                $tableDesc->dbid = $dbId;
                $tableDesc->tablename = $tableName;
            }

            // Log de l'état avant modification
            Log::info('Avant modification:', [
                'ancien_description' => $tableDesc->description,
                'nouveau_description' => $validated['description']
            ]);

            // Mettre à jour la description de la table
            $tableDesc->description = $validated['description'];
            $result = $tableDesc->save();

            // Log du résultat
            Log::info('Résultat sauvegarde:', [
                'save_result' => $result,
                'description_finale' => $tableDesc->description,
                'table_id' => $tableDesc->id ?? 'nouveau'
            ]);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Description sauvegardée avec succès',
                    'description' => $tableDesc->description,
                    'tableDetails' => [
                        'description' => $tableDesc->description,
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
     * Mise à jour la description d'une colonne spécifique
     */
    public function updateColumnDescription(Request $request, $tableName, $columnName)
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
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la table
            $tableDesc = TableDescription::where('dbid', $dbId)
                ->where('tablename', $tableName)
                ->first();

            if (!$tableDesc) {
                return response()->json(['error' => 'Table non trouvée'], 404);
            }

            // Mettre à jour la description de la colonne
            $column = TableStructure::where('id_table', $tableDesc->id)
                ->where('column', $columnName)
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
                    $tableDesc->id, 
                    $columnName . '_description', 
                    'update', 
                    $oldDescription, 
                    $validated['description']
                );
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la mise à jour de la description: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Met à jour les valeurs possibles d'une colonne spécifique
     */
    public function updateColumnPossibleValues(Request $request, $tableName, $columnName)
    {
        try {

            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to update possible values.')) {
            return $error;
            }

            // Valider les données
            $validated = $request->validate([
                'possible_values' => 'nullable|string'
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la table
            $tableDesc = TableDescription::where('dbid', $dbId)
                ->where('tablename', $tableName)
                ->first();

            if (!$tableDesc) {
                return response()->json(['error' => 'Table non trouvée'], 404);
            }

            // Mettre à jour les valeurs possibles de la colonne
            $column = TableStructure::where('id_table', $tableDesc->id)
                ->where('column', $columnName)
                ->first();

            if (!$column) {
                return response()->json(['error' => 'Colonne non trouvée'], 404);
            }

            // Vérifier si les valeurs possibles ont changé
            if ($column->rangevalues !== $validated['possible_values']) {
                $oldRangeValues = $column->rangevalues;
                $column->rangevalues = $validated['possible_values'];
                $column->save();
                
                // Log pour déboguer le résultat de la sauvegarde
                Log::info('Résultat de la sauvegarde', [
                    'column' => $columnName,
                    'rangevalues' => $column->rangevalues,
                    'saveResult' => $column 
                ]);
                
                // Log de l'audit pour les valeurs possibles
                $this->logAudit(
                    $dbId, 
                    $tableDesc->id, 
                    $columnName . '_rangevalues', 
                    'update', 
                    $oldRangeValues, 
                    $validated['possible_values']
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
    
    /**
     * Met à jour les propriétés structurelles d'une colonne
     */
    public function updateColumnProperties(Request $request, $tableName, $columnName)
    {
        try {

            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to update column properties.')) {
            return $error;
            }

            // Valider les données
            $validated = $request->validate([
                'column_name' => 'required|string',
                'data_type' => 'required|string',
                'is_nullable' => 'required|boolean',
                'is_primary_key' => 'required|boolean',
                'is_foreign_key' => 'required|boolean',
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la table
            $tableDesc = TableDescription::where('dbid', $dbId)
                ->where('tablename', $tableName)
                ->first();

            if (!$tableDesc) {
                return response()->json(['error' => 'Table non trouvée'], 404);
            }

            // Récupérer la colonne
            $column = TableStructure::where('id_table', $tableDesc->id)
                ->where('column', $columnName)
                ->first();

            if (!$column) {
                return response()->json(['error' => 'Colonne non trouvée'], 404);
            }

            // Mettre à jour le nom de la colonne
            if ($column->column !== $validated['column_name']) {
                $oldColumnName = $column->column;
                $column->column = $validated['column_name'];
                
                // Log de l'audit pour le nom de la colonne
                $this->logAudit(
                    $dbId, 
                    $tableDesc->id, 
                    $columnName, 
                    'update_name', 
                    $oldColumnName, 
                    $validated['column_name']
                );
            }

            // Mettre à jour le type de données
            if ($column->type !== $validated['data_type']) {
                $oldType = $column->type;
                $column->type = $validated['data_type'];
                
                // Log de l'audit pour le type de données
                $this->logAudit(
                    $dbId, 
                    $tableDesc->id, 
                    $columnName . '_type', 
                    'update', 
                    $oldType, 
                    $validated['data_type']
                );
            }

            // Mettre à jour la nullabilité
            $newNullable = $validated['is_nullable'] ? 1 : 0;
            if ($column->nullable != $newNullable) {
                $oldNullable = $column->nullable;
                $column->nullable = $newNullable;
                
                // Log de l'audit pour la nullabilité
                $this->logAudit(
                    $dbId, 
                    $tableDesc->id, 
                    $columnName . '_nullable', 
                    'update', 
                    $oldNullable ? 'true' : 'false', 
                    $newNullable ? 'true' : 'false'
                );
            }

            // Mettre à jour les clés
            $oldKey = $column->key;
            $newKey = null;
            
            if ($validated['is_primary_key']) {
                $newKey = 'PK';
            } elseif ($validated['is_foreign_key']) {
                $newKey = 'FK';
            }
            
            if ($oldKey !== $newKey) {
                $column->key = $newKey;
                
                // Log de l'audit pour le type de clé
                $this->logAudit(
                    $dbId, 
                    $tableDesc->id, 
                    $columnName . '_key', 
                    'update', 
                    $oldKey ?: 'null', 
                    $newKey ?: 'null'
                );
            }

            // Sauvegarder les modifications
            $column->save();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la mise à jour des propriétés de la colonne: ' . $e->getMessage()], 500);
        }
    }

    public function updateColumnRelease(Request $request, $tableName, $columnName)
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
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la table
            $tableDesc = TableDescription::where('dbid', $dbId)
                ->where('tablename', $tableName)
                ->first();

            if (!$tableDesc) {
                return response()->json(['error' => 'Table non trouvée'], 404);
            }

            // Récupérer la colonne
            $column = TableStructure::where('id_table', $tableDesc->id)
                ->where('column', $columnName)
                ->first();

            if (!$column) {
                return response()->json(['error' => 'Colonne non trouvée'], 404);
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
                    $oldReleaseInfo = $oldRelease ? $oldRelease->version_number : 'Version supprimée';
                }
                
                if ($newReleaseId) {
                    $newRelease = Release::find($newReleaseId);
                    $newReleaseInfo = $newRelease ? $newRelease->version_number : 'Version inconnue';
                }
                
                // Mettre à jour la colonne
                $column->release_id = $newReleaseId;
                $column->save();
                
                // Log de l'audit pour la version
                $this->logAudit(
                    $dbId, 
                    $tableDesc->id, 
                    $columnName . '_release', 
                    'update', 
                    $oldReleaseInfo ?: 'null', 
                    $newReleaseInfo ?: 'null'
                );
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de la version de colonne', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Erreur lors de la mise à jour de la version: ' . $e->getMessage()], 500);
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
                'table_id' => 'required|integer',
                'column_name' => 'required|string'
            ]);

            Log::info('Données validées', [
                'validated' => $validated
            ]);

            // Récupérer les informations de la table
            $tableDesc = DB::table('table_description')
                ->where('id', $validated['table_id'])
                ->first();

            if (!$tableDesc) {
                return response()->json([
                    'success' => false,
                    'error' => 'Table non trouvée'
                ], 404);
            }

            // Appeler la méthode du TableController pour la mise à jour avec audit
            $tableController = new \App\Http\Controllers\TableController();
            
            // Créer une nouvelle requête avec les bonnes données
            $updateRequest = new Request([
                'release_id' => $validated['release_id']
            ]);

            // Appeler la méthode qui gère l'audit
            $response = $tableController->updateColumnRelease(
                $updateRequest, 
                $tableDesc->tablename, 
                $validated['column_name']
            );

            // Retourner la réponse de la méthode d'audit
            return $response;

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



    public function apiDetails($tableName)
    {
        try {
            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            Log::info('API - Récupération des détails pour tableName: ' . $tableName . ', dbId: ' . $dbId);
            
            if (!$dbId) {
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la table
            $tableDesc = TableDescription::where('dbid', $dbId)
                ->where('tablename', $tableName)
                ->first();

            if (!$tableDesc) {
                return response()->json(['error' => 'Table non trouvée'], 404);
            }

            // Récupérer les colonnes de la table
            $columns = TableStructure::where('id_table', $tableDesc->id)
                ->get()
                ->map(function ($column) {
                    return [
                        'column_name' => $column->column,
                        'data_type' => $column->type,
                        'is_nullable' => $column->nullable == 1,
                        'is_primary_key' => $column->key === 'PK',
                        'is_foreign_key' => $column->key === 'FK',
                        'description' => $column->description,
                        'possible_values' => $column->rangevalues
                    ];
                });

            // Récupérer les index de la table
            $indexes = DB::table('table_index')
                ->where('id_table', $tableDesc->id)
                ->get()
                ->map(function ($index) {
                    return [
                        'index_name' => $index->name,
                        'index_type' => $index->type,
                        'columns' => $index->column,
                        'is_primary_key' => strpos($index->properties, 'PRIMARY KEY') !== false,
                        'is_unique' => strpos($index->properties, 'UNIQUE') !== false
                    ];
                });

            // Récupérer les relations de la table
            $relations = DB::table('table_relations')
                ->where('id_table', $tableDesc->id)
                ->get()
                ->map(function ($relation) {
                    // Analyse de la chaîne d'action pour extraire les règles DELETE et UPDATE
                    $deleteRule = 'NO ACTION';
                    $updateRule = 'NO ACTION';
                    
                    if ($relation->action) {
                        if (preg_match('/ON DELETE (\w+( \w+)?)/', $relation->action, $deleteMatches)) {
                            $deleteRule = $deleteMatches[1];
                        }
                        if (preg_match('/ON UPDATE (\w+( \w+)?)/', $relation->action, $updateMatches)) {
                            $updateRule = $updateMatches[1];
                        }
                    }
                    
                    return [
                        'constraint_name' => $relation->constraints,
                        'column_name' => $relation->column,
                        'referenced_table' => $relation->referenced_table,
                        'referenced_column' => $relation->referenced_column,
                        'delete_rule' => $deleteRule,
                        'update_rule' => $updateRule
                    ];
                });

            // Renvoyer la réponse JSON
            return response()->json([
                'description' => $tableDesc->description,
                'columns' => $columns,
                'indexes' => $indexes,
                'relations' => $relations
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur dans TableController::apiDetails', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Erreur lors de la récupération des détails de la table: ' . $e->getMessage()], 500);
        }
    }

    public function addColumn(Request $request, $tableName)
    {
        try {

            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to add columns.')) {
                return $error;
            }
            // Valider les données
            $validated = $request->validate([
                'column_name' => 'required|string|max:255',
                'data_type' => 'required|string|max:255',
                'is_nullable' => 'required|boolean',
                'is_primary_key' => 'required|boolean',
                'is_foreign_key' => 'required|boolean',
                'description' => 'nullable|string',
                'possible_values' => 'nullable|string',
                'release' => 'nullable|string'
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la table
            $tableDesc = TableDescription::where('dbid', $dbId)
                ->where('tablename', $tableName)
                ->first();

            if (!$tableDesc) {
                return response()->json(['error' => 'Table non trouvée'], 404);
            }

            // Vérifier si une colonne avec ce nom existe déjà
            $existingColumn = TableStructure::where('id_table', $tableDesc->id)
                ->where('column', $validated['column_name'])
                ->first();

            if ($existingColumn) {
                return response()->json(['error' => 'Une colonne avec ce nom existe déjà dans cette table'], 400);
            }

            // Déterminer le type de clé
            $keyType = null;
            if ($validated['is_primary_key']) {
                $keyType = 'PK';
            } elseif ($validated['is_foreign_key']) {
                $keyType = 'FK';
            }

            // Créer la nouvelle colonne
            $newColumn = new TableStructure();
            $newColumn->id_table = $tableDesc->id;
            $newColumn->column = $validated['column_name'];
            $newColumn->type = $validated['data_type'];
            $newColumn->nullable = $validated['is_nullable'] ? 1 : 0;
            $newColumn->key = $keyType;
            $newColumn->description = $validated['description'];
            $newColumn->rangevalues = $validated['possible_values'];
            
            // Ajouter le champ release si disponible
            if (Schema::hasColumn('table_structure', 'release')) {
                $newColumn->release = $validated['release'];
            }
            
            $newColumn->save();

            // Log de l'audit pour l'ajout de la colonne
            $this->logAudit(
                $dbId,
                $tableDesc->id,
                $validated['column_name'],
                'add',
                null,
                json_encode([
                    'column_name' => $validated['column_name'],
                    'data_type' => $validated['data_type'],
                    'is_nullable' => $validated['is_nullable'],
                    'is_primary_key' => $validated['is_primary_key'],
                    'is_foreign_key' => $validated['is_foreign_key'],
                    'description' => $validated['description'],
                    'possible_values' => $validated['possible_values'],
                    'release' => $validated['release'] ?? null
                ])
            );

            return response()->json(['success' => true, 'column_id' => $newColumn->id]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'ajout d\'une colonne', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Erreur lors de l\'ajout de la colonne: ' . $e->getMessage()], 500);
        }
    }

    public function addRelation(Request $request, $tableName)
    {
        try {

            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to add relations.')) {
                return $error;
            }

            // Valider les données
            $validated = $request->validate([
                'constraint_name' => 'required|string|max:255',
                'column_name' => 'required|string|max:255',
                'referenced_table' => 'required|string|max:255',
                'referenced_column' => 'required|string|max:255',
                'delete_rule' => 'required|string|max:255',
                'update_rule' => 'required|string|max:255'
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer la description de la table
            $tableDesc = TableDescription::where('dbid', $dbId)
                ->where('tablename', $tableName)
                ->first();

            if (!$tableDesc) {
                return response()->json(['error' => 'Table non trouvée'], 404);
            }

            // Vérifier si une relation avec ce nom de contrainte existe déjà
            $existingRelation = DB::table('table_relations')
                ->where('id_table', $tableDesc->id)
                ->where('constraints', $validated['constraint_name'])
                ->first();

            if ($existingRelation) {
                return response()->json(['error' => 'Une relation avec ce nom de contrainte existe déjà'], 400);
            }

            // Vérifier si la colonne source existe
            $column = TableStructure::where('id_table', $tableDesc->id)
                ->where('column', $validated['column_name'])
                ->first();

            if (!$column) {
                return response()->json(['error' => 'La colonne source n\'existe pas dans cette table'], 404);
            }

            // Construire la chaîne d'action à partir des règles ON DELETE et ON UPDATE
            $action = '';
            if ($validated['delete_rule'] !== 'NO ACTION') {
                $action .= 'ON DELETE ' . $validated['delete_rule'];
            }
            if ($validated['update_rule'] !== 'NO ACTION') {
                if (!empty($action)) {
                    $action .= ' ';
                }
                $action .= 'ON UPDATE ' . $validated['update_rule'];
            }

            // Créer la nouvelle relation
            $relationId = DB::table('table_relations')->insertGetId([
                'id_table' => $tableDesc->id,
                'constraints' => $validated['constraint_name'],
                'column' => $validated['column_name'],
                'referenced_table' => $validated['referenced_table'],
                'referenced_column' => $validated['referenced_column'],
                'action' => $action,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Log de l'audit pour l'ajout de la relation
            $this->logAudit(
                $dbId,
                $tableDesc->id,
                $validated['constraint_name'],
                'add_relation',
                null,
                json_encode([
                    'constraint_name' => $validated['constraint_name'],
                    'column_name' => $validated['column_name'],
                    'referenced_table' => $validated['referenced_table'],
                    'referenced_column' => $validated['referenced_column'],
                    'delete_rule' => $validated['delete_rule'],
                    'update_rule' => $validated['update_rule']
                ])
            );

            // Mettre à jour la colonne pour indiquer qu'elle est une clé étrangère si ce n'est pas déjà le cas
            if ($column->key !== 'PK' && $column->key !== 'FK') {
                $column->key = 'FK';
                $column->save();
                
                // Log de l'audit pour la mise à jour du type de clé
                $this->logAudit(
                    $dbId,
                    $tableDesc->id,
                    $validated['column_name'] . '_key',
                    'update',
                    $column->key ?: 'null',
                    'FK'
                );
            }

            return response()->json(['success' => true, 'relation_id' => $relationId]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'ajout d\'une relation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Erreur lors de l\'ajout de la relation: ' . $e->getMessage()], 500);
        }
    }

    public function getTableId($tableName)
    {
        try {
            $dbId = session('current_db_id');
            $tableDesc = TableDescription::where('dbid', $dbId)
                ->where('tablename', $tableName)
                ->first();

            if (!$tableDesc) {
                return response()->json(['error' => 'Table non trouvée'], 404);
            }

            return response()->json(['id' => $tableDesc->id]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

}

<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;
use App\Models\UserProjectAccess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

trait HasProjectPermissions 
{
    /**
     * Récupérer les permissions utilisateur depuis la base de données
     */
    protected function getUserPermissions(Request $request): array
    {
        // D'abord essayer de récupérer depuis la requête (si déjà chargées)
        $cachedPermissions = $request->get('user_project_permission');
        if ($cachedPermissions) {
            Log::info('Permissions trouvées dans la requête', ['permissions' => $cachedPermissions]);
            return $cachedPermissions;
        }

        // Sinon charger depuis la base de données
        $dbId = session('current_db_id');
        $userId = Auth::id();

        Log::info('Chargement des permissions depuis la DB', [
            'user_id' => $userId,
            'db_id' => $dbId
        ]);

        if (!$userId || !$dbId) {
            Log::warning('Utilisateur non connecté ou aucune DB sélectionnée', [
                'user_id' => $userId,
                'db_id' => $dbId
            ]);
            return [
                'level' => 'none',
                'can_read' => false,
                'can_write' => false,
                'can_admin' => false,
                'can_delete' => false
            ];
        }

        // Récupérer les permissions depuis la base de données
        $userAccess = UserProjectAccess::where('user_id', $userId)
            ->where('db_id', $dbId)
            ->first();

        if (!$userAccess) {
            // Vérifier si l'utilisateur est le créateur/propriétaire du projet
            // Vous devrez adapter cette partie selon votre structure de données
            $project = \App\Models\Project::where('db_id', $dbId)->first();
            
            if ($project && $project->created_by === $userId) {
                Log::info('Utilisateur identifié comme propriétaire du projet');
                $permissions = [
                    'level' => 'owner',
                    'can_read' => true,
                    'can_write' => true,
                    'can_admin' => true,
                    'can_delete' => true
                ];
            } else {
                Log::info('Aucun accès trouvé pour cet utilisateur');
                $permissions = [
                    'level' => 'none',
                    'can_read' => false,
                    'can_write' => false,
                    'can_admin' => false,
                    'can_delete' => false
                ];
            }
        } else {
            Log::info('Permissions trouvées dans la DB', ['user_access' => $userAccess]);
            
            $permissions = [
                'level' => $userAccess->level,
                'can_read' => $userAccess->can_read ?? false,
                'can_write' => $userAccess->can_write ?? false,
                'can_admin' => $userAccess->can_admin ?? false,
                'can_delete' => $userAccess->can_delete ?? false
            ];
        }

        // Mettre en cache dans la requête pour éviter de recharger
        $request->merge(['user_project_permission' => $permissions]);
        
        Log::info('Permissions finales', ['permissions' => $permissions]);
        return $permissions;
    }

    /**
     * Vérifier si c'est le propriétaire
     */
    protected function isProjectOwner(Request $request): bool
    {
        $permissions = $this->getUserPermissions($request);
        $isOwner = ($permissions['level'] ?? '') === 'owner';
        
        Log::info('Vérification propriétaire', [
            'level' => $permissions['level'] ?? 'none',
            'is_owner' => $isOwner
        ]);
        
        return $isOwner;
    }

    /**
     * Vérifier les permissions du projet
     */
    protected function checkProjectPermission(Request $request, string $requiredLevel): bool
    {
        $permissions = $this->getUserPermissions($request);
        
        Log::info('Vérification permission', [
            'required_level' => $requiredLevel,
            'permissions' => $permissions
        ]);

        // Si owner, tout est autorisé
        if (($permissions['level'] ?? '') === 'owner') {
            Log::info('Accès autorisé - Propriétaire');
            return true;
        }

        // Sinon vérifier les permissions spécifiques
        switch ($requiredLevel) {
            case 'read':
                return $permissions['can_read'] ?? false;
            case 'write':
                return $permissions['can_write'] ?? false;
            case 'admin':
                return $permissions['can_admin'] ?? false;
            case 'delete':
                return $permissions['can_delete'] ?? false;
            default:
                return false;
        }
    }

    /**
     * Exiger une permission spécifique ou retourner une erreur
     */
    protected function requirePermission(Request $request, string $requiredLevel, string $errorMessage = null)
    {
        Log::info('Vérification permission requise', [
            'required_level' => $requiredLevel,
            'user_id' => Auth::id()
        ]);

        if (!$this->checkProjectPermission($request, $requiredLevel)) {
            $errorMessage = $errorMessage ?: "You don't have {$requiredLevel} permission for this project.";
            
            Log::warning('Permission refusée', [
                'required_level' => $requiredLevel,
                'error_message' => $errorMessage
            ]);
                        
            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json(['error' => $errorMessage], 403);
            }
                        
            return redirect()->back()->with('error', $errorMessage);
        }
        
        Log::info('Permission accordée', ['required_level' => $requiredLevel]);
        return null;
    }

    /**
     * Vérifier un niveau minimum
     */
    protected function hasMinimumLevel(Request $request, string $minimumLevel): bool
    {
        $permissions = $this->getUserPermissions($request);
        $currentLevel = $permissions['level'] ?? 'none';
        
        $levels = ['none', 'read', 'write', 'Admin', 'owner'];
        $currentIndex = array_search($currentLevel, $levels);
        $requiredIndex = array_search($minimumLevel, $levels);
        
        $hasLevel = $currentIndex !== false && $requiredIndex !== false && $currentIndex >= $requiredIndex;
        
        Log::info('Vérification niveau minimum', [
            'current_level' => $currentLevel,
            'minimum_level' => $minimumLevel,
            'has_level' => $hasLevel
        ]);
        
        return $hasLevel;
    }
}
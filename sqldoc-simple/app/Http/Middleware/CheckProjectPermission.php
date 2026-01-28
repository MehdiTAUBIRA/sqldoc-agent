<?php
// Dans CheckProjectPermission.php - Correction complète

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\DbDescription; 
use App\Models\UserProjectAccess;
use Illuminate\Support\Facades\Log;

class CheckProjectPermission
{
    public function handle(Request $request, Closure $next, string $permission = 'read')
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $projectId = $this->findProjectId($request);

        if (!$projectId) {
            Log::warning('Aucun projet trouvé pour vérifier les permissions', [
                'user_id' => $user->id,
                'route' => $request->route()->getName(),
                'url' => $request->url(),
                'current_db_id' => session('current_db_id'),
                'current_project' => session('current_project')
            ]);
            
            return $this->redirectWithError($request, 'No project selected. Please select a project first.');
        }

        // Vérifier les permissions
        $hasPermission = $this->checkUserProjectPermission($user, $projectId, $permission);
        
        if (!$hasPermission) {
            Log::warning('Permission refusée', [
                'user_id' => $user->id,
                'project_id' => $projectId,
                'required_permission' => $permission,
                'route' => $request->route()->getName()
            ]);
            
            return $this->redirectWithError($request, "You don't have permission to {$permission} this project.");
        }

        // Ajouter les informations de permission à la requête
        $request->merge([
            'user_project_permission' => $this->getUserProjectPermission($user, $projectId),
            'current_project_id' => $projectId
        ]);

        return $next($request);
    }

    /**
     * ✅ CORRECTION : Trouver l'ID du projet via db_description
     */
    private function findProjectId(Request $request): ?int
    {
        // 1. Depuis les paramètres de route
        if ($request->route('project')) {
            $projectId = $request->route('project');
            return is_object($projectId) ? $projectId->id : (int)$projectId;
        }
        
        // 2. ✅ CORRECTION : Depuis la session current_db_id via db_description
        if (session('current_db_id')) {
            $dbId = session('current_db_id');
            
            // Trouver la DB description correspondante
            $dbDescription = DbDescription::find($dbId);
            
            if ($dbDescription && $dbDescription->project_id) {
                Log::info('Projet trouvé via DB description', [
                    'db_id' => $dbId,
                    'project_id' => $dbDescription->project_id,
                    'db_name' => $dbDescription->dbname
                ]);
                return $dbDescription->project_id;
            }
            
            Log::warning('DB description introuvable', [
                'db_id' => $dbId,
                'user_id' => auth()->id()
            ]);
        }
        
        // 3. Depuis la session du projet actuel
        if (session('current_project')) {
            return session('current_project')['id'] ?? null;
        }
        
        // 4. Depuis les paramètres de requête
        if ($request->has('project_id')) {
            return (int)$request->get('project_id');
        }

        return null;
    }

    /**
     * Gestion centralisée des redirections d'erreur
     */
    private function redirectWithError(Request $request, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json(['error' => $message], 403);
        }
        
        return redirect()->route('projects.index')->with('error', $message);
    }

    /**
     * Vérifier si l'utilisateur a la permission requise sur le projet
     */
    private function checkUserProjectPermission($user, $projectId, $requiredPermission): bool
    {
        $project = Project::find($projectId);
        if (!$project) {
            Log::warning('Projet introuvable', ['project_id' => $projectId]);
            return false;
        }

        // ✅ CORRECTION : Comparaison avec conversion de type
        $isOwner = (int)$project->user_id === (int)$user->id;
        
        Log::info('Vérification permissions (corrigée)', [
            'user_id' => $user->id,
            'project_id' => $projectId,
            'project_owner_id' => $project->user_id,
            'project_owner_id_type' => gettype($project->user_id),
            'user_id_type' => gettype($user->id),
            'required_permission' => $requiredPermission,
            'is_owner' => $isOwner  // ✅ Maintenant ça devrait être true
        ]);

        // ✅ Le propriétaire a tous les droits (comparaison corrigée)
        if ($isOwner) {
            Log::info('Accès accordé - Propriétaire du projet', [
                'user_id' => $user->id,
                'project_id' => $projectId
            ]);
            return true;
        }

        // Vérifier les accès partagés
        $projectAccess = UserProjectAccess::where('user_id', $user->id)
            ->where('project_id', $projectId)
            ->first();

        Log::info('Vérification accès partagés', [
            'user_id' => $user->id,
            'project_id' => $projectId,
            'has_shared_access' => $projectAccess ? true : false,
            'access_level' => $projectAccess->access_level ?? 'none'
        ]);

        if (!$projectAccess) {
            Log::warning('Aucun accès partagé trouvé', [
                'user_id' => $user->id,
                'project_id' => $projectId
            ]);
            return false;
        }

        // Vérifier selon le niveau d'accès
        $hasPermission = false;
        switch ($requiredPermission) {
            case 'read':
                $hasPermission = in_array($projectAccess->access_level, ['read', 'write', 'Admin']);
                break;
            case 'write':
                $hasPermission = in_array($projectAccess->access_level, ['write', 'Admin']);
                break;
            case 'Admin':
                $hasPermission = $projectAccess->access_level === 'Admin';
                break;
            default:
                $hasPermission = false;
        }

        Log::info('Résultat vérification permission', [
            'user_id' => $user->id,
            'project_id' => $projectId,
            'required_permission' => $requiredPermission,
            'user_access_level' => $projectAccess->access_level,
            'permission_granted' => $hasPermission
        ]);

        return $hasPermission;
    }
    /**
     * Récupérer le niveau de permission de l'utilisateur
     */
    private function getUserProjectPermission($user, $projectId): array
    {
        $project = Project::find($projectId);
        if (!$project) {
            return $this->getDefaultPermissions();
        }

        // ✅ CORRECTION : Comparaison avec conversion de type
        $isOwner = (int)$project->user_id === (int)$user->id;
        
        // Le propriétaire a les droits owner
        if ($isOwner) {
            Log::info('Permissions owner accordées', [
                'user_id' => $user->id,
                'project_id' => $projectId
            ]);
            
            return [
                'level' => 'owner',
                'can_read' => true,
                'can_write' => true,
                'can_admin' => true,
                'can_delete' => true
            ];
        }

        // Vérifier les accès partagés
        $projectAccess = UserProjectAccess::where('user_id', $user->id)
            ->where('project_id', $projectId)
            ->first();

        if (!$projectAccess) {
            return $this->getDefaultPermissions();
        }

        return $this->mapAccessLevelToPermissions($projectAccess->access_level);
    }

    /**
     * Permissions par défaut
     */
    private function getDefaultPermissions(): array
    {
        return [
            'level' => 'none',
            'can_read' => false,
            'can_write' => false,
            'can_admin' => false,
            'can_delete' => false
        ];
    }

    /**
     * Mapper le niveau d'accès aux permissions
     */
    private function mapAccessLevelToPermissions(string $accessLevel): array
    {
        switch ($accessLevel) {
            case 'read':
                return [
                    'level' => 'read',
                    'can_read' => true,
                    'can_write' => false,
                    'can_admin' => false,
                    'can_delete' => false
                ];
            
            case 'write':
                return [
                    'level' => 'write',
                    'can_read' => true,
                    'can_write' => true,
                    'can_admin' => false,
                    'can_delete' => false
                ];
            
            case 'Admin':
                return [
                    'level' => 'Admin',
                    'can_read' => true,
                    'can_write' => true,
                    'can_admin' => true,
                    'can_delete' => false // Seul le propriétaire peut supprimer
                ];
            
            default:
                return $this->getDefaultPermissions();
        }
    }
    
}
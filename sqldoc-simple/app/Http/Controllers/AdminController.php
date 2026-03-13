<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Project;
use App\Models\DbDescription;
use App\Models\UserProjectAccess;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{

    function generateStrongPassword(int $length = 16, int $minUppercase = 1, int $minLowercase = 1, int $minNumbers = 1, int $minSpecial = 1, bool $avoidAmbiguous = true): string 
    {
    
        if ($length < 16) {
            throw new InvalidArgumentException('La longueur minimale est 16 caractères.');
        }

        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers   = '0123456789';
        $special   = '!@#$%^&*()-_=+[]{}:;?';

        if ($avoidAmbiguous) {
            $lowercase = str_replace(['l','o'], '', $lowercase);
            $uppercase = str_replace(['I','O'], '', $uppercase);
            $numbers   = str_replace(['0','1'], '', $numbers);
        }

        $requiredTotal = $minUppercase + $minLowercase + $minNumbers + $minSpecial;

        if ($requiredTotal > $length) {
            throw new InvalidArgumentException('La somme des minimums dépasse la longueur demandée.');
        }

        $password = [];

        for ($i = 0; $i < $minUppercase; $i++) {
            $password[] = $uppercase[random_int(0, strlen($uppercase) - 1)];
        }

        for ($i = 0; $i < $minLowercase; $i++) {
            $password[] = $lowercase[random_int(0, strlen($lowercase) - 1)];
        }

        for ($i = 0; $i < $minNumbers; $i++) {
            $password[] = $numbers[random_int(0, strlen($numbers) - 1)];
        }

        for ($i = 0; $i < $minSpecial; $i++) {
            $password[] = $special[random_int(0, strlen($special) - 1)];
        }

        $all = $lowercase . $uppercase . $numbers . $special;

        for ($i = count($password); $i < $length; $i++) {
            $password[] = $all[random_int(0, strlen($all) - 1)];
        }

        shuffle($password);

        return implode('', $password);
    }

    public function index()
    {
        return Inertia::render('Admin/Index', [
            'users' => User::with(['role', 'projectAccesses.project'])->get(),
            'roles' => Role::with('permissions')->get(),
            'permissions' => Permission::all(),
            'projects' => Project::with('user')->whereNull('deleted_at')->get()
        ]);
    }

    public function createUser(Request $request)
    {
        $messages = [
            'email.unique' => 'E-mail already used.',
            'password.min' => 'Password must contain at least 8 characters.'
        ];

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email:rfc|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id'
        ], $messages);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role_id' => $validated['role_id']
        ]);

        return back()->with('success', 'User successfully created');
    }

    public function createRole(Request $request)
    {
        $messages = [
            'name.unique' => 'name already used.',
        ];

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles',
            'description' => 'required|string|max:255',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'description' => $validated['description']
        ]);

        return back()->with('success', 'Role successfully created');
    }

    public function deleteRole(Role $role)
    {
        if (in_array(strtolower($role->name), ['Admin', 'user'])) {
            return back()->with('error', 'Cannot delete system roles');
        }

        $role->delete();

        return back()->with('success', 'Role deleted successfully');
    }

    public function updateUserRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        $user->update(['role_id' => $validated['role_id']]);

        return back()->with('success', 'Role updated successfully');
    }

    public function updateRolePermissions(Request $request, Role $role)
    {
        try {
            Log::info('Permissions reçues:', $request->all());

            $validated = $request->validate([
                'permissions' => 'required|array',
                'permissions.*' => 'exists:permissions,id'
            ]);

            DB::beginTransaction();
            
            // Synchroniser les permissions
            $result = $role->permissions()->sync($request->permissions);
            
            DB::commit();

            // ✅ Synchroniser vers l'app web
            $this->syncRolePermissionsToWeb($role);

            return back()->with('success', 'Permissions updated successfully');
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error:', ['message' => $e->getMessage()]);
            return back()->with('error', 'Error updating permissions: ' . $e->getMessage());
        }
    }

    protected function syncRolePermissionsToWeb(Role $role)
    {
        $apiService = app(\App\Services\ApiService::class);
        
        if (!$apiService->isConnected()) {
            Log::warning('⚠️ Cannot sync permissions: agent not connected');
            return;
        }

        try {
            $permissionNames = $role->permissions->pluck('name')->toArray();
            
            Log::info('📤 Syncing role permissions to web', [
                'role' => $role->name,
                'permissions' => $permissionNames,
            ]);

            $apiService->post('/api/admin/sync-role-permissions', [
                'role_name' => $role->name,
                'permissions' => $permissionNames,
            ]);

            Log::info('✅ Role permissions synced to web');
            
        } catch (\Exception $e) {
            Log::error('❌ Failed to sync role permissions to web', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Accorder l'accès à un projet pour un utilisateur
     */
    public function grantProjectAccess(Request $request)
    {
        try {
            if (!$this->isUserAdmin()) {
                return back()->with('error', 'Unauthorized access. Administrator privileges required.');
            }

            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'project_ids' => 'required|array',
                'project_ids.*' => 'exists:projects,id',
                'access_level' => 'required|in:read,write,Admin'
            ]);

            $userId = $validated['user_id'];
            $accessLevel = $validated['access_level'];
            $projectIds = $validated['project_ids'];

            DB::transaction(function () use ($userId, $accessLevel, $projectIds) {
                foreach ($projectIds as $projectId) {
                    $existingAccess = UserProjectAccess::where('user_id', $userId)
                        ->where('project_id', $projectId)
                        ->first();

                    if ($existingAccess) {
                        if ($existingAccess->access_level !== $accessLevel) {
                            $existingAccess->update(['access_level' => $accessLevel]);
                        }
                    } else {
                        UserProjectAccess::create([
                            'user_id' => $userId,
                            'project_id' => $projectId,
                            'access_level' => $accessLevel,
                        ]);
                    }

                    Log::info('Admin - Accès projet accordé/modifié', [
                        'user_id' => $userId,
                        'project_id' => $projectId,
                        'access_level' => $accessLevel,
                        'admin_id' => auth()->id()
                    ]);
                }
            });

            return back()->with('success', 'Access granted/updated successfully');

        } catch (\Exception $e) {
            Log::error('Erreur dans AdminController::grantProjectAccess', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return back()->with('error', 'Error granting access: ' . $e->getMessage());
        }
    }

    /**
     * Révoquer l'accès à un projet pour un utilisateur
     */
    public function revokeProjectAccess(Request $request)
    {
        try {
            if (!$this->isUserAdmin()) {
                return back()->with('error', 'Access restricted.');
            }

            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'project_ids' => 'required|array',
                'project_ids.*' => 'exists:projects,id',
            ]);

            $deleted = UserProjectAccess::where('user_id', $validated['user_id'])
                ->whereIn('project_id', $validated['project_ids']) 
                ->delete();

            if ($deleted) {
                Log::info('Admin - Accès projet révoqué', [
                    'user_id' => $validated['user_id'],
                    'project_ids' => $validated['project_ids'], 
                    'admin_id' => auth()->id()
                ]);

                return back()->with('success', 'Access successfully revoked');
            } else {
                return back()->with('error', 'No access found to revoke');
            }

        } catch (\Exception $e) {
            Log::error('Erreur dans AdminController::revokeProjectAccess', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return back()->with('error', 'Error while revoking access: ' . $e->getMessage());
        }
    }

    /**
     * Obtenir les accès aux projets pour un utilisateur
     */
    public function getUserProjectAccesses($userId)
    {
        try {
            if (!$this->isUserAdmin()) {
                abort(403, 'Accès non autorisé.');
            }

            $user = User::with(['projectAccesses.project.user'])->findOrFail($userId);
            
            $accesses = $user->projectAccesses->map(function ($access) {
                return [
                    'id' => $access->id,
                    'project_id' => $access->project_id,
                    'project_name' => $access->project->name,
                    'project_owner' => $access->project->user->name,
                    'access_level' => $access->access_level,
                    'granted_at' => $access->created_at->format('d/m/Y H:i')
                ];
            });

            return Inertia::render('Admin/UserProjectAccesses', [
                'user' => $user,
                'accesses' => $accesses
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur dans AdminController::getUserProjectAccesses', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Erreur lors du chargement des accès: ' . $e->getMessage());
        }
    }

    /**
     * Obtenir tous les projets disponibles pour attribution
     */
    public function getAvailableProjects()
    {
        try {
            if (!$this->isUserAdmin()) {
                abort(403, 'Accès non autorisé.');
            }

            $projects = Project::with('user')
                ->whereNull('deleted_at')
                ->select('id', 'name', 'description', 'db_type', 'user_id')
                ->get()
                ->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'description' => $project->description,
                        'db_type' => $project->db_type,
                        'owner_name' => $project->user->name,
                        'display_name' => $project->name . ' (' . $project->user->name . ')'
                    ];
                });

            return Inertia::render('Admin/AvailableProjects', [
                'projects' => $projects
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur dans AdminController::getAvailableProjects', [
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Erreur lors du chargement des projets: ' . $e->getMessage());
        }
    }

    public function getDeletedProjects(Request $request)
    {
        try {
            if (!$this->isUserAdmin()) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Accès non autorisé. Privilèges administrateur requis.'
                    ], 403);
                }
                abort(403, 'Accès non autorisé. Privilèges administrateur requis.');
            }

            $deletedProjects = Project::onlyTrashed()
                ->with('user:id,name,email')
                ->orderBy('deleted_at', 'desc')
                ->get()
                ->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'description' => $project->description,
                        'db_type' => $project->db_type,
                        'user' => [
                            'id' => $project->user->id,
                            'name' => $project->user->name,
                            'email' => $project->user->email,
                        ],
                        'deleted_at' => $project->deleted_at ? $project->deleted_at->format('d/m/Y H:i') : null,
                        'created_at' => $project->created_at ? $project->created_at->format('d/m/Y H:i') : null
                    ];
                });

            Log::info('Admin - Projets supprimés récupérés', [
                'admin_id' => auth()->id(),
                'count' => $deletedProjects->count()
            ]);

            // ✅ Retourner JSON si demandé (axios), sinon Inertia
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'projects' => $deletedProjects
                ]);
            }

            return Inertia::render('Admin/DeletedProjects', [
                'projects' => $deletedProjects
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur dans AdminController::getDeletedProjects', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erreur lors du chargement des projets supprimés: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Erreur lors du chargement des projets supprimés: ' . $e->getMessage());
        }
    }

    public function restoreProject($id)
    {
        try {
            if (!$this->isUserAdmin()) {
                return back()->with('error', 'Accès non autorisé. Privilèges administrateur requis.');
            }

            $project = Project::withTrashed()->findOrFail($id);
            
            if (!$project->trashed()) {
                return back()->with('error', 'Ce projet n\'est pas supprimé');
            }

            $project->restore();

            Log::info('Admin - Projet restauré', [
                'project_id' => $id,
                'project_name' => $project->name,
                'project_owner' => $project->user->name,
                'admin_id' => auth()->id()
            ]);

            return back()->with('success', 'Projet restauré avec succès');
            
        } catch (\Exception $e) {
            Log::error('Erreur dans AdminController::restoreProject', [
                'id' => $id,
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return back()->with('error', 'Erreur lors de la restauration du projet: ' . $e->getMessage());
        }
    }

    public function forceDeleteProject($id)
    {
        try {
            if (!$this->isUserAdmin()) {
                return back()->with('error', 'Accès non autorisé. Privilèges administrateur requis.');
            }

            $project = Project::withTrashed()->findOrFail($id);
            
            $dbDescriptionsCount = DbDescription::where('project_id', $project->id)->count();
            
            if ($dbDescriptionsCount > 0) {
                return back()->with('error', "Impossible de supprimer définitivement ce projet car il contient {$dbDescriptionsCount} base(s) de données associée(s).");
            }

            $projectName = $project->name;
            $projectOwner = $project->user->name;
            $project->forceDelete();

            Log::warning('Admin - Projet supprimé définitivement', [
                'project_id' => $id,
                'project_name' => $projectName,
                'project_owner' => $projectOwner,
                'admin_id' => auth()->id()
            ]);

            return back()->with('success', 'Projet supprimé définitivement');
            
        } catch (\Exception $e) {
            Log::error('Erreur dans AdminController::forceDeleteProject', [
                'id' => $id,
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return back()->with('error', 'Erreur lors de la suppression définitive: ' . $e->getMessage());
        }
    }

    public function getProjectStats()
    {
        try {
            if (!$this->isUserAdmin()) {
                abort(403, 'Accès non autorisé.');
            }

            $stats = [
                'total_projects' => Project::withTrashed()->count(),
                'active_projects' => Project::count(),
                'deleted_projects' => Project::onlyTrashed()->count(),
                'projects_by_user' => Project::withTrashed()
                    ->select('user_id', DB::raw('count(*) as total'))
                    ->with('user:id,name')
                    ->groupBy('user_id')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'user_name' => $item->user->name,
                            'project_count' => $item->total
                        ];
                    }),
                'projects_by_type' => Project::withTrashed()
                    ->select('db_type', DB::raw('count(*) as total'))
                    ->groupBy('db_type')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->db_type => $item->total];
                    })
            ];

            return Inertia::render('Admin/ProjectStats', [
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur dans AdminController::getProjectStats', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return back()->with('error', 'Erreur lors du chargement des statistiques: ' . $e->getMessage());
        }
    }

    private function isUserAdmin()
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }

        if ($user->role && $user->role->name === 'Admin') {
            return true;
        }

        if (isset($user->role) && $user->role === 'Admin') {
            return true;
        }

        if ($user->role && $user->role->permissions()->where('name', 'manage_projects')->exists()) {
            return true;
        }

        return false;
    }
}
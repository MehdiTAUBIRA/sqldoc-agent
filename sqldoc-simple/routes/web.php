<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseStructureController;
use App\Http\Controllers\FunctionController;
use App\Http\Controllers\ProcedureController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReleaseApiController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\SpecificSearchController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\TriggerController;
use App\Http\Controllers\ViewController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AgentAuthController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Inertia\Inertia;


Route::get('/login', function () {
    if (agentConnected()) {
        return redirect()->route('user.login');
    }
    return redirect()->route('agent.login');
})->name('login');

Route::get('/register', function () {
    return redirect('/');  
})->name('register');

Route::middleware('web')->group(function () {
    // Page de connexion de l'agent (formulaire token + API URL)
    Route::get('/agent/login', [AgentAuthController::class, 'showAgentLoginForm'])
        ->name('agent.login');
    
    // Traitement de la connexion agent
    Route::post('/agent/login', [AgentAuthController::class, 'agentLogin'])
        ->name('agent.login.submit');
});



Route::middleware(['web', 'agent.auth'])->group(function () {
    // Page de login utilisateur (email + password)
    Route::get('/user/login', [AgentAuthController::class, 'showUserLoginForm'])
        ->name('user.login');
    
    // Traitement du login utilisateur
    Route::post('/user/login', [AgentAuthController::class, 'userLogin'])
        ->name('user.login.submit');

    Route::post('/agent/logout', [AgentAuthController::class, 'agentLogout'])
        ->name('agent.logout');


    Route::get('/api/pending-sync/count', function() {
        return response()->json([
            'count' => \App\Models\PendingSync::whereNull('synced_at')->count()
        ]);
    });

    Route::post('/api/sync-now', function() {
        try {
            \App\Jobs\SyncPendingChangesJob::dispatchSync();
            
            $pending = \App\Models\PendingSync::whereNull('synced_at')->count();
            
            return response()->json([
                'message' => 'Sync started',
                'pending_count' => $pending,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Sync failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    });

    Route::post('/api/sync-trigger', function() {
        try {
            // Exécuter immédiatement (pas de queue)
            (new \App\Jobs\SyncPendingChangesJob())->handle();
            
            $pending = \App\Models\PendingSync::whereNull('synced_at')->count();
            
            return response()->json([
                'message' => 'Sync completed',
                'pending_count' => $pending,
                'timestamp' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Sync trigger failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Sync failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    });
});


Route::get('/', function () {
    
    if (!agentConnected()) {
        return redirect()->route('agent.login');
    }
    
    
    if (!auth()->check()) {
        return redirect()->route('user.login');
    }
    
    
    return redirect()->route('dashboard');
});



Route::middleware(['auth', 'role:Admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('admin');
    Route::post('/admin/users', [AdminController::class, 'createUser']);
    Route::post('/admin/users/{user}/role', [AdminController::class, 'updateUserRole']);
    Route::put('/admin/roles/{role}/permissions', [AdminController::class, 'updateRolePermissions']);

    //creation de role et delete role
    Route::post('/admin/roles', [AdminController::class, 'createRole'])->name('admin.roles.create');
    Route::delete('/admin/roles/{role}', [AdminController::class, 'deleteRole'])->name('admin.roles.delete');

    // Nouvelles routes pour la gestion des accès aux projets
    Route::get('/admin/projects/available', [AdminController::class, 'getAvailableProjects'])->name('admin.projects.available');
    Route::get('/admin/users/{user}/project-accesses', [AdminController::class, 'getUserProjectAccesses'])->name('admin.users.project-accesses');
    Route::post('/admin/project-access/grant', [AdminController::class, 'grantProjectAccess'])->name('admin.project-access.grant');
    Route::post('/admin/project-access/revoke', [AdminController::class, 'revokeProjectAccess'])->name('admin.project-access.revoke');

    // Routes existantes pour les projets supprimés
    Route::get('/projects/deleted', [AdminController::class, 'getDeletedProjects'])->name('admin.projects.deleted');
    Route::post('/projects/{id}/restore', [AdminController::class, 'restoreProject'])->name('admin.projects.restore');
    Route::get('/projects/stats', [AdminController::class, 'getProjectStats'])->name('admin.projects.stats');
    Route::get('/admin/projects/all', [AdminController::class, 'getAllProjects']);
    Route::get('/projects/{id}/deletion-preview', [ProjectController::class, 'getProjectDeletionPreview']);
    Route::delete('/projects/{id}/force', [ProjectController::class, 'forceDeleteProject']);
});



Route::middleware(['web', 'agent.auth', 'auth'])->group(function () {
    
    
    // Déconnecter l'utilisateur (garde l'agent connecté)
    Route::post('/user/logout', [AgentAuthController::class, 'userLogout'])
        ->name('user.logout');
    

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');


    
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/dashboard-data', [DashboardController::class, 'index']);

    // GESTION DES PROJETS

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}/connect', [ProjectController::class, 'connect'])->name('projects.connect');
    Route::post('/projects/{project}/connect', [ProjectController::class, 'handleConnect'])->name('projects.handle-connect');
    Route::post('/disconnect', [ProjectController::class, 'disconnect'])->name('disconnect');
    Route::get('/projects/{project}/open', [ProjectController::class, 'open'])->name('projects.open');
    Route::post('/projects/{project}/test-connection', [ProjectController::class, 'testConnection'])->name('projects.test-connection');
    
    // Soft delete et restauration
    Route::delete('/projects/{id}/soft', [ProjectController::class, 'softDelete'])->name('projects.soft-delete');
    Route::post('/projects/{id}/restore', [ProjectController::class, 'restore'])->name('projects.restore');
    Route::get('/projects/deleted', [ProjectController::class, 'deleted'])->name('projects.deleted');
    Route::put('/projects/{id}', [ProjectController::class, 'update'])->name('projects.update');
    Route::get('/projects/{id}/deletion-preview', [ProjectController::class, 'getProjectDeletionPreview']);
    Route::delete('/projects/{id}/force', [ProjectController::class, 'forceDeleteProject']);
    

    // DATABASE STRUCTURE
    
    Route::get('/database-structure', [DatabaseStructureController::class, 'index']);
    Route::post('/database-structure/refresh', [DatabaseStructureController::class, 'refresh'])->name('database.structure.refresh');
    Route::delete('/database-structure/cache', [DatabaseStructureController::class, 'clearCache'])->name('database.structure.clear-cache');
    Route::get('/database-structure/cache-status', [DatabaseStructureController::class, 'cacheStatus'])->name('database.structure.cache-status');

    // ROUTES AVEC PERMISSIONS DE LECTURE
    
    Route::middleware('project.permissions:read')->group(function () {
        
        // TABLES
        Route::get('/table/{tableName}/details', [TableController::class, 'details'])->name('table.details');
        Route::get('/table/{tableName}/column/{columnName}/audit-logs', [TableController::class, 'getAuditLogs'])->name('table.audit.logs');

        // VIEWS
        Route::get('/view/{viewName}/details', [ViewController::class, 'details'])->name('view.details');
        Route::get('/view/{viewName}/column/{columnName}/audit-logs', [ViewController::class, 'getAuditLogs'])->name('view.audit.logs');

        // FUNCTIONS
        Route::get('/function/{functionName}/details', [FunctionController::class, 'details'])->name('function.details');
        Route::get('/function/{functionName}/function/{parameterName}/audit-logs', [FunctionController::class, 'getAuditLogs'])->name('function.audit.logs');

        // PROCEDURES
        Route::get('/procedure/{procedureName}/details', [ProcedureController::class, 'details'])->name('procedure.details');
        Route::get('/procedure/{procedureName}/parameter/{parameterName}/audit-logs', [ProcedureController::class, 'getAuditLogs'])->name('procedure.parameter.audit-logs');

        // TRIGGERS
        Route::get('/trigger/{triggerName}/details', [TriggerController::class, 'details'])->name('trigger.details');

        // RELEASES
        Route::prefix('releases')->name('releases.')->group(function () {
            Route::get('/', [ReleaseController::class, 'index'])->name('index');
            Route::get('/{id}', [ReleaseController::class, 'show'])->name('show');
        });

        // RECHERCHE SPÉCIFIQUE
        Route::get('/specific-search', [SpecificSearchController::class, 'specificSearch'])->name('specific.search');
    });

    // ROUTES AVEC PERMISSIONS D'ÉCRITURE
    
    Route::middleware('project.permissions:write')->group(function () {
        
        // TABLES
        Route::post('/table/{tableName}/save-description', [TableController::class, 'saveDescription'])->name('table.savedescription');
        Route::post('/table/{tableName}/column/{columnName}/description', [TableController::class, 'updateColumnDescription'])->name('table.column.updateDescription');
        Route::post('/table/{tableName}/column/{columnName}/possible-values', [TableController::class, 'updateColumnPossibleValues'])->name('table.column.updatePossibleValues');
        Route::post('/table/{tableName}/column/{columnName}/properties', [TableController::class, 'updateColumnProperties'])->name('table.column.properties');
        Route::post('/table/{tableName}/column/{columnName}/release', [TableController::class, 'updateColumnRelease']);
        Route::post('/table/{tableName}/column/add', [TableController::class, 'addColumn'])->name('table.column.add');
        Route::post('/table/{tableName}/relation/add', [TableController::class, 'addRelation'])->name('table.relation.add');

        // VIEWS
        Route::post('/view/{viewName}/save-description', [ViewController::class, 'saveDescription'])->name('view.saveDescription');
        Route::post('/view/{viewName}/column/{columnName}/description', [ViewController::class, 'saveColumnDescription'])->name('view.column.saveDescription');
        Route::post('/view/{viewName}/save-all', [ViewController::class, 'saveAll'])->name('view.saveAll');
        Route::post('/view/{viewName}/save-structure', [ViewController::class, 'saveStructure'])->name('view.saveStructure');
        Route::post('/view/{viewName}/column/{columnName}/description', [ViewController::class, 'updateColumnDescription'])->name('view.column.updateDescription');
        Route::post('/view/{viewName}/column/{columnName}/rangevalues', [ViewController::class, 'updateColumnRangeValues'])->name('view.column.updateRangeValues');
        Route::post('/view/{viewName}/column/{columnName}/release', [ViewController::class, 'updateColumnRelease']);

        // FUNCTIONS
        Route::post('/function/{functionName}/description', [FunctionController::class, 'saveDescription'])->name('function.saveDescription');
        Route::post('/function-parameter/{parameterId}/update-description', [FunctionController::class, 'saveParameterDescription'])->name('function.parameter.updateDescription');
        Route::post('/function/{functionName}/description', [FunctionController::class, 'updateDescription'])->name('function.update-description');
        Route::post('/function/{functionName}/function/{parameterId}/description', [FunctionController::class, 'updateParameterDescription'])->name('function.update-parameter-description');
        Route::post('/function/{functionName}/function/{parameterName}/range-values', [FunctionController::class, 'updateParameterRangeValues'])->name('function.update-parameter-range-values');
        Route::post('/function/{functionName}/function/{parameterName}/release', [FunctionController::class, 'updateParameterRelease'])->name('function.update-parameter-release');

        // PROCEDURES
        Route::post('/procedure/{procedureName}/description', [ProcedureController::class, 'saveDescription'])->name('procedure.saveDescription');
        Route::post('/procedure-parameter/{parameterId}/update-description', [ProcedureController::class, 'saveParameterDescription'])->name('procedure.parameter.updateDescription');
        Route::post('/procedure/{procedureName}/save-all', [ProcedureController::class, 'saveAll'])->name('procedure.saveAll');
        Route::post('/procedure/{procedureName}/parameter/{parameterName}/description', [ProcedureController::class, 'updateColumnDescription'])->name('procedure.column.updateDescription');
        Route::post('/procedure/{procedureName}/parameter/{parameterName}/rangevalues', [ProcedureController::class, 'updateColumnRangeValues'])->name('procedure.column.updateRangeValues');
        Route::post('/procedure/{procedureName}/parameter/{parameterName}/release', [ProcedureController::class, 'updateParameterRelease'])->name('procedure.parameter.updateRelease');
        Route::post('/procedure/{procedureName}/description', [ProcedureController::class, 'updateDescription'])->name('procedure.update-description');

        // TRIGGERS
        Route::post('/trigger/{triggerName}/description', [TriggerController::class, 'saveDescription'])->name('trigger.description');
        Route::post('/trigger/{triggerName}/save-all', [TriggerController::class, 'saveAll'])->name('trigger.saveall');

        // RELEASES - MODIFICATION
        Route::prefix('releases')->name('releases.')->group(function () {
            Route::get('/create', [ReleaseController::class, 'create'])->name('create');
            Route::post('/', [ReleaseController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [ReleaseController::class, 'edit'])->name('edit');
            Route::post('/{id}', [ReleaseController::class, 'update'])->name('update');
            Route::delete('/{id}', [ReleaseController::class, 'destroy'])->name('destroy');
        });
    });

    // ROUTES API AVEC PERMISSIONS
 
    Route::prefix('api')->group(function () {
        
        // API LECTURE
        Route::middleware('project.permissions:read')->group(function () {
            Route::get('/table/{tableName}/details', [TableController::class, 'apiDetails'])->name('api.table.details');
            Route::get('/table-id/{tableName}', [TableController::class, 'getTableId']);
            Route::get('/view/{viewName}/details', [ViewController::class, 'details'])->name('api.view.details');
            Route::get('/function/{functionName}/details', [FunctionController::class, 'apiDetails'])->name('api.function.details');
            Route::get('/procedure/{procedureName}/details', [ProcedureController::class, 'details'])->name('api.procedure.details');
            Route::get('/trigger/{triggerName}/details', [TriggerController::class, 'details'])->name('api.trigger.details');
            Route::get('/releases', [ReleaseApiController::class, 'index'])->name('api.releases.index');
            Route::get('/releases/all', [ReleaseApiController::class, 'getAllVersions'])->name('api.releases.all');
        });

        // API ÉCRITURE
        Route::middleware('project.permissions:write')->group(function () {
            Route::post('/table/{tableName}/column/{columnName}/release', [TableController::class, 'updateColumnRelease']);
            Route::post('/releases', [ReleaseApiController::class, 'store'])->name('api.releases.store');
            Route::post('/releases/{id}', [ReleaseApiController::class, 'update'])->name('api.releases.update');
            Route::delete('/releases/{id}', [ReleaseApiController::class, 'destroy'])->name('api.releases.destroy');
            Route::post('/releases/assign-to-column', [ReleaseApiController::class, 'assignReleaseToColumn'])->name('api.releases.assign');
            Route::post('/releases/remove-from-column', [ReleaseApiController::class, 'removeReleaseFromColumn'])->name('api.releases.remove');
        });
    });
});

<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        
        Inertia::share([
            'permissions' => function () {
                return session('permissions', []);
            },
            
            'appName' => config('app.name'),
            'appVersion' => config('app.version'),
            
            'auth' => function () {
                return [
                    'user' => Auth::user() ? [
                        'id' => Auth::user()->id,
                        'name' => Auth::user()->name,
                        'email' => Auth::user()->email,
                        'role' => Auth::user()->role ?? 'user',
                    ] : null,
                ];
            },

            'currentProject' => function () {
                return session('current_project');
            },
            
            'navigationData' => function () {
                if (!Auth::check()) {
                    return null;
                }
                
                if (!$this->shouldLoadNavigation()) {
                    return null;
                }
                
                $dbId = session('current_db_id');
                
                if (!$dbId) {
                    return [
                        'tables' => [],
                        'views' => [],
                        'functions' => [],
                        'procedures' => [],
                        'triggers' => [],
                        'metadata' => [
                            'generated_at' => now()->toISOString(),
                            'execution_time_ms' => 0,
                            'total_objects' => 0,
                            'message' => 'Aucune base de données sélectionnée'
                        ]
                    ];
                }
                
                try {
                    $cacheKey = "simple_navigation_" . Auth::id() . "_{$dbId}";
                    
                    return Cache::remember($cacheKey, 1800, function () use ($dbId) {
                        Log::info('AppServiceProvider - Génération navigation depuis cache', [
                            'user_id' => Auth::id(),
                            'db_id' => $dbId
                        ]);
                        
                        return $this->buildSimpleNavigation($dbId);
                    });
                    
                } catch (\Exception $e) {
                    Log::warning('AppServiceProvider - Erreur lors du chargement de la navigation', [
                        'user_id' => Auth::id(),
                        'db_id' => $dbId,
                        'error' => $e->getMessage(),
                        'route' => request()->route() ? request()->route()->getName() : 'unknown'
                    ]);
                    
                    return [
                        'tables' => [],
                        'views' => [],
                        'functions' => [],
                        'procedures' => [],
                        'triggers' => [],
                        'metadata' => [
                            'generated_at' => now()->toISOString(),
                            'execution_time_ms' => 0,
                            'total_objects' => 0,
                            'error' => 'Erreur lors du chargement de la navigation'
                        ]
                    ];
                }
            },
            
            'flash' => function () {
                return [
                    'success' => session('success'),
                    'error' => session('error'),
                    'warning' => session('warning'),
                    'info' => session('info'),
                ];
            },
        ]);

        Carbon::serializeUsing(function ($carbon) {
            return $carbon->format('Y-m-d H:i:s');
        });
    }

    private function buildSimpleNavigation($dbId)
    {
        $startTime = microtime(true);
        
        $tables = DB::table('table_description')
            ->where('dbid', $dbId)
            ->select('id', 'tablename as name', 'description')
            ->orderBy('tablename')
            ->get();
            
        $views = DB::table('view_description')
            ->where('dbid', $dbId)
            ->select('id', 'viewname as name', 'description')
            ->orderBy('viewname')
            ->get();
            
        $functions = DB::table('function_description')
            ->where('dbid', $dbId)
            ->select('id', 'functionname as name', 'description')
            ->orderBy('functionname')
            ->get();
            
        $procedures = DB::table('ps_description')
            ->where('dbid', $dbId)
            ->select('id', 'psname as name', 'description')
            ->orderBy('psname')
            ->get();
            
        $triggers = DB::table('trigger_description')
            ->where('dbid', $dbId)
            ->select('id', 'triggername as name', 'description')
            ->orderBy('triggername')
            ->get();
            
        $executionTime = (microtime(true) - $startTime) * 1000;
        
        return [
            'tables' => $tables,
            'views' => $views,
            'functions' => $functions,
            'procedures' => $procedures,
            'triggers' => $triggers,
            'metadata' => [
                'generated_at' => now()->toISOString(),
                'execution_time_ms' => round($executionTime, 2),
                'total_objects' => $tables->count() + $views->count() + $functions->count() + $procedures->count() + $triggers->count(),
                'db_id' => $dbId
            ]
        ];
    }

    private function shouldLoadNavigation(): bool
    {
        $request = request();
        
        if ($request->is('api/*')) {
            return false;
        }
        
        // ✅ Ajouter les routes agent
        if ($request->is('login*') || 
            $request->is('register*') || 
            $request->is('password/*') || 
            $request->is('email/*') ||
            $request->is('agent/*') ||      // ✅ Ajouté
            $request->is('user/login*')) {  // ✅ Ajouté
            return false;
        }
        
        if ($request->is('/') && !Auth::check()) {
            return false;
        }
        
        if ($request->is('404') || $request->is('500')) {
            return false;
        }
        
        // ✅ Ajouter les routes agent dans les exclusions
        $excludedRoutes = [
            'login',
            'register',
            'password.request',
            'password.email',
            'password.reset',
            'verification.notice',
            'verification.verify',
            'verification.send',
            'agent.login',           // ✅ Ajouté
            'agent.login.submit',    // ✅ Ajouté
            'agent.reconnect',       // ✅ Ajouté
            'agent.logout',          // ✅ Ajouté
            'user.login',            // ✅ Ajouté
            'user.login.submit',     // ✅ Ajouté
            'user.logout',           // ✅ Ajouté
        ];
        
        $currentRoute = $request->route() ? $request->route()->getName() : null;
        if ($currentRoute && in_array($currentRoute, $excludedRoutes)) {
            return false;
        }
        
        return true;
    }
}
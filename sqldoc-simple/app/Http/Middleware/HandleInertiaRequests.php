<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     *
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    public function version(Request $request)
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function share(Request $request)
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role->name,
                ] : null,
            ],
            'flash' => function () use ($request) {
                return [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                    'warning' => $request->session()->get('warning'),
                    'info' => $request->session()->get('info'),
                ];
            },
            'showingMobileMenu' => false,
            'domain' => config('app.domain'),
            'appName' => config('app.name', 'Laravel'),
            'appVersion' => config('app.version', null),

            'currentProject' => fn() => session('current_project'),

            'navigationData' => function () use ($request) {
                    
                    if (!$request->user()) {
                        return [
                            'tables' => [],
                            'views' => [],
                            'functions' => [],
                            'procedures' => [],
                            'triggers' => [],
                            'metadata' => [
                                'generated_at' => now()->toIso8601String(),
                                'execution_time_ms' => 0,
                                'total_objects' => 0,
                                'message' => 'User not authenticated'
                            ]
                        ];
                    }
                    
                    
                    $currentDbId = session('current_db_id');
                    
                    if (!$currentDbId) {
                        return [
                            'tables' => [],
                            'views' => [],
                            'functions' => [],
                            'procedures' => [],
                            'triggers' => [],
                            'metadata' => [
                                'generated_at' => now()->toIso8601String(),
                                'execution_time_ms' => 0,
                                'total_objects' => 0,
                                'message' => 'No database selected'
                            ]
                        ];
                    }
                    
                    try {
                        $startTime = microtime(true);
                        
                        $tables = DB::table('table_description')
                            ->where('dbid', $currentDbId)
                            ->select('id', 'tablename as name', 'description')
                            ->orderBy('tablename')
                            ->get();
                            
                        $views = DB::table('view_description')
                            ->where('dbid', $currentDbId)
                            ->select('id', 'viewname as name', 'description')
                            ->orderBy('viewname')
                            ->get();
                            
                        $functions = DB::table('function_description')
                            ->where('dbid', $currentDbId)
                            ->select('id', 'functionname as name', 'description')
                            ->orderBy('functionname')
                            ->get();
                            
                        $procedures = DB::table('ps_description')
                            ->where('dbid', $currentDbId)
                            ->select('id', 'psname as name', 'description')
                            ->orderBy('psname')
                            ->get();
                            
                        $triggers = DB::table('trigger_description')
                            ->where('dbid', $currentDbId)
                            ->select('id', 'triggername as name', 'description')
                            ->orderBy('triggername')
                            ->get();
                            
                        $executionTime = (microtime(true) - $startTime) * 1000;
                        
                        Log::info('✅ navigationData chargée', [
                            'db_id' => $currentDbId,
                            'tables' => $tables->count(),
                            'views' => $views->count(),
                            'functions' => $functions->count(),
                            'procedures' => $procedures->count(),
                            'triggers' => $triggers->count(),
                        ]);
                        
                        return [
                            'tables' => $tables,
                            'views' => $views,
                            'functions' => $functions,
                            'procedures' => $procedures,
                            'triggers' => $triggers,
                            'metadata' => [
                                'generated_at' => now()->toIso8601String(),
                                'execution_time_ms' => round($executionTime, 2),
                                'total_objects' => $tables->count() + $views->count() + $functions->count() + $procedures->count() + $triggers->count(),
                                'db_id' => $currentDbId
                            ]
                        ];
                        
                    } catch (\Exception $e) {
                        Log::error('❌ Erreur chargement navigationData', [
                            'error' => $e->getMessage(),
                            'db_id' => $currentDbId,
                            'trace' => $e->getTraceAsString()
                        ]);
                        
                        return [
                            'tables' => [],
                            'views' => [],
                            'functions' => [],
                            'procedures' => [],
                            'triggers' => [],
                            'metadata' => [
                                'generated_at' => now()->toIso8601String(),
                                'execution_time_ms' => 0,
                                'total_objects' => 0,
                                'error' => 'Failed to load: ' . $e->getMessage()
                            ]
                        ];
                    }
                },
                
                'permissions' => fn() => [],
            ]);
            
    }
}

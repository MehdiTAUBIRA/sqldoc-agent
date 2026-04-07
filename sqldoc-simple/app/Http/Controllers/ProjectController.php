<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\HasProjectPermissions;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\DbDescription;
use App\Services\DatabaseStructureService;
use App\Models\UserProjectAccess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProjectController extends Controller
{
    use HasProjectPermissions, AuthorizesRequests;

    public function index()
    {
        $userId = auth()->id();
        $user = auth()->user();
        
        $ownedProjects = Project::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'db_type' => $project->db_type,
                    'is_owner' => true,
                    'access_level' => 'Admin',
                    'owner_name' => auth()->user()->name,
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at
                ];
            });


        $sharedProjects = collect();
        
        $userProjectAccesses = \App\Models\UserProjectAccess::where('user_id', $userId)
            ->with(['project' => function($query) {
                $query->whereNull('deleted_at')->with('user:id,name');
            }])
            ->get();
        
        foreach ($userProjectAccesses as $access) {
            if ($access->project) {
                $sharedProjects->push([
                    'id' => $access->project->id,
                    'name' => $access->project->name,
                    'description' => $access->project->description,
                    'db_type' => $access->project->db_type,
                    'is_owner' => false,
                    'access_level' => $access->access_level,
                    'owner_name' => $access->project->user->name,
                    'created_at' => $access->project->created_at,
                    'updated_at' => $access->project->updated_at,
                    'shared_at' => $access->created_at
                ]);
            }
        }
        

        $allProjects = $ownedProjects->concat($sharedProjects)->sortBy('name');
        
        $deletedProjects = [];
        if ($this->isUserAdmin($user)) {
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
                })
                ->values()
                ->all();
        }
        
        Log::info('Projects récupérés pour l\'utilisateur', [
            'user_id' => $userId,
            'owned_count' => $ownedProjects->count(),
            'shared_count' => $sharedProjects->count(),
            'total_count' => $allProjects->count(),
            'deleted_count' => count($deletedProjects)
        ]);
        
        return Inertia::render('Projects/Index', [
            'projects' => $allProjects->values(),
            'deletedProjects' => $deletedProjects, 
            'stats' => [
                'owned' => $ownedProjects->count(),
                'shared' => $sharedProjects->count(),
                'total' => $allProjects->count(),
                'deleted' => count($deletedProjects) 
            ],
        ]);
    }


    private function isUserAdmin($user = null)
    {
        if (!$user) {
            $user = auth()->user();
        }
        
        if (!$user) {
            return false;
        }

        
        if ($user->role && $user->role->name === 'Admin') {
            return true;
        }

        
        if ($user->role && $user->role->permissions()->where('name', 'manage_projects')->exists()) {
            return true;
        }

        return false;
    }

    public function store(Request $request)
    {
        $messages = [
            'name.unique' => 'You already have a project with this name.',
        ];

        $validated = $request->validate([
            'name' => [
                        'required','string','max:255',
                        Rule::unique('projects')->where(function ($query) use ($request) {
                            return $query->where('user_id', $request->user()->id);
                        }),
            ],
            'db_type' => 'required|in:sqlserver,mysql,pgsql',
            'description' => 'nullable|string|max:1000'
            
        ], $messages);

        $project = $request->user()->projects()->create($validated);

        return redirect()->route('projects.connect', $project->id);
    }

    public function create()
    {
        return Inertia::render('Projects/Create', [
            'dbTypes' => [
                'mysql' => 'MySQL',
                'sqlserver' => 'SQL Server',
                'pgsql' => 'PostgreSQL' 
            ]
        ]);
    }

    public function connect(Project $project)
    {
        $userCanAccess = $this->checkUserProjectAccess($project);
        
        if (!$userCanAccess['allowed'] || !$userCanAccess['is_owner']) {
            return redirect()->route('projects.index')
                ->with('error', 'Only the project owner can configure database connections.');
        }

        return Inertia::render('Projects/Connect', [
            'project' => $project
        ]);
    }

    public function handleConnect(Request $request, Project $project)
    {
        set_time_limit(5600);

    Log::info('=== DÉBUT handleConnect ===', [
        'project_id' => $project->id,
        'user_id' => auth()->id()
    ]);

    $userCanAccess = $this->checkUserProjectAccess($project);
    if (!$userCanAccess['allowed'] || !$userCanAccess['is_owner']) {
        return redirect()->back()->with('error', 'Only the project owner can configure database connections.');
    }

    if (agentConnected()) {
        $apiService = app(\App\Services\ApiService::class);
        try {
            $response = $apiService->get('/api/check-project-limit');
            if (isset($response['limit_reached']) && $response['limit_reached'] === true) {
                return back()->with('error', $response['message'] ?? 'Project limit reached. Please upgrade your plan.');
            }
        } catch (\Exception $e) {
            if (str_starts_with($e->getMessage(), 'LIMIT_REACHED')) {
                $limitMessage = str_replace('LIMIT_REACHED: ', '', $e->getMessage());
                return back()->with('error', $limitMessage);
            }
        }
    }

    try {
        // Validation
        $rules = [
            'server' => 'required|string',
            'database' => 'required|string',
            'description' => 'nullable|string|max:1000',
            'port' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ];

        if ($project->db_type === 'sqlserver') {
            $rules['authMode'] = 'required|in:windows,sql';
            $rules['username'] = 'required_if:authMode,sql';
            $rules['password'] = 'required_if:authMode,sql';
        } else {
            $rules['port'] = 'required|numeric|min:1|max:65535';
            $rules['username'] = 'required|string';
            $rules['password'] = 'required|string';
        }

        $validated = $request->validate($rules);

        Log::info('Validation réussie', [
            'project_id' => $project->id,
            'db_type' => $project->db_type,
            'server' => $validated['server'],
            'database' => $validated['database'],
            'authMode' => $validated['authMode'] ?? 'N/A'
        ]);

        // Configuration de connexion
        $driver = $this->getDriverFromDbType($project->db_type);
        $config = [
            'driver' => $driver,
            'host' => $validated['server'],
            'database' => $validated['database'],
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ];

        if ($project->db_type === 'sqlserver') {
                $port = !empty($validated['port']) ? (int)$validated['port'] : 1433;

                // Configuration de base
                $config['port'] = $port;
                $config['charset'] = 'utf8';
                $config['prefix'] = '';
                $config['prefix_indexes'] = true;

                // Authentification
                if ($validated['authMode'] === 'windows') {
                    // Windows Authentication - ne pas définir username/password
                    $config['trust_server_certificate'] = true;
                } else {
                    // SQL Server Authentication
                    $config['username'] = $validated['username'];
                    $config['password'] = $validated['password'];
                }

                // ✅ OPTIONS CRITIQUES pour ODBC Driver 17/18
                $config['encrypt'] = 'no';
                $config['trust_server_certificate'] = 'yes';
                $config['TrustServerCertificate'] = 'yes';
                
                // ✅ OPTIONS PDO - UNIQUEMENT celles supportées par sqlsrv
                $config['options'] = [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    // NE PAS mettre PDO::ATTR_TIMEOUT pour SQL Server - pas supporté
                ];

                Log::info('Configuration SQL Server', [
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'database' => $config['database'],
                    'auth_mode' => $validated['authMode'],
                    'encrypt' => $config['encrypt'],
                ]);

            } else {
                // MySQL / PostgreSQL
                $config['port'] = $validated['port'];
                $config['username'] = $validated['username'];
                $config['password'] = $validated['password'];
                $config['charset'] = 'utf8';
                $config['prefix'] = '';
                $config['prefix_indexes'] = true;
                
                // ✅ ATTR_TIMEOUT fonctionne pour MySQL/PostgreSQL
                $config['options'] = [\PDO::ATTR_TIMEOUT => 30];

                if ($project->db_type === 'pgsql') {
                    $config['schema'] = 'public';
                    $config['sslmode'] = 'prefer';
                }
            }

        // Définir la connexion dynamique
        $connectionName = "project_{$project->id}";
        Config::set("database.connections.{$connectionName}", $config);

        Log::info('Tentative de connexion à la base', ['connection_name' => $connectionName, 'driver' => $driver]);

        try {
            $pdo = DB::connection($connectionName)->getPdo();
            Log::info('Connexion PDO établie avec succès');

            $testQuery = $this->getTestQuery($project->db_type, $validated['database']);
            $result = DB::connection($connectionName)->select($testQuery);

            if (empty($result)) {
                DB::purge($connectionName);
                throw new \Exception("Database '{$validated['database']}' does not exist or is not accessible.");
            }

            Log::info('Connexion réussie et base de données vérifiée');

        } catch (\PDOException $e) {
            $this->cleanupConnection($connectionName);
            $errorMessage = $this->analyzePDOException($e, $project->db_type, $validated);
            Log::error('Erreur PDO', ['error' => $e->getMessage()]);
            return \Inertia\Inertia::render('Projects/Connect', ['project' => $project, 'flash' => ['error' => $errorMessage]]);
        }

            $webUserId = session('web_user_id') ?? auth()->id();
            
            // Sauvegarde DbDescription
            $dbDescription = DbDescription::updateOrCreate(
                ['project_id' => $project->id, 'user_id' => $webUserId],
                ['dbname' => $validated['database'], 'description' => $validated['description'] ?? null]
            );

            session(['current_db_id' => $dbDescription->id]);

            // Extraction de la structure
            try {
                $databaseStructureService = new DatabaseStructureService();
                $databaseStructureService->extractAndSaveAllStructures($connectionName, $dbDescription->id);
                Log::info('Structure extraite avec succès');
            } catch (\Exception $structureException) {
                Log::error('❌ Erreur extraction structure', [
                    'error' => $structureException->getMessage(),
                    'file' => $structureException->getFile(),
                    'line' => $structureException->getLine(),
                    'trace' => $structureException->getTraceAsString()
                ]);
            }

            Log::info('🎯 CHECKPOINT 1 - Après extraction');
            Log::info('🎯 CHECKPOINT 2 - agentConnected() = ' . (agentConnected() ? 'TRUE' : 'FALSE'));
            Log::info('🎯 CHECKPOINT 3 - $dbDescription->id = ' . $dbDescription->id);

            Log::info('🔍 CHECKPOINT SYNC - avant agentConnected()');

            try {
                    if (agentConnected()) {
                        Log::info('🔍 AVANT handle()');
                        DB::table('sync_mappings')
                            ->whereIn('type', ['table', 'view', 'function', 'procedure', 'trigger'])
                            ->delete();
                        
                        (new \App\Jobs\SyncProjectToWebJob($dbDescription->id))->handle();
                         Log::info('🔍 APRÈS handle() - pas d exception');
                        Log::info('✅ Job de synchronisation dispatché avec succès');
                        
                    } else {
                        Log::warning('⚠️ Agent non connecté, synchronisation ignorée');
                    }
                } catch (\Throwable $syncException) {
                    if (str_starts_with($syncException->getMessage(), 'LIMIT_REACHED')) {

                        $limitMessage = str_replace('LIMIT_REACHED: ', '', $syncException->getMessage());
                        Log::warning('🚫 Limite atteinte lors de la sync', ['message' => $limitMessage]);
                        
                        // Supprimer le DbDescription créé localement
                        try {
                            $dbDescription->delete();
                            Log::info('🗑️ DbDescription local supprimé suite à la limite atteinte');
                        } catch (\Exception $deleteException) {
                            Log::error('Erreur suppression DbDescription local', [
                                'error' => $deleteException->getMessage()
                            ]);
                        }
                        
                        return back()->with('error', $limitMessage);
                    }
                    
                    Log::error('❌ Erreur lors de la synchronisation (non-bloquant)', [
                        'error' => $syncException->getMessage(),
                    ]);
                }

            // Sauvegarde des infos de connexion cryptées
            try {
                $encryptedConfig = [
                    'driver' => $config['driver'],
                    'host' => encrypt($config['host']),
                    'database' => encrypt($config['database']),
                    'port' => isset($config['port']) ? encrypt($config['port']) : null,
                    'username' => encrypt($config['username']),
                    'password' => encrypt($config['password']),
                ];
                $project->update(['connection_info' => json_encode($encryptedConfig)]);
            } catch (\Exception $e) {
                Log::warning('Erreur sauvegarde connection_info', ['error' => $e->getMessage()]);
            }

            session(['current_project' => [
                'id' => $project->id,
                'name' => $project->name,
                'connection' => $config,
                'db_type' => $project->db_type
            ]]);

            Log::info('=== REDIRECTION VERS DASHBOARD ===');
            return redirect()->route('dashboard')->with('success', 'Connection successful to ' . $validated['database']. '. Synchronizing with web...');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Erreurs de validation handleConnect', ['errors' => $e->errors()]);
            return redirect()->back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            Log::error('Erreur générale handleConnect', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'class' => get_class($e),
            ]);

            if (str_starts_with($e->getMessage(), 'LIMIT_REACHED')) {
                $limitMessage = str_replace('LIMIT_REACHED: ', '', $e->getMessage());
                return back()->with('error', $limitMessage); // ← ici aussi
            }

            $errorMessage = 'An unexpected error occurred: ' . $e->getMessage();
            return \Inertia\Inertia::render('Projects/Connect', [
                'project' => $project,
                'flash' => ['error' => $errorMessage]
            ])->withViewData(['flash' => ['error' => $errorMessage]]);
        }
    }


    private function cleanupConnection(string $connectionName): void
    {
        try {
            DB::disconnect($connectionName);
            DB::purge($connectionName);
            Log::info('Connexion nettoyée avec succès', ['connection' => $connectionName]);
        } catch (\Exception $e) {
            Log::warning('Erreur lors du nettoyage de la connexion', [
                'connection' => $connectionName,
                'cleanup_error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Analyser les exceptions PDO pour fournir des messages d'erreur clairs
     */
    private function analyzePDOException(\PDOException $e, $dbType, $validated)
    {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        // Messages d'erreur selon le type de base de données
        switch ($dbType) {
            case 'mysql':
                return $this->analyzeMySQLError($errorCode, $errorMessage, $validated);
            case 'pgsql':
                return $this->analyzePostgreSQLError($errorCode, $errorMessage, $validated);
            case 'sqlserver':
                return $this->analyzeSQLServerError($errorCode, $errorMessage, $validated);
            default:
                return "Database connection failed: " . $errorMessage;
        }
    }

    /**
     * Analyser les erreurs MySQL
     */
    private function analyzeMySQLError($errorCode, $errorMessage, $validated, array $errorInfo = []) 
    {
        // MySQL native error code (most reliable)
        $nativeCode = $errorInfo[1] ?? null;
        $sqlState   = $errorCode;

        // 1049: Unknown database
        if ($nativeCode === 1049) {
            return "🗄️ Database '{$validated['database']}' does not exist on the MySQL server.\n\n" .
                "Please verify:\n" .
                "• Database name is correct\n" .
                "• Database exists";
        }

        // 1045: Access denied (wrong password OR no DB access)
        if ($nativeCode === 1045) {
            return "🔐 Access denied for user '{$validated['username']}'.\n\n" .
                "Possible causes:\n" .
                "• Incorrect username or password\n" .
                "• User does not have access to database '{$validated['database']}'\n\n" .
                "Please verify credentials and database privileges.";
        }

        // 1044: User has no privilege on database
        if ($nativeCode === 1044) {
            return "🔒 Permission denied on database '{$validated['database']}'.\n\n" .
                "The user '{$validated['username']}' does not have sufficient privileges.\n\n" .
                "Required:\n" .
                "• At least USAGE privilege\n" .
                "• Appropriate database permissions";
        }

        // Can't connect / connection refused
        if (in_array($nativeCode, [2002, 2003, 2005], true)) {
            return "🌐 Cannot connect to MySQL server at '{$validated['server']}:{$validated['port']}'.\n\n" .
                "Please verify:\n" .
                "• Server is running\n" .
                "• Hostname and port are correct\n" .
                "• Firewall allows the connection";
        }

        // Lost connection
        if ($nativeCode === 2013) {
            return "🌐 Lost connection to MySQL server.\n\n" .
                "Possible causes:\n" .
                "• Network instability\n" .
                "• Server overload\n" .
                "• Connection timeout";
        }

        // SQLSTATE 70100 / HY000 timeout
        if (
            $sqlState === 'HY000' &&
            strpos($errorMessage, 'timeout') !== false
        ) {
            return "⏱️ Connection timeout to MySQL server.\n\n" .
                "The server took too long to respond.\n\n" .
                "Please check server load and network connectivity.";
        }

        
        return "❌ MySQL connection failed.\n\n" .
            "SQLSTATE: {$sqlState}\n" .
            "Native error code: " . ($nativeCode ?? 'N/A') . "\n" .
            "Error message: {$errorMessage}\n\n" .
            "Please contact your database administrator.";
    }


    /**
     * Analyser les erreurs PostgreSQL
     */
    private function analyzePostgreSQLError($errorCode, $errorMessage, $validated, array $errorInfo = []) 
    {
        
        $sqlState = $errorCode;

        // 3D000: database does not exist
        if ($sqlState === '3D000') {
            return "🗄️ Database '{$validated['database']}' does not exist on the PostgreSQL server.\n\n" .
                "Please verify:\n" .
                "• Database name is correct\n" .
                "• Database exists\n" .
                "• You are connecting to the correct server";
        }

        // 28P01: invalid password
        if ($sqlState === '28P01') {
            return "🔐 Password authentication failed for user '{$validated['username']}'.\n\n" .
                "Please verify:\n" .
                "• Username is correct\n" .
                "• Password is correct";
        }

        // 28000: invalid authorization specification
        if ($sqlState === '28000') {
            return "🔐 Authentication failed for user '{$validated['username']}'.\n\n" .
                "The role exists but is not allowed to connect.\n\n" .
                "Please verify:\n" .
                "• Role has LOGIN privilege\n" .
                "• pg_hba.conf allows this connection";
        }

        // 42704: role does not exist
        if ($sqlState === '42704') {
            return "👤 Role '{$validated['username']}' does not exist on the PostgreSQL server.\n\n" .
                "Please check the username or create the role first.";
        }

        // 42501: insufficient privilege
        if ($sqlState === '42501') {
            return "🔒 Permission denied on database '{$validated['database']}'.\n\n" .
                "The user does not have sufficient privileges.\n\n" .
                "Required:\n" .
                "• CONNECT privilege on the database\n" .
                "• Appropriate schema/table permissions";
        }
        
        // 08001 / 08006: connection issues
        if (in_array($sqlState, ['08001', '08006'], true)) {
            return "🌐 Cannot connect to PostgreSQL server at '{$validated['server']}:{$validated['port']}'.\n\n" .
                "Please verify:\n" .
                "• Server is running\n" .
                "• Host and port are correct\n" .
                "• Firewall allows the connection\n" .
                "• pg_hba.conf is properly configured";
        }

        // 57P03: cannot connect now (server starting / overloaded)
        if ($sqlState === '57P03' || strpos($errorMessage, 'timeout') !== false) {
            return "⏱️ Connection timeout to PostgreSQL server.\n\n" .
                "The server is not ready or is overloaded.\n\n" .
                "Please try again later or check server status.";
        }

        return "❌ PostgreSQL connection failed.\n\n" .
            "SQLSTATE: {$sqlState}\n" .
            "Error message: {$errorMessage}\n\n" .
            "Please contact your database administrator.";
    }


    /**
     * Analyser les erreurs SQL Server
     */
    private function analyzeSQLServerError($errorCode, $errorMessage, $validated, array $errorInfo = []) 
    {
        // SQL Server native error code (most reliable)
        $nativeCode = $errorInfo[1] ?? null;

        // 4060: Cannot open database requested by the login
        if ($nativeCode === 4060) {
            return "🔒 Access denied to database '{$validated['database']}'.\n\n" .
                "The login was successful, but the user does not have access to this database.\n\n" .
                "Possible causes:\n" .
                "• User is not mapped to the database\n" .
                "• Database does not exist or is offline\n" .
                "• User lacks CONNECT permission\n\n" .
                "Solution:\n" .
                "• Ask your DBA to grant access to the database";
        }

        // 229: Permission denied
        if ($nativeCode === 229) {
            return "🔒 Permission denied on database '{$validated['database']}'.\n\n" .
                "The user does not have sufficient privileges to perform this action.\n\n" .
                "Required permissions:\n" .
                "• CONNECT permission\n" .
                "• Appropriate database roles (e.g. db_datareader)";
        }

        // 18456: Login failed
        if ($nativeCode === 18456 || strpos($errorMessage, 'Login failed') !== false) {

            // Windows authentication
            if (strpos($errorMessage, '\\') !== false) {
                preg_match("/Login failed for user '(.+?)'/", $errorMessage, $matches);
                $windowsUser = $matches[1] ?? 'Windows user';

                return "🔐 Windows Authentication failed for '{$windowsUser}'.\n\n" .
                    "This Windows account is not authorized to connect to SQL Server.\n\n" .
                    "Solutions:\n" .
                    "• Grant this Windows user access in SQL Server\n" .
                    "• Or switch to SQL Server Authentication";
            }

            // SQL Server authentication
            $username = $validated['username'] ?? 'unknown user';

            return "🔐 SQL Server Authentication failed for user '{$username}'.\n\n" .
                "Please verify:\n" .
                "• Username and password are correct\n" .
                "• Login is enabled\n" .
                "• SQL Server Authentication mode is enabled";
        }

        if (
            strpos($errorMessage, 'server was not found') !== false ||
            strpos($errorMessage, 'network path was not found') !== false
        ) {
            return "🌐 SQL Server '{$validated['server']}' was not found or is not accessible.\n\n" .
                "Please verify:\n" .
                "• Server name or IP address is correct\n" .
                "• SQL Server service is running\n" .
                "• Firewall allows port 1433\n" .
                "• SQL Server Browser is running (named instance)";
        }

        if (
            $errorCode === 'HYT00' ||
            strpos($errorMessage, 'Login timeout expired') !== false ||
            strpos($errorMessage, 'Connection Timeout Expired') !== false
        ) {
            return "⏱️ Connection timeout to SQL Server '{$validated['server']}'.\n\n" .
                "The server did not respond within the allowed time.\n\n" .
                "Possible causes:\n" .
                "• SQL Server service is stopped\n" .
                "• Server is unreachable\n" .
                "• Firewall blocks port 1433\n" .
                "• Network latency or overload";
        }

        return "❌ SQL Server connection failed.\n\n" .
            "Error message: {$errorMessage}\n" .
            "SQLSTATE: {$errorCode}\n" .
            "Native error code: " . ($nativeCode ?? 'N/A') . "\n\n" .
            "Please contact your database administrator.";
    }

    /**
     * Obtenir une requête de test selon le type de base de données
     */
    private function getTestQuery($dbType, $databaseName)
    {
        switch ($dbType) {
            case 'mysql':
                return "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . addslashes($databaseName) . "'";
            case 'pgsql':
                return "SELECT datname FROM pg_database WHERE datname = '" . addslashes($databaseName) . "'";
            case 'sqlserver':
                return "SELECT name FROM sys.databases WHERE name = '" . addslashes($databaseName) . "'";
            default:
                return "SELECT 1";
        }
    }

    /**
     * Méthode helper pour convertir le type de base de données en driver Laravel
     */
    private function getDriverFromDbType($dbType)
    {
        switch ($dbType) {
            case 'sqlserver':
                return 'sqlsrv';
            case 'mysql':
                return 'mysql';
            case 'pgsql':
                return 'pgsql';
            default:
                return $dbType;
        }
    }

    public function open($id)
{

    $cacheKey = "project_quick_access_{$id}_" . auth()->id();
    
    $projectData = Cache::remember($cacheKey, 60, function() use ($id) {
        return [
            'project' => Project::with('user:id,name')->findOrFail($id),
            'access' => $this->checkUserProjectAccessOptimized(Project::findOrFail($id)),
            'db_description' => DbDescription::where('project_id', $id)->first()
        ];
    });
    
    extract($projectData);
    

    if ($project->trashed()) {
        return redirect()->route('projects.index')
            ->with('error', 'Ce projet a été supprimé.');
    }
    
    if (!$access['allowed']) {
        return redirect()->route('projects.index')
            ->with('error', $access['message']);
    }
    
    if (!$db_description) {
        if ($access['is_owner']) {
            return redirect()->route('projects.connect', $project->id)
                ->with('info', "Project needs database connection.");
        } else {
            return redirect()->route('projects.index')
                ->with('warning', "Project not configured yet.");
        }
    }
    

    $connectionInfo = $this->prepareConnectionInfoOptimized($project, $db_description);
    
    session([
        'current_project' => [
            'id' => $project->id,
            'name' => $project->name,
            'db_type' => $project->db_type,
            'connection' => $connectionInfo,
            'access_level' => $access['access_level'],
            'is_owner' => $access['is_owner']
        ],
        'current_db_id' => $db_description->id
    ]);
    

    return redirect()->route('dashboard')
        ->with('success', "Project '{$project->name}' opened successfully.");
}


    private function checkUserProjectAccessOptimized($project)
    {
        $userId = auth()->id();
        
        // Le propriétaire a toujours accès
        if ($project->user_id == $userId) {
            return [
                'allowed' => true,
                'access_level' => 'owner',
                'is_owner' => true,
                'message' => 'Owner access'
            ];
        }
        
     
        $projectAccess = UserProjectAccess::where('user_id', $userId)
            ->where('project_id', $project->id)
            ->first(['access_level']);
        
        if ($projectAccess) {
            return [
                'allowed' => true,
                'access_level' => $projectAccess->access_level,
                'is_owner' => false,
                'message' => 'Shared access: ' . $projectAccess->access_level
            ];
        }
        
        return [
            'allowed' => false,
            'access_level' => null,
            'is_owner' => false,
            'message' => "You don't have permission to access this project."
        ];
    }

    private function prepareConnectionInfoOptimized($project, $dbDescription)
    {
        if (isset($project->connection_info) && !empty($project->connection_info)) {
            $connectionInfo = is_string($project->connection_info) 
                ? json_decode($project->connection_info, true) 
                : $project->connection_info;
        } else {
            $connectionInfo = [];
        }
      
        $connectionInfo = array_merge([
            'driver' => $this->getDriverFromDbType($project->db_type),
            'host' => 'localhost',
            'database' => $dbDescription->dbname,
            'username' => '',
            'password' => '',
            'port' => null,
            'charset' => 'utf8',
            'prefix' => '',
        ], $connectionInfo);
        
        if (empty($connectionInfo['driver'])) {
            $connectionInfo['driver'] = $this->getDriverFromDbType($project->db_type);
        }
        
        return $connectionInfo;
    }

    private function quickDatabaseCheck($project, $dbDescription, $connectionInfo)
    {
        // Message par défaut de succès
        $messages = ['success' => "Project '{$project->name}' opened successfully."];
        
        try {

            $connectionName = "project_quick_check_{$project->id}";
            
            $quickConfig = $connectionInfo;
            $quickConfig['options'] = [
                \PDO::ATTR_TIMEOUT => 2, 
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            ];
            
            Config::set("database.connections.{$connectionName}", $quickConfig);
            
 
            $pdo = DB::connection($connectionName)->getPdo();
            
            $testQuery = "SELECT 1";
            switch ($project->db_type) {
                case 'mysql':
                    $testQuery = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1";
                    break;
                case 'pgsql':
                    $testQuery = "SELECT 1 FROM pg_database WHERE datname = ? LIMIT 1";
                    break;
                case 'sqlserver':
                    $testQuery = "SELECT 1 FROM sys.databases WHERE name = ?";
                    break;
            }
            
            $result = DB::connection($connectionName)->select($testQuery, [$dbDescription->dbname]);
            
            // Nettoyer immédiatement
            DB::disconnect($connectionName);
            DB::purge($connectionName);
            
            if (empty($result) && $project->db_type !== 'general') {
                $messages = [
                    'warning' => "Project '{$project->name}' opened, but database '{$dbDescription->dbname}' seems inaccessible. You may need to check the connection settings."
                ];
            }
            
        } catch (\Exception $e) {
            Log::warning('Quick database check failed (non-blocking)', [
                'project_id' => $project->id,
                'error' => $e->getMessage()
            ]);
            
            // Ne pas bloquer l'ouverture, juste avertir
            $messages = [
                'info' => "Project '{$project->name}' opened. Database connectivity will be verified when needed."
            ];
            
            // Nettoyer en cas d'erreur
            try {
                $connectionName = "project_quick_check_{$project->id}";
                DB::disconnect($connectionName);
                DB::purge($connectionName);
            } catch (\Exception $cleanupException) {
                // Ignorer les erreurs de nettoyage
            }
        }
        
        return $messages;
    }

    private function prepareConnectionInfo($project, $dbDescription)
    {
        $connectionInfo = null;
        
        if (isset($project->connection_info) && !empty($project->connection_info)) {
            if (is_string($project->connection_info)) {
                $connectionInfo = json_decode($project->connection_info, true);
            } else {
                $connectionInfo = $project->connection_info;
            }
        } else {
            // Utiliser les informations de base si pas de connection_info
            $connectionInfo = [
                'driver' => $this->getDriverFromDbType($project->db_type),
                'host' => 'localhost',
                'database' => $dbDescription->dbname,
                'username' => '',
                'password' => ''
            ];
        }
        
        // ✅ Normaliser : s'assurer que toutes les clés existent
        $connectionInfo = array_merge([
            'driver' => $this->getDriverFromDbType($project->db_type),
            'host' => 'localhost',
            'database' => $dbDescription->dbname,
            'username' => '',
            'password' => '',
            'port' => null,
            'charset' => 'utf8',
            'prefix' => '',
        ], $connectionInfo);
        
        // S'assurer que le driver est défini
        if (empty($connectionInfo['driver'])) {
            $connectionInfo['driver'] = $this->getDriverFromDbType($project->db_type);
        }
        
        return $connectionInfo;
    }

    private function checkUserProjectAccess($project)
    {
        $userId = auth()->id();
        
        Log::info('Vérification accès utilisateur', [
            'user_id' => $userId,
            'project_id' => $project->id,
            'project_owner_id' => $project->user_id
        ]);
        
        // Le propriétaire a toujours accès
        if ($project->user_id == $userId) {
            Log::info('Utilisateur identifié comme propriétaire');
            return [
                'allowed' => true,
                'access_level' => 'owner',
                'is_owner' => true,
                'message' => 'Owner access'
            ];
        }
        
        // Vérifier les accès partagés
        $projectAccess = UserProjectAccess::where('user_id', $userId)
            ->where('project_id', $project->id)
            ->first();
        
        if ($projectAccess) {
            Log::info('Accès partagé trouvé', ['access_level' => $projectAccess->access_level]);
            return [
                'allowed' => true,
                'access_level' => $projectAccess->access_level,
                'is_owner' => false,
                'message' => 'Shared access: ' . $projectAccess->access_level
            ];
        }
        
        // Aucun accès
        Log::info('Aucun accès trouvé pour cet utilisateur');
        return [
            'allowed' => false,
            'access_level' => null,
            'is_owner' => false,
            'message' => "You don't have permission to access this project. Contact the project owner or an administrator."
        ];
    }

    private function checkDatabaseAndPrepareMessages($project, $dbDescription, $connectionInfo)
    {
        $messages = ['success' => "Project '{$project->name}' opened successfully."];
        
        try {
            Log::info('Début vérification contenu base de données');
            
            $connectionName = "project_temp_check_{$project->id}";
            Config::set("database.connections.{$connectionName}", $connectionInfo);
            
            // Test de connexion
            $pdo = DB::connection($connectionName)->getPdo();
            Log::info('Connexion PDO réussie');
            
            // Vérifier le contenu de la base de données
            $databaseStats = $this->checkDatabaseContent($connectionName, $project->db_type, $dbDescription->dbname);
            Log::info('Statistiques de la base de données', $databaseStats);
            
            // Nettoyer la connexion temporaire
            DB::disconnect($connectionName);
            DB::purge($connectionName);
            Log::info('Connexion temporaire nettoyée');
            
            // Analyser les statistiques et préparer les messages
            $messages = $this->analyzeDatabaseStats($databaseStats, $project->name);
            Log::info('Messages générés', $messages);
            
        } catch (\PDOException $e) {
            Log::error('Erreur PDO lors de la vérification du contenu', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            $errorMessage = $this->analyzePDOException($e, $project->db_type, [
                'server' => $connectionInfo['host'],
                'database' => $connectionInfo['database'],
                'username' => $connectionInfo['username'] ?? '',
                'port' => $connectionInfo['port'] ?? null
            ]);
            
            $messages = [
                'error' => "Cannot connect to database for project '{$project->name}': {$errorMessage}. Please check your connection settings."
            ];
            
        } catch (\Exception $e) {
            Log::error('Erreur générale lors de la vérification du contenu', [
                'project_id' => $project->id,
                'error' => $e->getMessage()
            ]);
            
            $messages = [
                'warning' => "Project '{$project->name}' opened, but unable to verify database content. Error: " . $e->getMessage()
            ];
        }
        
        return $messages;
    }

    private function redirectWithMessage($messages, $projectName)
    {
        if (isset($messages['error'])) {
            Log::info('Redirection avec erreur', ['message' => $messages['error']]);
            return redirect()->route('projects.index')->with('error', $messages['error']);
        }
        
        $redirectResponse = redirect()->route('dashboard');
        
        if (isset($messages['warning'])) {
            Log::info('Redirection avec warning', ['message' => $messages['warning']]);
            return $redirectResponse->with('warning', $messages['warning']);
        } elseif (isset($messages['info'])) {
            Log::info('Redirection avec info', ['message' => $messages['info']]);
            return $redirectResponse->with('info', $messages['info']);
        } else {
            Log::info('Redirection avec succès', ['message' => $messages['success']]);
            return $redirectResponse->with('success', $messages['success']);
        }
    }

    /**
    * Vérifier le contenu de la base de données
    */
    private function checkDatabaseContent($connectionName, $dbType, $databaseName)
    {
        $stats = [
            'tables_count' => 0,
            'views_count' => 0,
            'total_records' => 0,
            'user_tables' => [],
            'system_tables_only' => false,
            'connection_error' => false
        ];
        
        try {
            switch ($dbType) {
                case 'mysql':
                    $stats = $this->checkMySQLContent($connectionName, $databaseName);
                    break;
                case 'pgsql':
                    $stats = $this->checkPostgreSQLContent($connectionName);
                    break;
                case 'sqlserver':
                    $stats = $this->checkSQLServerContent($connectionName);
                    break;
                default:
                    Log::warning('Type de base de données non supporté pour la vérification de contenu', [
                        'db_type' => $dbType
                    ]);
                    $stats['connection_error'] = true;
            }
        } catch (\Exception $e) {
            Log::warning('Erreur lors de la vérification du contenu de la BD', [
                'db_type' => $dbType,
                'error' => $e->getMessage()
            ]);
            $stats['connection_error'] = true;
        }
        
        return $stats;
    }

    /**
     * Vérifier le contenu MySQL
     */
    private function checkMySQLContent($connectionName, $databaseName)
    {
        $stats = ['tables_count' => 0, 'views_count' => 0, 'total_records' => 0, 'user_tables' => []];
        
        // Compter les tables utilisateur
        $tables = DB::connection($connectionName)->select("
            SELECT TABLE_NAME, TABLE_ROWS 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_TYPE = 'BASE TABLE'
            AND TABLE_NAME NOT LIKE 'mysql_%'
            AND TABLE_NAME NOT LIKE 'sys_%'
            AND TABLE_NAME NOT LIKE 'performance_schema%'
            AND TABLE_NAME NOT LIKE 'information_schema%'
        ", [$databaseName]);
        
        $stats['tables_count'] = count($tables);
        
        foreach ($tables as $table) {
            $stats['user_tables'][] = $table->TABLE_NAME;
            $stats['total_records'] += (int)$table->TABLE_ROWS;
        }
        
        // Compter les vues
        $views = DB::connection($connectionName)->select("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.VIEWS 
            WHERE TABLE_SCHEMA = ?
        ", [$databaseName]);
        
        $stats['views_count'] = $views[0]->count ?? 0;
        
        return $stats;
    }

    /**
     * Vérifier le contenu PostgreSQL
     */
    private function checkPostgreSQLContent($connectionName)
    {
        $stats = ['tables_count' => 0, 'views_count' => 0, 'total_records' => 0, 'user_tables' => []];
        
        // Compter les tables utilisateur
        $tables = DB::connection($connectionName)->select("
            SELECT 
                schemaname, 
                tablename,
                (SELECT reltuples::bigint AS estimate FROM pg_class WHERE relname = tablename) as row_estimate
            FROM pg_tables 
            WHERE schemaname = 'public'
        ");
        
        $stats['tables_count'] = count($tables);
        
        foreach ($tables as $table) {
            $stats['user_tables'][] = $table->tablename;
            $stats['total_records'] += (int)($table->row_estimate ?? 0);
        }
        
        // Compter les vues
        $views = DB::connection($connectionName)->select("
            SELECT COUNT(*) as count 
            FROM information_schema.views 
            WHERE table_schema = 'public'
        ");
        
        $stats['views_count'] = $views[0]->count ?? 0;
        
        return $stats;
    }

    /**
     * Vérifier le contenu SQL Server
     */
    private function checkSQLServerContent($connectionName)
    {
        $stats = ['tables_count' => 0, 'views_count' => 0, 'total_records' => 0, 'user_tables' => []];
        
        // Compter les tables utilisateur
        $tables = DB::connection($connectionName)->select("
            SELECT 
                t.name as table_name,
                ISNULL(p.rows, 0) as row_count
            FROM sys.tables t
            LEFT JOIN sys.partitions p ON t.object_id = p.object_id 
            WHERE p.index_id IN (0,1)
            AND t.is_ms_shipped = 0
        ");
        
        $stats['tables_count'] = count($tables);
        
        foreach ($tables as $table) {
            $stats['user_tables'][] = $table->table_name;
            $stats['total_records'] += (int)$table->row_count;
        }
        
        // Compter les vues
        $views = DB::connection($connectionName)->select("
            SELECT COUNT(*) as count 
            FROM sys.views 
            WHERE is_ms_shipped = 0
        ");
        
        $stats['views_count'] = $views[0]->count ?? 0;
        
        return $stats;
    }

    /**
     * Analyser les statistiques et générer les messages appropriés
     */
    private function analyzeDatabaseStats($stats, $projectName)
    {
        $messages = [];
        
        // Base de données complètement vide (aucune table, aucune vue)
        if ($stats['tables_count'] === 0 && $stats['views_count'] === 0) {
            $messages['warning'] = "Project '{$projectName}' opened successfully, but the database is completely empty. No tables or views found. You need to create tables or import data to start working with this project.";
            return $messages;
        }
        
        // Seulement des vues, pas de tables utilisateur
        if ($stats['tables_count'] === 0 && $stats['views_count'] > 0) {
            $viewText = $stats['views_count'] === 1 ? 'view' : 'views';
            $messages['info'] = "Project '{$projectName}' opened successfully. The database contains {$stats['views_count']} {$viewText} but no user tables. Consider creating tables or importing data.";
            return $messages;
        }
        
        // Tables présentes mais vides (aucune donnée)
        if ($stats['tables_count'] > 0 && $stats['total_records'] === 0) {
            $tableText = $stats['tables_count'] === 1 ? 'table' : 'tables';
            $tableList = implode(', ', array_slice($stats['user_tables'], 0, 3));
            if (count($stats['user_tables']) > 3) {
                $tableList .= '...';
            }
            
            $messages['warning'] = "Project '{$projectName}' opened successfully. Found {$stats['tables_count']} {$tableText} ({$tableList}) but they contain no data. Import data or add records to start analyzing your database.";
            return $messages;
        }
        
        // Très peu de données (moins de 10 enregistrements au total)
        if ($stats['tables_count'] > 0 && $stats['total_records'] > 0 && $stats['total_records'] < 10) {
            $tableText = $stats['tables_count'] === 1 ? 'table' : 'tables';
            $recordText = $stats['total_records'] === 1 ? 'record' : 'records';
            
            $messages['info'] = "Project '{$projectName}' opened successfully. Database contains {$stats['tables_count']} {$tableText} with only {$stats['total_records']} {$recordText}. Consider adding more data for better analysis.";
            return $messages;
        }
        
        // Base de données avec du contenu normal
        if ($stats['tables_count'] > 0 && $stats['total_records'] >= 10) {
            $tableText = $stats['tables_count'] === 1 ? 'table' : 'tables';
            $summary = "{$stats['tables_count']} {$tableText}";
            
            if ($stats['views_count'] > 0) {
                $viewText = $stats['views_count'] === 1 ? 'view' : 'views';
                $summary .= " and {$stats['views_count']} {$viewText}";
            }
            
            $summary .= " with approximately " . number_format($stats['total_records']) . " records";
            
            $messages['success'] = "Project '{$projectName}' opened successfully. Database contains {$summary}. Ready for analysis!";
            return $messages;
        }
        
        // Cas par défaut (ne devrait pas arriver)
        $messages['info'] = "Project '{$projectName}' opened successfully.";
        return $messages;
    }

    

    public function disconnect(Request $request)
    {
        try {
            // Récupérer les informations du projet en cours depuis la session
            $currentProject = session('current_project');
            
            if ($currentProject) {
                // Récupération de l'ID du projet et du nom de connexion
                $projectId = $currentProject['id'];
                $connectionName = "project_{$projectId}";
                
                // Log pour le débogage
                Log::info("Tentative de déconnexion", [
                    'connection' => $connectionName,
                    'db_type' => $currentProject['db_type'] ?? 'non défini'
                ]);
                
                // Fermer la connexion de base de données, quel que soit le type
                DB::disconnect($connectionName);
                
                // Supprimer la connexion de la configuration
                Config::set("database.connections.{$connectionName}", null);
                
                // Supprimer les informations de connexion de la session
                session()->forget('current_project');
                session()->save();
                
                // Message flash pour l'utilisateur
                session()->flash('success', 'Déconnecté de la base de données avec succès');
            } else {
                Log::warning("Tentative de déconnexion sans connexion active en session");
            }
            
            // Pour les requêtes AJAX, retourner une réponse JSON
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true]);
            }
            
            // Sinon, rediriger vers la liste des projets
            return redirect()->route('projects.index');
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la déconnexion', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
            
            return redirect()->back()->with('error', 'Erreur lors de la déconnexion: ' . $e->getMessage());
        }
    }

    /**
     * Soft delete d'un projet
     */
    public function softDelete($id)
    {
        try {
            Log::info('Tentative de soft delete', [
                'project_id' => $id,
                'user_id' => auth()->id()
            ]);

            $project = Project::where('user_id', auth()->id())
                ->where('id', $id)
                ->first();

            if (!$project) {
                return redirect()->back()->with('error', 'Project not found');
            }

            if ($project->trashed()) {
                return redirect()->back()->with('error', 'This project has already been deleted');
            }

            
            $result = DB::statement("
                UPDATE projects 
                SET deleted_at = datetime('now'), 
                    updated_at = datetime('now') 
                WHERE id = ? AND user_id = ?
            ", [$id, auth()->id()]);

            if ($result) {
                Log::info('Projet supprimé avec succès', ['project_id' => $id]);
                return redirect()->back()->with('success', 'Project deleted with success');

            } else {
                throw new \Exception('Échec de la mise à jour');
            }

        } catch (\Exception $e) {
            Log::error('Erreur soft delete:', [
                'error' => $e->getMessage(),
                'project_id' => $id
            ]);

            return redirect()->back()->with('error', 'Erreur serveur: ' . $e->getMessage());
        }
    }

    /**
     * Restaure un projet supprimé
     */
    public function restore($id)
    {
        
        if (! auth()->user()->isAdmin()) {
            return redirect()->back()
                ->with('error', 'Restricted acces, onlt admin can restore the project.');
        }

        $project = Project::withTrashed()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        if (! $project->trashed()) {
            return redirect()->back()
                ->with('warning', 'project is not deleted');
        }

        $project->restore();

        Log::info('Projet restauré par admin', [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'admin_id' => auth()->id(),
        ]);

        return redirect()->back()
            ->with('success', 'Project succesfully restored');
    }


    /**
     * Suppression définitive d'un projet
     */
    public function forceDeleteProject($id)
    {
        try {
            Log::info('🚨 ProjectController::forceDeleteProject appelée', [
                'project_id' => $id,
                'user_id' => auth()->id()
            ]);

            // Vérifier les permissions 
            if (!auth()->user()->isAdmin()) {
                return back()->with('error', 'Restricted access. Only an admin can delete this project.');
            }

            $project = Project::withTrashed()->findOrFail($id);
            $projectName = $project->name;
            $projectOwner = $project->user->name ?? 'User deleted';

            Log::info('🗑️ Début de suppression forcée du projet', [
                'project_id' => $id,
                'project_name' => $projectName,
                'admin_id' => auth()->id()
            ]);

            // Analyser toutes les dépendances
            $dependencies = $this->analyzeProjectDependencies($project->id);
            
            Log::info('📊 Dépendances trouvées', [
                'project_id' => $id,
                'dependencies' => $dependencies
            ]);

            // Supprimer toutes les dépendances dans une transaction
            DB::transaction(function () use ($project, $dependencies) {
                $this->deleteProjectDependencies($project->id, $dependencies);
                $project->forceDelete();
            });

            Log::warning('✅ Projet supprimé définitivement avec succès', [
                'project_id' => $id,
                'project_name' => $projectName,
                'project_owner' => $projectOwner,
                'dependencies_deleted' => $dependencies,
                'admin_id' => auth()->id()
            ]);

            return redirect()->route('projects.index')
                ->with('success', "Le projet \"{$projectName}\" et toutes ses dépendances ont été supprimés définitivement.");
            
        } catch (\Exception $e) {
            Log::error('❌ Erreur dans ProjectController::forceDeleteProject', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'admin_id' => auth()->id()
            ]);

            return back()->with('error', 'Erreur lors de la suppression définitive: ' . $e->getMessage());
        }
    }

    private function analyzeProjectDependencies($projectId)
    {
        $dependencies = [];

        try {
            Log::info('📊 Analyse des dépendances pour le projet', ['project_id' => $projectId]);

            $dbDescriptions = DbDescription::where('project_id', $projectId)->get();
            $dependencies['databases'] = $dbDescriptions->count();

            if ($dependencies['databases'] > 0) {
                $dbIds = $dbDescriptions->pluck('id');
                Log::info('📁 Bases de données trouvées', ['count' => $dependencies['databases'], 'db_ids' => $dbIds->toArray()]);
                
                // Tables
                $dependencies['tables'] = DB::table('table_description')
                    ->whereIn('dbid', $dbIds)
                    ->count();
                    
                $tableIds = DB::table('table_description')
                    ->whereIn('dbid', $dbIds)
                    ->pluck('id');
                    
                if ($tableIds->isNotEmpty()) {
                    $dependencies['columns'] = DB::table('table_structure')
                        ->whereIn('id_table', $tableIds)
                        ->count();
                        
                    $dependencies['indexes'] = DB::table('table_index')
                        ->whereIn('id_table', $tableIds)
                        ->count();
                        
                    $dependencies['relations'] = DB::table('table_relations')
                        ->whereIn('id_table', $tableIds)
                        ->count();
                }
                
                // Vues et leurs colonnes
                $dependencies['views'] = DB::table('view_description')
                    ->whereIn('dbid', $dbIds)
                    ->count();
                    
                $viewIds = DB::table('view_description')
                    ->whereIn('dbid', $dbIds)
                    ->pluck('id');
                    
                if ($viewIds->isNotEmpty()) {
                    $dependencies['view_columns'] = DB::table('view_column')
                        ->whereIn('id_view', $viewIds)
                        ->count();
                    
                    $dependencies['view_information'] = DB::table('view_information')
                        ->whereIn('id_view', $viewIds)
                        ->count();
                }
                
                // Triggers
                $dependencies['triggers'] = DB::table('trigger_description')
                    ->whereIn('dbid', $dbIds)
                    ->count();
                    
                $triggerIds = DB::table('trigger_description')
                    ->whereIn('dbid', $dbIds)
                    ->pluck('id');
                    
                if ($triggerIds->isNotEmpty()) {
                    $dependencies['trigger_information'] = DB::table('trigger_information')
                        ->whereIn('id_trigger', $triggerIds)
                        ->count();
                }
                    
                // ✅ Fonctions et func_information
                $dependencies['functions'] = DB::table('function_description')
                    ->whereIn('dbid', $dbIds)
                    ->count();
                    
                $functionIds = DB::table('function_description')
                    ->whereIn('dbid', $dbIds)
                    ->pluck('id');
                    
                if ($functionIds->isNotEmpty()) {
                    $dependencies['func_information'] = DB::table('func_information')
                        ->whereIn('id_func', $functionIds)
                        ->count();
                }

                if ($functionIds->isNotEmpty()) {
                    $dependencies['func_parameter'] = DB::table('func_parameter')
                        ->whereIn('id_func', $functionIds)
                        ->count();
                }
                    
                // ✅ Procédures et ps_information
                $dependencies['procedures'] = DB::table('ps_description')
                    ->whereIn('dbid', $dbIds)
                    ->count();
                    
                $procedureIds = DB::table('ps_description')
                    ->whereIn('dbid', $dbIds)
                    ->pluck('id');
                    
                if ($procedureIds->isNotEmpty()) {
                    $dependencies['ps_information'] = DB::table('ps_information')
                        ->whereIn('id_ps', $procedureIds)
                        ->count();
                }

                if ($procedureIds->isNotEmpty()) {
                    $dependencies['ps_parameter'] = DB::table('ps_parameter')
                        ->whereIn('id_ps', $procedureIds)
                        ->count();
                }
            }

            // Releases
            $dependencies['releases'] = DB::table('release')
                ->where('project_id', $projectId)
                ->count();
                
            // Permissions utilisateur
            $dependencies['user_permissions'] = DB::table('user_project_accesses')
                ->where('project_id', $projectId)
                ->count();

            Log::info('📊 Analyse terminée', ['dependencies' => $dependencies]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur lors de l\'analyse des dépendances', [
                'project_id' => $projectId,
                'error' => $e->getMessage()
            ]);
        }

        return array_filter($dependencies, function($count) {
            return $count > 0;
        });
    }

    private function deleteProjectDependencies($projectId, $dependencies)
    {
        Log::info('🗑️ Début suppression des dépendances', [
            'project_id' => $projectId,
            'dependencies' => $dependencies
        ]);

        try {
            $dbIds = DbDescription::where('project_id', $projectId)->pluck('id');
            
            if ($dbIds->isNotEmpty()) {
                Log::info('🔍 IDs des bases de données à traiter', ['db_ids' => $dbIds->toArray()]);
                
                $viewIds = DB::table('view_description')->whereIn('dbid', $dbIds)->pluck('id');
                
                // ORDRE CRITIQUE : Supprimer les enfants AVANT les parents
                
                // 1. view_information (référence view_description)
                if ($viewIds->isNotEmpty() && isset($dependencies['view_information'])) {
                    $deleted = DB::table('view_information')->whereIn('id_view', $viewIds)->delete();
                    Log::info("✅ Informations de vues supprimées: {$deleted}");
                }
                
                // 2. view_column (référence view_description)
                if ($viewIds->isNotEmpty() && isset($dependencies['view_columns'])) {
                    $deleted = DB::table('view_column')->whereIn('id_view', $viewIds)->delete();
                    Log::info("✅ Colonnes de vues supprimées: {$deleted}");
                }
                
                // 3. view_description (maintenant peut être supprimée)
                if (isset($dependencies['views'])) {
                    $deleted = DB::table('view_description')->whereIn('dbid', $dbIds)->delete();
                    Log::info("✅ Vues supprimées: {$deleted}");
                }
                
                // 4. Tables et leurs dépendances
                $tableIds = DB::table('table_description')->whereIn('dbid', $dbIds)->pluck('id');
                    
                if ($tableIds->isNotEmpty()) {
                    Log::info('🔍 IDs des tables à traiter', ['table_ids' => $tableIds->toArray()]);
                    
                    if (isset($dependencies['columns'])) {
                        $deleted = DB::table('table_structure')->whereIn('id_table', $tableIds)->delete();
                        Log::info("✅ Colonnes supprimées: {$deleted}");
                    }
                    
                    if (isset($dependencies['indexes'])) {
                        $deleted = DB::table('table_index')->whereIn('id_table', $tableIds)->delete();
                        Log::info("✅ Index supprimés: {$deleted}");
                    }
                    
                    if (isset($dependencies['relations'])) {
                        $deleted = DB::table('table_relations')->whereIn('id_table', $tableIds)->delete();
                        Log::info("✅ Relations supprimées: {$deleted}");
                    }
                }
                
                // 5. Triggers et leurs dépendances
                if (isset($dependencies['triggers'])) {
                    $triggerIds = DB::table('trigger_description')->whereIn('dbid', $dbIds)->pluck('id');
                    if ($triggerIds->isNotEmpty()) {
                        $deleted = DB::table('trigger_information')->whereIn('id_trigger', $triggerIds)->delete();
                        Log::info("✅ Informations de triggers supprimées: {$deleted}");
                    }
                    $deleted = DB::table('trigger_description')->whereIn('dbid', $dbIds)->delete();
                    Log::info("✅ Triggers supprimés: {$deleted}");
                }
                
                //  6. Fonctions - CORRECTION ICI
                if (isset($dependencies['functions'])) {
                    $functionIds = DB::table('function_description')->whereIn('dbid', $dbIds)->pluck('id');

                    if ($functionIds->isNotEmpty()) {
                        Log::info('🔍 IDs des fonctions à traiter', ['count' => $functionIds->count()]);

                        // Suppression par lots pour éviter la limite SQL Server (2100 paramètres max)
                        collect($functionIds)->chunk(1000)->each(function ($chunk) {
                            DB::table('func_information')->whereIn('id_func', $chunk)->delete();
                        });
                        Log::info("✅ Informations de fonctions supprimées (func_information)");

                        collect($functionIds)->chunk(1000)->each(function ($chunk) {
                            DB::table('func_parameter')->whereIn('id_func', $chunk)->delete();
                        });
                        Log::info("✅ Paramètres de fonctions supprimés (func_parameter)");
                    }

                    // Enfin, suppression des fonctions elles-mêmes
                    DB::table('function_description')->whereIn('dbid', $dbIds)->delete();
                    Log::info("✅ Fonctions supprimées: {$functionIds->count()}");
                }
                
                // 7. Procédures et leurs dépendances
                if (isset($dependencies['procedures'])) {
                    $procedureIds = DB::table('ps_description')->whereIn('dbid', $dbIds)->pluck('id');

                    if ($procedureIds->isNotEmpty()) {
                        Log::info('🔍 IDs des procédures à traiter', ['count' => $procedureIds->count()]);

                        // Suppression par lots pour éviter la limite SQL Server (2100)
                        collect($procedureIds)->chunk(1000)->each(function ($chunk) {
                            DB::table('ps_information')->whereIn('id_ps', $chunk)->delete();
                        });
                        Log::info("✅ Informations de procédures supprimées (ps_information)");

                        collect($procedureIds)->chunk(1000)->each(function ($chunk) {
                            DB::table('ps_parameter')->whereIn('id_ps', $chunk)->delete();
                        });
                        Log::info("✅ Paramètres de procédures supprimés (ps_parameter)");
                    }

                    // Puis suppression des procédures elles-mêmes
                    DB::table('ps_description')->whereIn('dbid', $dbIds)->delete();
                    Log::info("✅ Procédures supprimées: {$procedureIds->count()}");
                }
                
                // 8. Tables
                if (isset($dependencies['tables'])) {
                    $deleted = DB::table('table_description')->whereIn('dbid', $dbIds)->delete();
                    Log::info("✅ Tables supprimées: {$deleted}");
                }
                
                // 9. Bases de données
                if (isset($dependencies['databases'])) {
                    $deleted = DbDescription::where('project_id', $projectId)->delete();
                    Log::info("✅ Bases de données supprimées: {$deleted}");
                }
            }
            
            if (isset($dependencies['releases'])) {
                $deleted = DB::table('release')->where('project_id', $projectId)->delete();
                Log::info("✅ Releases supprimées: {$deleted}");
            }
            
            if (isset($dependencies['user_permissions'])) {
                $deleted = DB::table('user_project_accesses')->where('project_id', $projectId)->delete();
                Log::info("✅ Permissions supprimées: {$deleted}");
            }

            Log::info('✅ Suppression des dépendances terminée');

        } catch (\Exception $e) {
            Log::error('❌ Erreur lors de la suppression des dépendances', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }


    /**
     * Affiche les projets supprimés pour l'utilisateur connecté
     */
    public function deleted()
    {
        try {
            // Vérifier si l'utilisateur est admin
            if (!auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Accès non autorisé. Seuls les administrateurs peuvent voir les projets supprimés.'
                ], 403);
            }

            $deletedProjects = Project::onlyTrashed()
                ->where('user_id', auth()->id())
                ->orderBy('deleted_at', 'desc')
                ->get()
                ->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'description' => $project->description,
                        'db_type' => $project->db_type,
                        'deleted_at' => $project->deleted_at ? $project->deleted_at->toISOString() : null,
                        'created_at' => $project->created_at ? $project->created_at->format('Y-m-d H:i:s') : null
                        //'updated_at' => $project->updated_at ? $project->updated_at->format('Y-m-d H:i:s') : null
                    ];
                });

            return response()->json([
                'success' => true,
                'projects' => $deletedProjects
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur dans ProjectController::deleted', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du chargement des projets supprimés: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mise a jour projet
     */
    public function update(Request $request, $id)
    {
        try {
            $project = Project::where('user_id', auth()->id())->findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'db_type' => 'required|in:sqlserver,mysql,pgsql' // Changé 'postgres' en 'pgsql'
            ]);

            $project->update($validated);

            Log::info('Projet mis à jour', [
                'project_id' => $id,
                'project_name' => $project->name,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'project' => $project,
                'message' => 'Projet mis à jour avec succès'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur dans ProjectController::update', [
                'id' => $id,
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la mise à jour du projet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retourne tous les projets actifs pour les API
     */
    public function apiIndex()
    {
        try {
            $projects = Project::where('user_id', auth()->id())
                ->whereNull('deleted_at')
                ->select('id', 'name', 'description', 'db_type')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'projects' => $projects
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur dans ProjectController::apiIndex', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du chargement des projets: ' . $e->getMessage()
            ], 500);
        }
    }

    public function testConnection(Request $request, Project $project)
    {
        try {
            Log::info('Début test de connexion', [
                'project_id' => $project->id,
                'user_id' => auth()->id(),
                'request_data' => $request->except(['password']) // Exclure le mot de passe des logs
            ]);

            // Vérifier les permissions
            $userCanAccess = $this->checkUserProjectAccess($project);
            
            if (!$userCanAccess['allowed'] || !$userCanAccess['is_owner']) {
                Log::warning('Accès refusé pour test de connexion', [
                    'project_id' => $project->id,
                    'user_id' => auth()->id(),
                    'access_check' => $userCanAccess
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Only the project owner can test database connections.'
                ], 403);
            }

            // Validation des données
            $rules = [
                'server' => 'required|string',
                'database' => 'required|string',
                'username' => 'nullable|string',
                'password' => 'nullable|string',
            ];

            // Ajouter la validation du port selon le type de DB
            if ($project->db_type !== 'sqlserver') {
                $rules['port'] = 'required|numeric|min:1|max:65535';
            } else {
                $rules['port'] = 'nullable|numeric|min:1|max:65535';
                $rules['authMode'] = 'required|in:windows,sql';
            }

            $validated = $request->validate($rules);

            Log::info('Validation réussie', [
                'project_id' => $project->id,
                'db_type' => $project->db_type,
                'server' => $validated['server'],
                'database' => $validated['database']
            ]);

            // Préparation de la configuration de connexion
            $driver = $this->getDriverFromDbType($project->db_type);
            
            $config = [
                'driver' => $driver,
                'host' => $validated['server'],
                'database' => $validated['database'],
            ];

            // Configuration selon le type de base de données
            if ($project->db_type === 'sqlserver') {
                if (isset($validated['authMode']) && $validated['authMode'] === 'windows') {
                    $config['trusted_connection'] = true;
                    Log::info('Configuration SQL Server avec authentification Windows');
                } else {
                    $config['username'] = $validated['username'] ?? '';
                    $config['password'] = $validated['password'] ?? '';
                    Log::info('Configuration SQL Server avec authentification SQL');
                }
            } else {
                $config['port'] = $validated['port'];
                $config['username'] = $validated['username'] ?? '';
                $config['password'] = $validated['password'] ?? '';
                
                // Configuration spécifique pour PostgreSQL
                if ($project->db_type === 'pgsql') {
                    $config['charset'] = 'utf8';
                    $config['prefix'] = '';
                    $config['prefix_indexes'] = true;
                    $config['schema'] = 'public';
                    $config['sslmode'] = 'prefer';
                    Log::info('Configuration PostgreSQL appliquée');
                }
                
                Log::info('Configuration MySQL/PostgreSQL', [
                    'driver' => $driver,
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'database' => $config['database']
                ]);
            }

            // Test de connexion temporaire
            $testConnectionName = "test_connection_" . uniqid();
            
            Log::info('Tentative de connexion', [
                'connection_name' => $testConnectionName,
                'driver' => $driver
            ]);
            
            Config::set("database.connections.{$testConnectionName}", $config);
            
            try {
                // Tenter la connexion
                $pdo = DB::connection($testConnectionName)->getPdo();
                Log::info('Connexion PDO établie avec succès');
                
                // Test supplémentaire : vérifier que la base de données existe
                $testQuery = $this->getTestQuery($project->db_type, $validated['database']);
                Log::info('Exécution de la requête de test', ['query' => $testQuery]);
                
                $result = DB::connection($testConnectionName)->select($testQuery);
                Log::info('Requête de test exécutée avec succès', ['result_count' => count($result)]);
                
                // Vérifier que la base de données existe réellement
                if (empty($result)) {
                    throw new \Exception("Database '{$validated['database']}' does not exist or is not accessible.");
                }
                
                // Nettoyer la connexion temporaire
                DB::disconnect($testConnectionName);
                DB::purge($testConnectionName);
                
                Log::info('Test de connexion réussi', [
                    'project_id' => $project->id,
                    'database' => $validated['database'],
                    'driver' => $driver
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Connection test successful!'
                ]);
                
            } catch (\PDOException $e) {
                // Nettoyer la connexion en cas d'erreur
                try {
                    DB::disconnect($testConnectionName);
                    DB::purge($testConnectionName);
                } catch (\Exception $cleanupException) {
                    Log::warning('Erreur lors du nettoyage de la connexion', [
                        'cleanup_error' => $cleanupException->getMessage()
                    ]);
                }
                
                // Analyser l'erreur PDO
                $errorMessage = $this->analyzePDOException($e, $project->db_type, $validated);
                
                Log::warning('Test de connexion échoué (PDO)', [
                    'project_id' => $project->id,
                    'error_code' => $e->getCode(),
                    'error_message' => $e->getMessage(),
                    'analyzed_message' => $errorMessage
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => $errorMessage
                ]);
                
            } catch (\Exception $e) {
                // Nettoyer la connexion en cas d'erreur
                try {
                    DB::disconnect($testConnectionName);
                    DB::purge($testConnectionName);
                } catch (\Exception $cleanupException) {
                    Log::warning('Erreur lors du nettoyage de la connexion', [
                        'cleanup_error' => $cleanupException->getMessage()
                    ]);
                }
                
                Log::error('Erreur générale lors du test de connexion', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Connection test failed: ' . $e->getMessage()
                ]);
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Erreur de validation dans testConnection', [
                'project_id' => $project->id,
                'validation_errors' => $e->errors()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Validation failed: ' . collect($e->errors())->flatten()->first()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Erreur générale dans testConnection', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }  

}
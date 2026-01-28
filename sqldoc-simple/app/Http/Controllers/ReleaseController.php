<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\TableStructure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ReleaseController extends Controller
{
    /**
     * Affiche la liste des versions
     */
    public function index()
    {
        try {
            // Récupérer toutes les versions avec leur table structure associée
            $releases = Release::with('tableStructures')
                ->orderBy('version_number', 'desc')
                ->get()
                ->map(function ($release) {
                    return [
                        'id' => $release->id,
                        'version_number' => $release->version_number,
                        'table_structure' => $release->tableStructure ? [
                            'id' => $release->tableStructure->id,
                            'column' => $release->tableStructure->column,
                            'type' => $release->tableStructure->type
                        ] : null,
                        'created_at' => $release->created_at->format('d-m-Y H:i:s'),
                        'updated_at' => $release->updated_at->format('d-m-Y H:i:s')
                    ];
                });

            // Obtenir les versions uniques pour le filtre
            $uniqueVersions = Release::distinct('version_number')
                ->orderBy('version_number', 'desc')
                ->pluck('version_number');

            return Inertia::render('Releases/Index', [
                'releases' => $releases,
                'uniqueVersions' => $uniqueVersions
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseController::index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Rediriger avec un message d'erreur
            return redirect()->back()->with('error', 'Erreur lors du chargement des versions: ' . $e->getMessage());
        }
    }

    /**
     * Affiche les détails d'une version spécifique
     */
    public function show($id)
    {
        try {
            $release = Release::with('tableStructures')->findOrFail($id);

            // Récupérer les informations liées à la table structure
            $tableStructure = $release->tableStructure;
            $tableDescription = null;
            $tableName = null;

            if ($tableStructure) {
                $tableDescription = DB::table('table_description')
                    ->where('id', $tableStructure->id_table)
                    ->first();

                if ($tableDescription) {
                    $tableName = $tableDescription->tablename;
                }
            }

            return Inertia::render('Releases/Show', [
                'release' => [
                    'id' => $release->id,
                    'version_number' => $release->version_number,
                    'table_structure' => $tableStructure ? [
                        'id' => $tableStructure->id,
                        'column' => $tableStructure->column,
                        'type' => $tableStructure->type,
                        'nullable' => $tableStructure->nullable == 1,
                        'key' => $tableStructure->key,
                        'description' => $tableStructure->description,
                        'rangevalues' => $tableStructure->rangevalues
                    ] : null,
                    'table_name' => $tableName,
                    'created_at' => $release->created_at->format('d/m/Y H:i'),
                    'updated_at' => $release->updated_at->format('d/m/Y H:i')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseController::show', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('releases.index')->with('error', 'Version non trouvée ou erreur lors du chargement: ' . $e->getMessage());
        }
    }

    /**
     * Affiche le formulaire de création d'une nouvelle version
     */
    public function create()
    {
        try {
            // Récupérer la liste des colonnes disponibles pour ajouter une version
            $tableStructures = TableStructure::whereDoesntHave('releases')
                ->orWhereHas('releases', function ($query) {
                    $query->orderBy('created_at', 'desc')
                        ->limit(1);
                })
                ->with(['tableDescription' => function ($query) {
                    $query->select('id', 'tablename');
                }])
                ->get()
                ->map(function ($structure) {
                    return [
                        'id' => $structure->id,
                        'column' => $structure->column,
                        'table_name' => $structure->tableDescription ? $structure->tableDescription->tablename : 'N/A',
                        'type' => $structure->type
                    ];
                });

            // Générer la prochaine version suggérée
            $latestVersion = Release::orderBy('version_number', 'desc')->first();
            $suggestedVersion = "1.0.0";
            
            if ($latestVersion) {
                // Logique pour incrémenter la version
                $versionParts = explode(".", $latestVersion->version_number);
                if (count($versionParts) == 3) {
                    $versionParts[2] = intval($versionParts[2]) + 1;
                    $suggestedVersion = implode(".", $versionParts);
                }
            }

            return Inertia::render('Releases/Create', [
                'tableStructures' => $tableStructures,
                'suggestedVersion' => $suggestedVersion
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseController::create', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('releases.index')->with('error', 'Erreur lors de la préparation du formulaire: ' . $e->getMessage());
        }
    }

    /**
     * Traite la création d'une nouvelle version
     */
    public function store(Request $request)
    {
        try {
            // Valider les données
            $validated = $request->validate([
                'id_table_structure' => 'required|exists:table_structure,id',
                'version_number' => 'required|string'
            ]);

            // Vérifier si cette colonne a déjà une version identique
            $existingRelease = Release::where('id_table', $validated['id_table_structure'])
                ->where('version_number', $validated['version_number'])
                ->first();

            if ($existingRelease) {
                return redirect()->back()->withErrors([
                    'version_number' => 'Cette colonne possède déjà cette version.'
                ]);
            }

            // Créer la nouvelle version
            $release = Release::create($validated);

            // Mettre à jour le champ release de la table_structure si nécessaire
            $tableStructure = TableStructure::find($validated['id_table_structure']);
            if ($tableStructure && Schema::hasColumn('table_structure', 'release')) {
                $tableStructure->release = $validated['version_number'];
                $tableStructure->save();
            }

            return redirect()->route('releases.index')->with('success', 'Version créée avec succès!');
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseController::store', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Erreur lors de la création de la version: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Affiche le formulaire de modification d'une version
     */
    public function edit($id)
    {
        try {
            $release = Release::with('tableStructure')->findOrFail($id);

            // Récupérer la liste des colonnes disponibles
            $tableStructures = TableStructure::with(['tableDescription' => function ($query) {
                $query->select('id', 'tablename');
            }])
            ->get()
            ->map(function ($structure) {
                return [
                    'id' => $structure->id,
                    'column' => $structure->column,
                    'table_name' => $structure->tableDescription ? $structure->tableDescription->tablename : 'N/A',
                    'type' => $structure->type
                ];
            });

            return Inertia::render('Releases/Edit', [
                'release' => [
                    'id' => $release->id,
                    'id_table_structure' => $release->id_table_structure,
                    'version_number' => $release->version_number,
                    'created_at' => $release->created_at->format('d/m/Y H:i'),
                    'updated_at' => $release->updated_at->format('d/m/Y H:i')
                ],
                'tableStructures' => $tableStructures
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseController::edit', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('releases.index')->with('error', 'Version non trouvée ou erreur lors du chargement: ' . $e->getMessage());
        }
    }

    /**
     * Traite la mise à jour d'une version
     */
    public function update(Request $request, $id)
    {
        try {
            // Valider les données
            $validated = $request->validate([
                'id_table_structure' => 'required|exists:table_structure,id',
                'version_number' => 'required|string|max:10'
            ]);

            // Récupérer la version existante
            $release = Release::findOrFail($id);

            // Vérifier si la combinaison existe déjà (hors cette version)
            $existingRelease = Release::where('id_table_structure', $validated['id_table_structure'])
                ->where('version_number', $validated['version_number'])
                ->where('id', '!=', $id)
                ->first();

            if ($existingRelease) {
                return redirect()->back()->withErrors([
                    'version_number' => 'Cette colonne possède déjà cette version.'
                ]);
            }

            // Mettre à jour l'ancien table_structure si release a changé
            if ($release->id_table_structure != $validated['id_table_structure'] && Schema::hasColumn('table_structure', 'release')) {
                $oldTableStructure = TableStructure::find($release->id_table_structure);
                if ($oldTableStructure && $oldTableStructure->release == $release->version_number) {
                    $oldTableStructure->release = null;
                    $oldTableStructure->save();
                }
            }

            // Mettre à jour la version
            $release->update($validated);

            // Mettre à jour le nouveau table_structure si nécessaire
            if (Schema::hasColumn('table_structure', 'release')) {
                $tableStructure = TableStructure::find($validated['id_table_structure']);
                if ($tableStructure) {
                    $tableStructure->release = $validated['version_number'];
                    $tableStructure->save();
                }
            }

            return redirect()->route('releases.index')->with('success', 'Version mise à jour avec succès!');
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseController::update', [
                'id' => $id,
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Erreur lors de la mise à jour de la version: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Supprime une version
     */
    public function destroy($id)
    {
        try {
            // Récupérer la version
            $release = Release::findOrFail($id);

            // Mettre à jour le table_structure si nécessaire
            if (Schema::hasColumn('table_structure', 'release')) {
                $tableStructure = TableStructure::find($release->id_table_structure);
                if ($tableStructure && $tableStructure->release == $release->version_number) {
                    $tableStructure->release = null;
                    $tableStructure->save();
                }
            }

            // Supprimer la version
            $release->delete();

            return redirect()->route('releases.index')->with('success', 'Version supprimée avec succès!');
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseController::destroy', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('releases.index')->with('error', 'Erreur lors de la suppression de la version: ' . $e->getMessage());
        }
    }

    /**
     * API - Récupère la liste des versions disponibles
     */
    public function getVersions()
    {
        try {
            $versions = Release::distinct('version_number')
                ->orderBy('version_number', 'desc')
                ->pluck('version_number');

            return response()->json($versions);
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseController::getVersions', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Erreur lors de la récupération des versions'], 500);
        }
    }

    /**
     * API - Récupère les colonnes associées à une version spécifique
     */
    public function getColumnsByVersion($versionNumber)
    {
        try {
            $columns = Release::where('version_number', $versionNumber)
                ->with(['tableStructure' => function ($query) {
                    $query->with(['tableDescription' => function ($q) {
                        $q->select('id', 'tablename');
                    }]);
                }])
                ->get()
                ->map(function ($release) {
                    $tableStructure = $release->tableStructure;
                    $tableName = $tableStructure && $tableStructure->tableDescription 
                        ? $tableStructure->tableDescription->tablename 
                        : 'N/A';

                    return [
                        'id' => $tableStructure ? $tableStructure->id : null,
                        'column_name' => $tableStructure ? $tableStructure->column : null,
                        'table_name' => $tableName,
                        'data_type' => $tableStructure ? $tableStructure->type : null,
                        'description' => $tableStructure ? $tableStructure->description : null
                    ];
                });

            return response()->json($columns);
        } catch (\Exception $e) {
            Log::error('Erreur dans ReleaseController::getColumnsByVersion', [
                'version' => $versionNumber,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Erreur lors de la récupération des colonnes'], 500);
        }
    }
}
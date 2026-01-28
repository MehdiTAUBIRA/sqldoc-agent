<?php

namespace App\Http\Controllers;

use App\Models\DbDescription;
use Illuminate\Http\Request;
use App\Models\TableIndex;
use App\Models\TableRelation;
use App\Models\TableStructure;
use App\Models\ViewColumn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;


class SpecificSearchController extends Controller
{

    
    public function specificSearch(Request $request)
    {
        $currentDbId = session('current_db_id');
        
        Log::info('Session current_db_id: ' . $currentDbId);
        
        $dbDescription = DbDescription::find($currentDbId);
        if (!$dbDescription) {
            Log::error('DB Description non trouvé pour db_id: ' . $currentDbId);
            return Inertia::render('SpecificSearch', [
                'tableResults' => collect(),
                'viewResults' => collect(),
                'filters' => [],
            ]);
        }
        
        Log::info('DB Description trouvé', [
            'db_id' => $dbDescription->id,
            'project_id' => $dbDescription->project_id ?? 'N/A'
        ]);
        
        $tableResults = collect();
        $descriptionResults = collect();
        $viewResults = collect();
        $IndexResults = collect();
        $PkResults = collect();
        $FkResults = collect();

        // Recherche dans les tables
        if ($request->boolean('in_tables') && $request->filled('column')) {
            DB::enableQueryLog();
            
            $tableResults = TableStructure::query()
                ->with('TableDescription:id,tablename')
                ->whereHas('TableDescription', function ($q) use ($currentDbId) {
                    $q->where('dbid', $currentDbId);
                })
                ->where('column', 'like', '%' . $request->column . '%')
                ->get();
            
            $queries = DB::getQueryLog();
            Log::info('Requête SQL Tables: ' . json_encode($queries));
            Log::info('Résultats tables: ' . $tableResults->count());
        }

        // Recherche dans les tables
        if ($request->boolean('in_descriptions') && $request->filled('column')) {
            DB::enableQueryLog();
            
            $descriptionResults = TableStructure::query()
                ->with('TableDescription:id,tablename')
                ->whereHas('TableDescription', function ($q) use ($currentDbId) {
                    $q->where('dbid', $currentDbId);
                })
                ->where('description', 'like', '%' . $request->column . '%')
                ->get();
            
            $queries = DB::getQueryLog();
            Log::info('Requête SQL Tables: ' . json_encode($queries));
            Log::info('Résultats tables: ' . $descriptionResults->count());
        }

        // Recherche dans les vues
        if ($request->boolean('in_views') && $request->filled('column')) {
            DB::enableQueryLog();
            
            $viewResults = ViewColumn::query()
                ->with('ViewDescription:id,viewname') 
                ->whereHas('ViewDescription', function ($q) use ($currentDbId) {
                    $q->where('dbid', $currentDbId);
                })
                ->where('name', 'like', '%' . $request->column . '%')
                ->get();
            
            $queries = DB::getQueryLog();
            Log::info('Requête SQL Vues: ' . json_encode($queries));
            Log::info('Résultats vues: ' . $viewResults->count());
        }

        // Recherche dans les index
        if ($request->boolean('in_index') && $request->filled('column')) {
            DB::enableQueryLog();
            
            $IndexResults = TableIndex::query()
                ->with('TableDescription:id,tablename') 
                ->whereHas('TableDescription', function ($q) use ($currentDbId) {
                    $q->where('dbid', $currentDbId);
                })
                ->whereRaw('properties NOT LIKE ?', ['%PRIMARY KEY%'])
                ->where('name', 'like', '%' . $request->column . '%')
                ->get();
            
            $queries = DB::getQueryLog();
            Log::info('Requête SQL index: ' . json_encode($queries));
            Log::info('Résultats index: ' . $IndexResults->count());
        }

        // Recherche dans les clés primaires
        if ($request->boolean('in_pk') && $request->filled('column')) {
            DB::enableQueryLog();
            
            $PkResults = TableIndex::query()
                ->with('TableDescription:id,tablename') 
                ->whereHas('TableDescription', function ($q) use ($currentDbId) {
                    $q->where('dbid', $currentDbId);
                })
                ->whereRaw('properties LIKE ?', ['%PRIMARY KEY%'])
                ->where('name', 'like', '%' . $request->column . '%')
                ->get();
            
            $queries = DB::getQueryLog();
            Log::info('Requête SQL pk: ' . json_encode($queries));
            Log::info('Résultats pk: ' . $PkResults->count());
        }

        // Recherche dans les clés étrangères
        if ($request->boolean('in_fk') && $request->filled('column')) {
            DB::enableQueryLog();
            
            $FkResults = TableRelation::query()
                ->with('TableDescription:id,tablename') 
                ->whereHas('TableDescription', function ($q) use ($currentDbId) {
                    $q->where('dbid', $currentDbId);
                })
                ->where('constraints', 'like', '%' . $request->column . '%')
                ->get();
            
            $queries = DB::getQueryLog();
            Log::info('Requête SQL fk: ' . json_encode($queries));
            Log::info('Résultats fk: ' . $FkResults->count());
        }

        return Inertia::render('SpecificSearch', [
            'tableResults' => $tableResults,
            'descriptionResults' => $descriptionResults,
            'viewResults' => $viewResults,
            'IndexResults' => $IndexResults,
            'PkResults' => $PkResults,
            'FkResults' => $FkResults,
            'filters' => [
                'column' => $request->column,
                'in_tables' => $request->boolean('in_tables'),
                'in_descriptions' => $request->boolean('in_descriptions'),
                'in_views' => $request->boolean('in_views'),
                'in_index' => $request->boolean('in_index'),
                'in_pk' => $request->boolean('in_pk'),
                'in_fk' => $request->boolean('in_fk'),
            ],
        ]);
    }

}

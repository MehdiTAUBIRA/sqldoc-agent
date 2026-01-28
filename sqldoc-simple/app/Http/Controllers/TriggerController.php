<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\HasProjectPermissions;
use Illuminate\Http\Request;
use App\Models\TriggerDescription;
use App\Models\TriggerInformation;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TriggerController extends Controller
{
    use HasProjectPermissions;

    /**
     * Affiche les détails d'un trigger avec toutes les données
     */
    public function details(Request $request, $triggerName)
    {
        try {
            if ($error = $this->requirePermission($request, 'read')) {
                return $error;
            }

            // Décoder le nom du trigger
            $triggerName = urldecode($triggerName);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            Log::info('Récupération des détails pour triggerName: ' . $triggerName . ', dbId: ' . $dbId);
            
            if (!$dbId) {
                return Inertia::render('TriggerDetails', [
                    'triggerName' => $triggerName,
                    'triggerDetails' => $this->getDefaultTriggerData($triggerName),
                    'error' => 'Aucune base de données sélectionnée'
                ]);
            }

            // Récupérer ou créer la description du trigger
            $triggerDesc = TriggerDescription::where('dbid', $dbId)
                ->where('triggername', $triggerName)
                ->first();

            if (!$triggerDesc) {
                $triggerDesc = TriggerDescription::create([
                    'dbid' => $dbId,
                    'triggername' => $triggerName,
                    'description' => ''
                ]);
                Log::info('Trigger description créée par défaut pour: ' . $triggerName);
            }

            // Récupérer les informations du trigger
            $triggerInfo = TriggerInformation::where('id_trigger', $triggerDesc->id)->first();

            // Construire les données du trigger
            $triggerDetails = [
                'name' => $triggerDesc->triggername,
                'description' => $triggerDesc->description ?? '',
                'table_name' => $triggerInfo->table ?? '',
                'schema' => $triggerInfo->schema ?? null,
                'trigger_type' => $triggerInfo->type ?? '',
                'trigger_event' => $triggerInfo->event ?? '',
                'is_disabled' => $triggerInfo ? ($triggerInfo->state === 0 || $triggerInfo->state === 'DISABLED') : false,
                'definition' => $triggerInfo->definition ?? '',
                'create_date' => $triggerInfo->creation_date ?? null
            ];

            return Inertia::render('TriggerDetails', [
                'triggerName' => $triggerName,
                'triggerDetails' => $triggerDetails
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur dans TriggerController::details', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Inertia::render('TriggerDetails', [
                'triggerName' => $triggerName,
                'triggerDetails' => $this->getDefaultTriggerData($triggerName),
                'error' => 'Erreur lors de la récupération des détails du trigger: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Retourne les données par défaut d'un trigger
     */
    private function getDefaultTriggerData($triggerName)
    {
        return [
            'name' => $triggerName,
            'description' => '',
            'table_name' => '',
            'schema' => null,
            'trigger_type' => '',
            'trigger_event' => '',
            'is_disabled' => false,
            'definition' => '',
            'create_date' => null
        ];
    }

    /**
     * Sauvegarde la description d'un trigger
     */
    public function saveDescription(Request $request, $triggerName)
    {
        try {
            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to update triggers.')) {
                return $error;
            }

            // Décoder le nom du trigger
            $triggerName = urldecode($triggerName);

            // Valider les données
            $validated = $request->validate([
                'description' => 'nullable|string|max:65535'
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer ou créer la description du trigger
            $triggerDesc = TriggerDescription::where('dbid', $dbId)
                ->where('triggername', $triggerName)
                ->first();

            if (!$triggerDesc) {
                $triggerDesc = TriggerDescription::create([
                    'dbid' => $dbId,
                    'triggername' => $triggerName,
                    'description' => $validated['description'] ?? ''
                ]);
            } else {
                $triggerDesc->description = $validated['description'] ?? '';
                $triggerDesc->save();
            }

            Log::info('Description sauvegardée pour trigger: ' . $triggerName);
            return response()->json(['success' => true]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Erreur de validation dans saveDescription:', $e->errors());
            return response()->json(['error' => 'Erreur de validation: ' . implode(', ', $e->errors()['description'] ?? [])], 422);
        } catch (\Exception $e) {
            Log::error('Erreur dans saveDescription:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Erreur lors de la sauvegarde de la description: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sauvegarde toutes les informations d'un trigger
     */
    public function saveAll(Request $request, $triggerName)
    {
        try {
            if ($error = $this->requirePermission($request, 'write', 'You need write permissions to update triggers.')) {
                return $error;
            }

            // Décoder le nom du trigger
            $triggerName = urldecode($triggerName);
            
            // Valider les données
            $validated = $request->validate([
                'description' => 'nullable|string|max:65535',
                'language' => 'nullable|string|max:3'
            ]);

            // Obtenir l'ID de la base de données actuelle depuis la session
            $dbId = session('current_db_id');
            if (!$dbId) {
                return response()->json(['error' => 'Aucune base de données sélectionnée'], 400);
            }

            // Récupérer ou créer la description du trigger
            $triggerDesc = TriggerDescription::where('dbid', $dbId)
                ->where('triggername', $triggerName)
                ->first();

            if (!$triggerDesc) {
                $triggerDesc = TriggerDescription::create([
                    'dbid' => $dbId,
                    'triggername' => $triggerName,
                    'description' => $validated['description'] ?? '',
                    'language' => $validated['language'] ?? 'fr'
                ]);
            } else {
                $triggerDesc->description = $validated['description'] ?? '';
                if (isset($validated['language'])) {
                    $triggerDesc->language = $validated['language'];
                }
                $triggerDesc->save();
            }

            Log::info('Toutes les informations sauvegardées pour trigger: ' . $triggerName);
            return response()->json(['success' => true]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Erreur de validation dans saveAll:', $e->errors());
            return response()->json(['error' => 'Erreur de validation: ' . implode(', ', array_merge(...array_values($e->errors())))], 422);
        } catch (\Exception $e) {
            Log::error('Erreur dans saveAll:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Erreur lors de la sauvegarde des informations: ' . $e->getMessage()], 500);
        }
    }
}
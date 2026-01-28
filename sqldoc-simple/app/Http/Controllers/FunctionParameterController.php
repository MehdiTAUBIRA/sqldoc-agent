<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FunctionParameter;
use Illuminate\Support\Facades\Log;

class FunctionParameterController extends Controller
{
    /**
     * Met à jour la description d'un paramètre de fonction
     */
    public function updateDescription(Request $request, $parameterId)
    {
        try {
            $validated = $request->validate([
                'description' => 'nullable|string|max:2000',
            ]);

            // Trouver le paramètre
            $parameter = FunctionParameter::findOrFail($parameterId);
            
            // Mise à jour de la description
            $parameter->description = $validated['description'];
            $parameter->save();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de la description du paramètre', [
                'parameter_id' => $parameterId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QCM;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QCMController extends Controller
{
    /**
     * Liste tous les QCMs
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        if ($user->isTeacher()) {
            // Un enseignant voit ses propres QCMs
            $qcms = QCM::where('teacher_id', $user->id)
                       ->with('questions.answers')
                       ->paginate(10);
        } else {
            // Un étudiant voit les QCMs publiés et disponibles
            $qcms = QCM::where('is_published', true)
                       ->where(function($query) {
                           $query->whereNull('available_from')
                                 ->orWhere('available_from', '<=', now());
                       })
                       ->where(function($query) {
                           $query->whereNull('available_until')
                                 ->orWhere('available_until', '>=', now());
                       })
                       ->with('teacher:id,name,first_name,last_name')
                       ->paginate(10);
        }

        return response()->json([
            'success' => true,
            'qcms' => $qcms
        ]);
    }

    /**
     * Crée un nouveau QCM (enseignants uniquement)
     */
    public function store(Request $request)
    {
        if (!auth()->user()->isTeacher()) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les enseignants peuvent créer des QCMs'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:1',
            'available_from' => 'nullable|date',
            'available_until' => 'nullable|date|after:available_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $qcm = QCM::create([
            'title' => $request->title,
            'description' => $request->description,
            'teacher_id' => auth()->id(),
            'duration_minutes' => $request->duration_minutes,
            'available_from' => $request->available_from,
            'available_until' => $request->available_until,
            'is_published' => false, // Par défaut non publié
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QCM créé avec succès',
            'qcm' => $qcm
        ], 201);
    }

    /**
     * Affiche un QCM spécifique
     */
    public function show($id)
    {
        $qcm = QCM::with(['questions.answers', 'teacher:id,name,first_name,last_name'])
                   ->findOrFail($id);

        $user = auth()->user();

        // Vérification des permissions
        if ($user->isStudent() && !$qcm->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce QCM n\'est pas disponible'
            ], 403);
        }

        if ($user->isTeacher() && $qcm->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez voir que vos propres QCMs'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'qcm' => $qcm
        ]);
    }

    /**
     * Met à jour un QCM (enseignants uniquement)
     */
    public function update(Request $request, $id)
    {
        $qcm = QCM::findOrFail($id);

        if (!auth()->user()->isTeacher() || $qcm->teacher_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'sometimes|required|integer|min:1',
            'is_published' => 'sometimes|boolean',
            'available_from' => 'nullable|date',
            'available_until' => 'nullable|date|after:available_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $qcm->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'QCM mis à jour avec succès',
            'qcm' => $qcm
        ]);
    }

    /**
     * Supprime un QCM (enseignants uniquement)
     */
    public function destroy($id)
    {
        $qcm = QCM::findOrFail($id);

        if (!auth()->user()->isTeacher() || $qcm->teacher_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        $qcm->delete();

        return response()->json([
            'success' => true,
            'message' => 'QCM supprimé avec succès'
        ]);
    }
}
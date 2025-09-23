<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QCM;
use App\Models\Answer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    /**
     * Liste les questions d'un QCM
     */
    public function index(Request $request, $qcmId)
    {
        $qcm = QCM::findOrFail($qcmId);
        
        // Vérification des permissions
        $user = auth()->user();
        if ($user->isTeacher() && $qcm->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        $questions = Question::where('qcm_id', $qcmId)
                            ->with('answers')
                            ->orderBy('order')
                            ->get();

        return response()->json([
            'success' => true,
            'questions' => $questions
        ]);
    }

    /**
     * Crée une nouvelle question (enseignants uniquement)
     */
    public function store(Request $request, $qcmId)
    {
        $qcm = QCM::findOrFail($qcmId);

        if (!auth()->user()->isTeacher() || $qcm->teacher_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les enseignants peuvent ajouter des questions à leurs QCMs'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,single_choice,true_false',
            'points' => 'required|integer|min:1',
            'explanation' => 'nullable|string',
            'answers' => 'required|array|min:2',
            'answers.*.answer_text' => 'required|string',
            'answers.*.is_correct' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérification qu'il y a au moins une bonne réponse
        $correctAnswers = collect($request->answers)->where('is_correct', true);
        if ($correctAnswers->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Il doit y avoir au moins une réponse correcte'
            ], 422);
        }

        // Transaction pour créer question + réponses
        DB::beginTransaction();
        try {
            // Ordre automatique
            $maxOrder = Question::where('qcm_id', $qcmId)->max('order') ?? 0;

            $question = Question::create([
                'qcm_id' => $qcmId,
                'question_text' => $request->question_text,
                'question_type' => $request->question_type,
                'points' => $request->points,
                'order' => $maxOrder + 1,
                'explanation' => $request->explanation,
            ]);

            // Création des réponses
            foreach ($request->answers as $index => $answerData) {
                Answer::create([
                    'question_id' => $question->id,
                    'answer_text' => $answerData['answer_text'],
                    'is_correct' => $answerData['is_correct'],
                    'order' => $index + 1,
                ]);
            }

            DB::commit();

            $question->load('answers');

            return response()->json([
                'success' => true,
                'message' => 'Question créée avec succès',
                'question' => $question
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la question'
            ], 500);
        }
    }

    /**
     * Affiche une question spécifique
     */
    public function show($qcmId, $id)
    {
        $question = Question::where('qcm_id', $qcmId)
                           ->with(['answers', 'qcm'])
                           ->findOrFail($id);

        $user = auth()->user();
        if ($user->isTeacher() && $question->qcm->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'question' => $question
        ]);
    }

    /**
     * Met à jour une question (enseignants uniquement)
     */
    public function update(Request $request, $qcmId, $id)
    {
        $question = Question::where('qcm_id', $qcmId)->findOrFail($id);

        if (!auth()->user()->isTeacher() || $question->qcm->teacher_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'question_text' => 'sometimes|required|string',
            'question_type' => 'sometimes|required|in:multiple_choice,single_choice,true_false',
            'points' => 'sometimes|required|integer|min:1',
            'explanation' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $question->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Question mise à jour avec succès',
            'question' => $question->load('answers')
        ]);
    }

    /**
     * Supprime une question (enseignants uniquement)
     */
    public function destroy($qcmId, $id)
    {
        $question = Question::where('qcm_id', $qcmId)->findOrFail($id);

        if (!auth()->user()->isTeacher() || $question->qcm->teacher_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'Question supprimée avec succès'
        ]);
    }
}
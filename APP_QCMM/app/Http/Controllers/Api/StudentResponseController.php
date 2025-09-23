<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentResponse;
use App\Models\QCM;
use App\Models\Question;
use App\Models\Result;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class StudentResponseController extends Controller
{
    /**
     * Soumet les réponses d'un étudiant à un QCM
     */
    public function submitResponses(Request $request, $qcmId)
    {
        $user = auth()->user();
        
        if (!$user->isStudent()) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les étudiants peuvent soumettre des réponses'
            ], 403);
        }

        $qcm = QCM::with('questions.answers')->findOrFail($qcmId);

        if (!$qcm->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce QCM n\'est pas disponible'
            ], 403);
        }

        // Vérifier si l'étudiant a déjà soumis ses réponses
        $existingResult = Result::where('student_id', $user->id)
                               ->where('qcm_id', $qcmId)
                               ->first();

        if ($existingResult) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà soumis vos réponses pour ce QCM'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'responses' => 'required|array',
            'responses.*.question_id' => 'required|exists:questions,id',
            'responses.*.answer_id' => 'nullable|exists:answers,id',
            'responses.*.response_text' => 'nullable|string',
            'started_at' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $startedAt = new \DateTime($request->started_at);
            $completedAt = now();
            $timeSpent = $completedAt->getTimestamp() - $startedAt->getTimestamp();

            $correctAnswers = 0;
            $pointsEarned = 0;
            $totalPoints = 0;

            // Sauvegarder les réponses et calculer le score
            foreach ($request->responses as $responseData) {
                $question = $qcm->questions->where('id', $responseData['question_id'])->first();
                
                if (!$question) continue;

                $totalPoints += $question->points;

                $studentResponse = StudentResponse::create([
                    'student_id' => $user->id,
                    'qcm_id' => $qcmId,
                    'question_id' => $responseData['question_id'],
                    'answer_id' => $responseData['answer_id'] ?? null,
                    'response_text' => $responseData['response_text'] ?? null,
                    'answered_at' => now(),
                ]);

                // Vérifier si la réponse est correcte
                if ($studentResponse->isCorrect()) {
                    $correctAnswers++;
                    $pointsEarned += $question->points;
                }
            }

            // Calculer le pourcentage
            $percentage = $totalPoints > 0 ? ($pointsEarned / $totalPoints) * 20 : 0; // Sur 20

            // Générer un feedback automatique
            $feedback = $this->generateFeedback($percentage, $correctAnswers, count($request->responses));

            // Créer le résultat
            $result = Result::create([
                'student_id' => $user->id,
                'qcm_id' => $qcmId,
                'total_questions' => count($request->responses),
                'correct_answers' => $correctAnswers,
                'points_earned' => $pointsEarned,
                'total_points' => $totalPoints,
                'percentage' => $percentage,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'time_spent_seconds' => $timeSpent,
                'feedback' => $feedback,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Réponses soumises avec succès',
                'result' => $result
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la soumission des réponses'
            ], 500);
        }
    }

    /**
     * Récupère les réponses d'un étudiant pour un QCM
     */
    public function getStudentResponses($qcmId)
    {
        $user = auth()->user();
        
        $responses = StudentResponse::where('student_id', $user->id)
                                   ->where('qcm_id', $qcmId)
                                   ->with(['question', 'answer'])
                                   ->get();

        if ($responses->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune réponse trouvée pour ce QCM'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'responses' => $responses
        ]);
    }

    /**
     * Génère un feedback automatique basé sur le score
     */
    private function generateFeedback($percentage, $correctAnswers, $totalQuestions)
    {
        $feedback = "Vous avez obtenu {$correctAnswers} bonnes réponses sur {$totalQuestions} questions. ";
        
        if ($percentage >= 16) {
            $feedback .= "Excellent travail ! Vous maîtrisez très bien le sujet.";
        } elseif ($percentage >= 14) {
            $feedback .= "Très bon travail ! Vous avez une bonne compréhension du sujet.";
        } elseif ($percentage >= 12) {
            $feedback .= "Bon travail ! Continuez vos efforts pour approfondir vos connaissances.";
        } elseif ($percentage >= 10) {
            $feedback .= "Résultat passable. Il serait bénéfique de réviser certaines notions.";
        } else {
            $feedback .= "Il est recommandé de revoir le cours et de vous exercer davantage sur ce sujet.";
        }

        return $feedback;
    }
}
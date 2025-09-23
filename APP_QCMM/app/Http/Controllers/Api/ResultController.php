<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\QCM;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    /**
     * Liste les résultats selon le rôle de l'utilisateur
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->isStudent()) {
            // Un étudiant voit ses propres résultats
            $results = Result::where('student_id', $user->id)
                            ->with(['qcm:id,title,description', 'qcm.teacher:id,name,first_name,last_name'])
                            ->orderBy('completed_at', 'desc')
                            ->paginate(10);
        } else {
            // Un enseignant voit les résultats de ses QCMs
            $results = Result::whereHas('qcm', function($query) use ($user) {
                                $query->where('teacher_id', $user->id);
                            })
                            ->with(['student:id,name,first_name,last_name,email', 'qcm:id,title'])
                            ->orderBy('completed_at', 'desc')
                            ->paginate(10);
        }

        return response()->json([
            'success' => true,
            'results' => $results
        ]);
    }

    /**
     * Affiche un résultat spécifique avec détails
     */
    public function show($id)
    {
        $user = auth()->user();
        $result = Result::with([
            'student:id,name,first_name,last_name,email',
            'qcm:id,title,description',
            'qcm.teacher:id,name,first_name,last_name'
        ])->findOrFail($id);

        // Vérification des permissions
        if ($user->isStudent() && $result->student_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez voir que vos propres résultats'
            ], 403);
        }

        if ($user->isTeacher() && $result->qcm->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez voir que les résultats de vos QCMs'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'result' => $result
        ]);
    }

    /**
     * Obtient les statistiques d'un QCM (enseignants uniquement)
     */
    public function getQcmStatistics($qcmId)
    {
        $user = auth()->user();
        
        if (!$user->isTeacher()) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les enseignants peuvent voir les statistiques'
            ], 403);
        }

        $qcm = QCM::findOrFail($qcmId);

        if ($qcm->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez voir que les statistiques de vos QCMs'
            ], 403);
        }

        $results = Result::where('qcm_id', $qcmId)->get();

        if ($results->isEmpty()) {
            return response()->json([
                'success' => true,
                'statistics' => [
                    'total_attempts' => 0,
                    'average_score' => 0,
                    'success_rate' => 0,
                    'average_time' => 0,
                ]
            ]);
        }

        $totalAttempts = $results->count();
        $averageScore = $results->avg('percentage');
        $successRate = $results->where('percentage', '>=', 10)->count() / $totalAttempts * 100;
        $averageTime = $results->avg('time_spent_seconds');

        // Distribution des notes
        $scoreDistribution = [
            'excellent' => $results->where('percentage', '>=', 16)->count(),
            'tres_bien' => $results->whereBetween('percentage', [14, 15.99])->count(),
            'bien' => $results->whereBetween('percentage', [12, 13.99])->count(),
            'passable' => $results->whereBetween('percentage', [10, 11.99])->count(),
            'insuffisant' => $results->where('percentage', '<', 10)->count(),
        ];

        return response()->json([
            'success' => true,
            'statistics' => [
                'total_attempts' => $totalAttempts,
                'average_score' => round($averageScore, 2),
                'success_rate' => round($successRate, 2),
                'average_time_seconds' => round($averageTime, 0),
                'average_time_formatted' => gmdate("H:i:s", $averageTime),
                'score_distribution' => $scoreDistribution,
                'best_score' => $results->max('percentage'),
                'worst_score' => $results->min('percentage'),
            ]
        ]);
    }

    /**
     * Obtient le détail des réponses pour un résultat (avec correction)
     */
    public function getResultDetails($id)
    {
        $user = auth()->user();
        $result = Result::findOrFail($id);

        // Vérification des permissions
        if ($user->isStudent() && $result->student_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        if ($user->isTeacher() && $result->qcm->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        // Récupérer toutes les réponses de l'étudiant avec les corrections
        $studentResponses = $result->qcm->questions()->with([
            'answers',
            'studentResponses' => function($query) use ($result) {
                $query->where('student_id', $result->student_id);
            }
        ])->get();

        $detailedResponses = $studentResponses->map(function($question) {
            $studentResponse = $question->studentResponses->first();
            $correctAnswers = $question->getCorrectAnswers();
            
            return [
                'question' => [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'points' => $question->points,
                    'explanation' => $question->explanation,
                ],
                'all_answers' => $question->answers,
                'student_answer' => $studentResponse ? $studentResponse->answer : null,
                'correct_answers' => $correctAnswers,
                'is_correct' => $studentResponse ? $studentResponse->isCorrect() : false,
                'points_earned' => $studentResponse && $studentResponse->isCorrect() ? $question->points : 0,
            ];
        });

        return response()->json([
            'success' => true,
            'result' => $result,
            'detailed_responses' => $detailedResponses
        ]);
    }

    /**
     * Export des résultats en CSV (enseignants uniquement)
     */
    public function exportResults($qcmId)
    {
        $user = auth()->user();
        
        if (!$user->isTeacher()) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les enseignants peuvent exporter les résultats'
            ], 403);
        }

        $qcm = QCM::findOrFail($qcmId);

        if ($qcm->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        $results = Result::where('qcm_id', $qcmId)
                        ->with('student:id,name,first_name,last_name,email')
                        ->get();

        $csvData = [];
        $csvData[] = [
            'Nom',
            'Prénom', 
            'Email',
            'Score (/20)',
            'Pourcentage',
            'Questions correctes',
            'Total questions',
            'Temps passé',
            'Date de completion',
            'Statut'
        ];

        foreach ($results as $result) {
            $csvData[] = [
                $result->student->last_name,
                $result->student->first_name,
                $result->student->email,
                $result->percentage,
                round(($result->percentage / 20) * 100, 2) . '%',
                $result->correct_answers,
                $result->total_questions,
                $result->getTimeSpentFormatted(),
                $result->completed_at->format('d/m/Y H:i'),
                $result->isPassed() ? 'Réussi' : 'Échoué'
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Données prêtes pour export',
            'csv_data' => $csvData
        ]);
    }
}
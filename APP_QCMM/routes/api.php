<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\QCMController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\StudentResponseController;
use App\Http\Controllers\Api\ResultController;

// Routes d'authentification (publiques)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Routes protégées par JWT
Route::middleware('auth:api')->group(function () {
    
    // Routes d'authentification (protégées)
    Route::prefix('auth')->group(function () {
        Route::get('profile', [AuthController::class, 'profile']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // Routes QCM
    Route::apiResource('qcms', QCMController::class);
    
    // Routes Questions (imbriquées dans QCM)
    Route::prefix('qcms/{qcm}')->group(function () {
        Route::get('questions', [QuestionController::class, 'index']);
        Route::post('questions', [QuestionController::class, 'store']);
        Route::get('questions/{question}', [QuestionController::class, 'show']);
        Route::put('questions/{question}', [QuestionController::class, 'update']);
        Route::delete('questions/{question}', [QuestionController::class, 'destroy']);
        
        // Soumission des réponses d'un étudiant
        Route::post('submit-responses', [StudentResponseController::class, 'submitResponses']);
        
        // Récupérer les réponses d'un étudiant pour un QCM
        Route::get('my-responses', [StudentResponseController::class, 'getStudentResponses']);
        
        // Statistiques d'un QCM (enseignants)
        Route::get('statistics', [ResultController::class, 'getQcmStatistics']);
        
        // Export des résultats (enseignants)
        Route::get('export-results', [ResultController::class, 'exportResults']);
    });

    // Routes Résultats
    Route::prefix('results')->group(function () {
        Route::get('', [ResultController::class, 'index']);
        Route::get('{result}', [ResultController::class, 'show']);
        Route::get('{result}/details', [ResultController::class, 'getResultDetails']);
    });

    // Route de test (pour vérifier que l'API fonctionne)
    Route::get('test', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'API QCM-Net fonctionne !',
            'user' => $request->user(),
            'timestamp' => now()
        ]);
    });
});

// Route de santé publique
Route::get('health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API QCM-Net est en ligne',
        'version' => '1.0.0',
        'timestamp' => now()
    ]);
}); 
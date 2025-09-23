<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('qcm_id')->constrained('qcms')->onDelete('cascade');
            $table->integer('total_questions');
            $table->integer('correct_answers');
            $table->integer('points_earned');
            $table->integer('total_points');
            $table->decimal('percentage', 5, 2); // Pourcentage avec 2 décimales
            $table->datetime('started_at');
            $table->datetime('completed_at');
            $table->integer('time_spent_seconds'); // Temps passé en secondes
            $table->text('feedback')->nullable(); // Feedback automatique
            $table->timestamps();

            // Un étudiant ne peut avoir qu'un résultat par QCM
            $table->unique(['student_id', 'qcm_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
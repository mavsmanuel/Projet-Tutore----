<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'qcm_id',
        'total_questions',
        'correct_answers',
        'points_earned',
        'total_points',
        'percentage',
        'started_at',
        'completed_at',
        'time_spent_seconds',
        'feedback',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'percentage' => 'decimal:2',
    ];

    // Relations
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function qcm()
    {
        return $this->belongsTo(QCM::class);
    }

    // Helper methods
    public function getGrade()
    {
        if ($this->percentage >= 16) return 'Excellent';
        if ($this->percentage >= 14) return 'Très Bien';
        if ($this->percentage >= 12) return 'Bien';
        if ($this->percentage >= 10) return 'Passable';
        return 'Insuffisant';
    }

    public function getTimeSpentFormatted()
    {
        $minutes = floor($this->time_spent_seconds / 60);
        $seconds = $this->time_spent_seconds % 60;
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function isPassed()
    {
        return $this->percentage >= 10; // Seuil de réussite à 50%
    }
}
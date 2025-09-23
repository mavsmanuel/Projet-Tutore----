<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'qcm_id',
        'question_id',
        'answer_id',
        'response_text',
        'answered_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
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

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function answer()
    {
        return $this->belongsTo(Answer::class);
    }

    // Helper methods
    public function isCorrect()
    {
        if ($this->answer) {
            return $this->answer->is_correct;
        }
        return false;
    }
}
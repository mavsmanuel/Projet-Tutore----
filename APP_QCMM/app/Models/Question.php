<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'qcm_id',
        'question_text',
        'question_type',
        'points',
        'order',
        'explanation',
    ];

    // Relations
    public function qcm()
    {
        return $this->belongsTo(QCM::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class)->orderBy('order');
    }

    public function studentResponses()
    {
        return $this->hasMany(StudentResponse::class);
    }

    // Helper methods
    public function getCorrectAnswers()
    {
        return $this->answers()->where('is_correct', true)->get();
    }

    public function hasMultipleCorrectAnswers()
    {
        return $this->answers()->where('is_correct', true)->count() > 1;
    }
}
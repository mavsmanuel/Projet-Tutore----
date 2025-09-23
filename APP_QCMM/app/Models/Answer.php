<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'answer_text',
        'is_correct',
        'order',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    // Relations
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function studentResponses()
    {
        return $this->hasMany(StudentResponse::class);
    }
}
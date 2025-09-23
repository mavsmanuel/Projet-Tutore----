<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QCM extends Model
{
    use HasFactory;

    protected $table = 'qcms';

    protected $fillable = [
        'title',
        'description',
        'teacher_id',
        'duration_minutes',
        'is_published',
        'available_from',
        'available_until',
    ];

    protected $casts = [
        'available_from' => 'datetime',
        'available_until' => 'datetime',
        'is_published' => 'boolean',
    ];

    // Relations
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function questions()
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }

    public function studentResponses()
    {
        return $this->hasMany(StudentResponse::class);
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }

    // Helper methods
    public function isAvailable()
    {
        $now = now();
        return $this->is_published && 
               (!$this->available_from || $now >= $this->available_from) &&
               (!$this->available_until || $now <= $this->available_until);
    }

    public function getTotalPoints()
    {
        return $this->questions->sum('points');
    }

    public function getTotalQuestions()
    {
        return $this->questions->count();
    }
}
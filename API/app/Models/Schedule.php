<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'class_subject_teacher_id',
        'day',
        'start_time',
        'end_time',
        'room',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    // Relationships
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ClassSubjectTeacher::class, 'class_subject_teacher_id');
    }

    // Convenience accessor methods through assignment
    public function getClassAttribute()
    {
        return $this->assignment->class ?? null;
    }

    public function getSubjectAttribute()
    {
        return $this->assignment->subject ?? null;
    }

    public function getTeacherAttribute()
    {
        return $this->assignment->teacher ?? null;
    }

    // Scopes
    public function scopeForDay($query, $day)
    {
        return $query->where('day', $day);
    }
}

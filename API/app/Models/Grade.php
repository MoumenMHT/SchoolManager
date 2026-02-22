<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    use HasFactory;
    protected $fillable = [
        'student_id',
        'subject_id',
        'teacher_id',
        'exam_type',
        'grade',
        'max_grade',
        'semester',
        'academic_year',
        'comment',
    ];

    protected $casts = [
        'grade' => 'decimal:2',
        'max_grade' => 'decimal:2',
    ];

    // Relationships
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    // Helper methods
    public function getPercentageAttribute()
    {
        return ($this->grade / $this->max_grade) * 100;
    }
}

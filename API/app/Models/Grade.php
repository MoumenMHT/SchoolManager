<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'exam_id',
        'grade',
        'comment',
    ];

    protected $casts = [
        'grade' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** Shortcut to get the subject through the exam. */
    public function subject(): BelongsTo
    {
        return $this->exam->subject();
    }

    /** Shortcut to get the teacher through the exam. */
    public function teacher(): BelongsTo
    {
        return $this->exam->teacher();
    }

    /** Per-exercise notes for this student on this grade/exam. */
    public function exerciseGrades(): HasMany
    {
        return $this->hasMany(ExerciseGrade::class);
    }

    // ─── Helper attributes ────────────────────────────────────────────────────

    /**
     * Percentage score relative to the exam's max_grade, normalised to /20.
     */
    public function getPercentageAttribute(): float
    {
        $maxGrade = $this->exam?->max_grade ?? 20;
        return $maxGrade > 0 ? ($this->grade / $maxGrade) * 20 : 0;
    }
}

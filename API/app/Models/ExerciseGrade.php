<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'grade_id',
        'exam_exercise_id',
        'note',
    ];

    protected $casts = [
        'note' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /** The student's overall grade record this exercise note belongs to. */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /** The exercise definition this note is for. */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(ExamExercise::class, 'exam_exercise_id');
    }
}

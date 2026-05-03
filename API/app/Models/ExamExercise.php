<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamExercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'level_name',
        'max_note',
    ];

    protected $casts = [
        'max_note' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** All student notes recorded for this specific exercise. */
    public function exerciseGrades(): HasMany
    {
        return $this->hasMany(ExerciseGrade::class);
    }

    // ─── Analytics helpers ────────────────────────────────────────────────────

    /** Average note scored on this exercise across all students. */
    public function averageNote(): float
    {
        return round((float) $this->exerciseGrades()->avg('note'), 2);
    }
}

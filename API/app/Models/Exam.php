<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'teacher_id',
        'exam_type',
        'semester',
        'academic_year',
        'max_grade',
    ];

    protected $casts = [
        'max_grade' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** Classes that are assigned this exam (many-to-many via class_exam pivot). */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_exam', 'exam_id', 'class_id');
    }

    /** The exercise/level definitions that make up this exam. */
    public function exercises(): HasMany
    {
        return $this->hasMany(ExamExercise::class);
    }

    /** All student grade rows for this exam. */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}

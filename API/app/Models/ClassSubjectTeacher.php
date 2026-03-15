<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSubjectTeacher extends Model
{
    use HasFactory;
    
    protected $table = 'class_subject_teacher';

    protected $fillable = [
        'class_id',
        'subject_id',
        'teacher_id',
        'academic_year',
        'coefficient',
    ];

    protected $casts = [
        'coefficient' => 'integer',
    ];

    // Relationships
    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'class_subject_teacher_id');
    }

    // Scopes
    public function scopeForAcademicYear($query, $year)
    {
        return $query->where('academic_year', $year);
    }

    public function scopeForClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForSubject($query, $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }

    // Helper method to auto-fill coefficient based on subject and class level
    public static function getCoefficientForAssignment($subjectId, $classId)
    {
        $class = SchoolClass::find($classId);
        if (!$class) {
            return null;
        }

        return LevelSubject::getCoefficient($subjectId, $class->level_id);
    }

    // Validation: Check if teacher can teach the subject
    public function validateTeacherSubject(): bool
    {
        return TeacherSubject::where('teacher_id', $this->teacher_id)
            ->where('subject_id', $this->subject_id)
            ->exists();
    }

    // Override save to auto-fill coefficient if not provided
    public function save(array $options = [])
    {
        if (!$this->coefficient && $this->class_id && $this->subject_id) {
            $this->coefficient = self::getCoefficientForAssignment($this->subject_id, $this->class_id);
        }

        return parent::save($options);
    }
}

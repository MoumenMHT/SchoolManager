<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subject extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    protected $casts = [
        //
    ];

    // Relationships
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subject_teacher')
            ->withPivot('teacher_id', 'academic_year', 'coefficient')
            ->withTimestamps();
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'class_subject_teacher')
            ->withPivot('class_id', 'academic_year', 'coefficient')
            ->withTimestamps();
    }

    // New: Direct relationship to teachers who can teach this subject
    public function qualifiedTeachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'teacher_subjects')
            ->withTimestamps();
    }

    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class);
    }

    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(Level::class, 'level_subjects')
            ->withPivot(['coefficient', 'weekly_sessions_required'])
            ->withTimestamps();
    }

    public function coefficients(): HasMany
    {
        return $this->hasMany(LevelSubject::class);
    }

    public function getCoefficientForLevel(?int $levelId)
    {
        return LevelSubject::getCoefficient($this->id, $levelId);
    }
}

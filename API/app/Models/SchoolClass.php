<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SchoolClass extends Model
{
    use HasFactory;
    protected $table = 'classes';

    protected $fillable = [
        'name',
        'level',
        'level_id',
        'academic_year',
        'capacity',
        'main_teacher_id',
        'supervisor_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
    ];

    // Relationships
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function mainTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'main_teacher_id');
    }

    public function levelProfile(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'level_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'class_id');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'class_subject_teacher', 'class_id', 'teacher_id')
            ->withPivot('subject_id', 'academic_year')
            ->withTimestamps();
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject_teacher', 'class_id', 'subject_id')
            ->withPivot('teacher_id', 'academic_year')
            ->withTimestamps();
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class, 'supervisor_id');
    }

    // Helper methods
    public function getCurrentStudentCount()
    {
        return $this->students()->where('is_active', true)->count();
    }

    public function hasAvailableSeats()
    {
        return $this->getCurrentStudentCount() < $this->capacity;
    }
}

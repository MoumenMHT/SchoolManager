<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAverage extends Model
{
    use HasFactory;

    protected $table = 'student_averages';

    protected $fillable = [
        'student_id',
        'class_id',
        'subject_id',
        'record_type',
        'trimester',
        'academic_year',
        'average',
    ];

    /**
     * Ensure we can upsert quickly without worrying about casts changing precision logic.
     */
    protected $casts = [
        'average' => 'float',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}

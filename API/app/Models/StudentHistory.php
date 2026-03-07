<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentHistory extends Model
{
    use HasFactory;

    protected $table = 'student_history';

    protected $fillable = [
        'student_id',
        'class_id',
        'academic_year',
        'enrolled_at',
        'left_at',
    ];

    protected $casts = [
        'enrolled_at' => 'date',
        'left_at' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}

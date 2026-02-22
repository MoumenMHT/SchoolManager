<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectCoefficient extends Model
{
    protected $table = 'subject_coefficients';

    protected $fillable = [
        'subject_id',
        'class_level',
        'coefficient',
    ];

    protected $casts = [
        'coefficient' => 'integer',
    ];

    // Relationships
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    // Helper method to get coefficient for a subject and class level
    public static function getCoefficient($subjectId, $classLevel)
    {
        $coefficient = self::where('subject_id', $subjectId)
            ->where('class_level', $classLevel)
            ->first();

        return $coefficient ? $coefficient->coefficient : null;
    }
}

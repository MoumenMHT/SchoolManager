<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'level_id',
        'subject_id',
        'coefficient',
        'weekly_sessions_required',
    ];

    protected $casts = [
        'coefficient' => 'integer',
        'weekly_sessions_required' => 'integer',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public static function getCoefficient(int $subjectId, ?int $levelId): ?int
    {
        if (!$levelId) {
            return null;
        }

        return self::where('subject_id', $subjectId)
            ->where('level_id', $levelId)
            ->value('coefficient');
    }

    public static function getWeeklySessionsRequired(int $subjectId, ?int $levelId): ?int
    {
        if (!$levelId) {
            return null;
        }

        return self::where('subject_id', $subjectId)
            ->where('level_id', $levelId)
            ->value('weekly_sessions_required');
    }
}

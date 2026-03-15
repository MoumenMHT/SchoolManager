<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Level extends Model
{
    use HasFactory;

    protected $fillable = [
        'cycle',
        'year_number',
        'track',
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'year_number' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function levelSubjects(): HasMany
    {
        return $this->hasMany(LevelSubject::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'level_subjects')
            ->withPivot(['coefficient', 'weekly_sessions_required'])
            ->withTimestamps();
    }

    public function fees(): BelongsToMany
    {
        return $this->belongsToMany(Fee::class, 'fee_levels')->withTimestamps();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Fee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'base_amount',
        'academic_year',
        'is_active',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function parentFees()
    {
        return $this->hasMany(ParentFee::class);
    }

    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(Level::class, 'fee_levels')->withTimestamps();
    }
}

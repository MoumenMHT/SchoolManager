<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Contract;

class ParentModel extends Model
{
    use HasFactory;
    protected $table = 'parents';

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'cin',
        'profession',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods
    public function hasAccount(): bool
    {
        return !is_null($this->user_id);
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getContactEmailAttribute(): ?string
    {
        return $this->hasAccount() ? $this->user->email : $this->email;
    }

    public function getContactPhoneAttribute(): ?string
    {
        return $this->hasAccount() ? $this->user->phone : $this->phone;
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'parent_id');
    }

    public function fees()
    {
        return $this->belongsToMany(Fee::class, 'parents_fees', 'parent_id', 'fee_id')
            ->whereNull('parents_fees.student_id');
    }

    public function studentFees()
    {
        return $this->hasMany(ParentFee::class, 'parent_id')
            ->whereNotNull('student_id')
            ->with(['fee', 'student']);
    }

    public function payments()
    {
        return $this->hasManyThrough(Payment::class, Student::class, 'parent_id', 'student_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'parent_id');
    }
}

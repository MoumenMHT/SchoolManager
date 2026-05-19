<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'old_contract_id',
        'contract_number',
        'academic_year',
        'total_fees',
        'discount_type',
        'discount_value',
        'discount_reason',
        'monthly_amount',
        'paid_amount',
        'remaining_amount',
        'balance',
        'start_date',
        'end_date',
        'notes',
        'status',
        'is_active',
    ];

    protected $casts = [
        'total_fees' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'monthly_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($contract) {
            if (empty($contract->contract_number)) {
                $contract->contract_number = 'CNT-' . date('Y') . '-' . str_pad(static::max('id') + 1, 6, '0', STR_PAD_LEFT);
            }
        });
    }
}

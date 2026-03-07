<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'month_year',
        'amount_due',
        'amount_paid',
        'balance',
        'status',
        'due_date',
        'note',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function updateStatus()
    {
        if ($this->amount_paid >= $this->amount_due) {
            $this->status = 'paid';
        } elseif ($this->amount_paid > 0) {
            $this->status = 'partial';
        } elseif ($this->due_date < now() && $this->amount_paid == 0) {
            $this->status = 'late';
        } else {
            $this->status = 'unpaid';
        }
        
        $this->balance = $this->amount_due - $this->amount_paid;
        $this->save();
    }
}

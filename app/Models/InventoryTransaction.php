<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'transaction_number', 'item_type', 'item_id', 'transaction_type',
        'quantity', 'balance_before', 'balance_after',
        'reference_type', 'reference_id', 'transaction_date', 'created_by',
    ];

    protected $casts = [
        'quantity'       => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after'  => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}

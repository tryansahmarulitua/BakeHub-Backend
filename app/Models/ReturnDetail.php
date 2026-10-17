<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnDetail extends Model
{
    protected $fillable = [
        'return_transaction_id', 'item_type', 'item_id', 'quantity', 'condition',
    ];

    protected $casts = ['quantity' => 'decimal:2'];

    public function returnTransaction() { return $this->belongsTo(ReturnTransaction::class); }
}

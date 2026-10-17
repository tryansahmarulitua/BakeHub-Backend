<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnTransaction extends Model
{
    protected $fillable = [
        'return_number', 'return_type', 'reference_id', 'return_date',
        'status', 'reason', 'notes', 'created_by',
    ];

    protected $casts = ['return_date' => 'date'];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function details() { return $this->hasMany(ReturnDetail::class, 'return_transaction_id'); }
}

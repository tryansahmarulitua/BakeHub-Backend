<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Waste extends Model
{
    protected $fillable = [
        'waste_number', 'waste_date', 'item_type', 'item_id',
        'quantity', 'reason', 'estimated_loss', 'notes', 'created_by',
    ];

    protected $casts = [
        'waste_date'     => 'date',
        'quantity'       => 'decimal:2',
        'estimated_loss' => 'decimal:2',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}

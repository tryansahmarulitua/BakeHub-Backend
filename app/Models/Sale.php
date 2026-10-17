<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number', 'customer_id', 'sale_date', 'subtotal',
        'discount', 'tax', 'total', 'payment_amount', 'change_amount',
        'payment_method', 'payment_status', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'sale_date'      => 'date',
        'subtotal'       => 'decimal:2',
        'discount'       => 'decimal:2',
        'tax'            => 'decimal:2',
        'total'          => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'change_amount'  => 'decimal:2',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function creator()  { return $this->belongsTo(User::class, 'created_by'); }
    public function details()  { return $this->hasMany(SaleDetail::class); }
}

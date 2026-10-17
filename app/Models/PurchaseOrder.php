<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'nomor_po', 'supplier_id', 'tanggal_po', 'status',
        'subtotal', 'diskon', 'pajak', 'total', 'catatan', 'created_by',
    ];

    protected $casts = [
        'tanggal_po' => 'date',
        'subtotal' => 'decimal:2',
        'diskon' => 'decimal:2',
        'pajak' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function details() { return $this->hasMany(PurchaseOrderDetail::class); }
    public function goodsReceipts() { return $this->hasMany(GoodsReceipt::class); }
}

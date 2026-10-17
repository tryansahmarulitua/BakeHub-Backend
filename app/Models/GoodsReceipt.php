<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'nomor_gr', 'purchase_order_id', 'tanggal_terima',
        'received_by', 'status', 'catatan',
    ];

    protected $casts = ['tanggal_terima' => 'date'];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }
    public function details() { return $this->hasMany(GoodsReceiptDetail::class); }
}

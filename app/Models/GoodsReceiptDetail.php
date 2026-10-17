<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptDetail extends Model
{
    protected $fillable = [
        'goods_receipt_id', 'raw_material_id',
        'quantity_ordered', 'quantity_received', 'quantity_rejected', 'expired_date',
    ];

    protected $casts = [
        'quantity_ordered'  => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'quantity_rejected' => 'decimal:2',
        'expired_date'      => 'date',
    ];

    public function goodsReceipt() { return $this->belongsTo(GoodsReceipt::class); }
    public function rawMaterial()  { return $this->belongsTo(RawMaterial::class); }
}

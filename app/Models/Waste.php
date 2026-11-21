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

    protected $appends = ['waste_code', 'item_code'];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function product() { return $this->belongsTo(Product::class, 'item_id'); }
    public function rawMaterial() { return $this->belongsTo(RawMaterial::class, 'item_id'); }

    public function getWasteCodeAttribute(): string
    {
        return $this->waste_number;
    }

    public function getItemCodeAttribute(): string
    {
        return $this->item_type === 'product'
            ? ($this->product?->kode_produk ?? '-')
            : ($this->rawMaterial?->kode_bahan ?? '-');
    }
}

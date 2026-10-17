<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    protected $fillable = [
        'kode_bahan', 'nama_bahan', 'category_id', 'unit_id', 'harga_beli', 
        'stok', 'stok_minimum', 'tanggal_kedaluwarsa', 'supplier_id', 'status'
    ];

    protected $casts = [
        'harga_beli' => 'decimal:2',
        'stok' => 'float',
        'stok_minimum' => 'float',
        'tanggal_kedaluwarsa' => 'date'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}

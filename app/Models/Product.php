<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'kode_produk', 'nama_produk', 'category_id', 'unit_id', 
        'harga_jual', 'harga_modal', 'stok', 'tanggal_kedaluwarsa', 'status'
    ];

    protected $casts = [
        'harga_jual' => 'decimal:2',
        'harga_modal' => 'decimal:2',
        'stok' => 'integer',
        'tanggal_kedaluwarsa' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }
}

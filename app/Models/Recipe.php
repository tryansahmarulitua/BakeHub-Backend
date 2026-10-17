<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    protected $fillable = [
        'product_id', 'name', 'output_quantity', 'status',
        'biaya_bahan', 'biaya_kemasan', 'overhead_percent',
        'biaya_overhead', 'total_biaya_produksi', 'harga_modal_per_unit',
    ];

    protected $casts = [
        'output_quantity'      => 'decimal:2',
        'biaya_bahan'          => 'decimal:2',
        'biaya_kemasan'        => 'decimal:2',
        'overhead_percent'     => 'decimal:2',
        'biaya_overhead'       => 'decimal:2',
        'total_biaya_produksi' => 'decimal:2',
        'harga_modal_per_unit' => 'decimal:2',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function details() { return $this->hasMany(RecipeDetail::class); }
    public function productions() { return $this->hasMany(Production::class); }
}

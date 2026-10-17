<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    protected $fillable = [
        'nomor_produksi', 'product_id', 'recipe_id', 'quantity',
        'production_date', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'quantity'        => 'decimal:2',
        'production_date' => 'date',
    ];

    public function product()  { return $this->belongsTo(Product::class); }
    public function recipe()   { return $this->belongsTo(Recipe::class); }
    public function creator()  { return $this->belongsTo(User::class, 'created_by'); }
    public function details()  { return $this->hasMany(ProductionDetail::class); }
}

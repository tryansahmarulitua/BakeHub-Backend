<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionDetail extends Model
{
    protected $fillable = [
        'production_id', 'raw_material_id', 'unit_id',
        'quantity_needed', 'quantity_used',
    ];

    protected $casts = [
        'quantity_needed' => 'decimal:2',
        'quantity_used'   => 'decimal:2',
    ];

    public function production()  { return $this->belongsTo(Production::class); }
    public function rawMaterial() { return $this->belongsTo(RawMaterial::class); }
    public function unit()        { return $this->belongsTo(Unit::class); }
}

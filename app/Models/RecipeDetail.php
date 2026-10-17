<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeDetail extends Model
{
    protected $fillable = ['recipe_id', 'raw_material_id', 'unit_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:2'];

    public function recipe() { return $this->belongsTo(Recipe::class); }
    public function rawMaterial() { return $this->belongsTo(RawMaterial::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
}

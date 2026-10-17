<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['name', 'symbol', 'category'];

    public function conversionsFrom()
    {
        return $this->hasMany(UnitConversion::class, 'from_unit_id');
    }

    public function conversionsTo()
    {
        return $this->hasMany(UnitConversion::class, 'to_unit_id');
    }

    public function rawMaterials()
    {
        return $this->hasMany(RawMaterial::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'type'];

    public function rawMaterials()
    {
        return $this->hasMany(RawMaterial::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}

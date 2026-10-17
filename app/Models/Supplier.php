<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'kode_supplier', 'nama_supplier', 'nama_pic', 'nomor_telepon', 
        'email', 'alamat', 'status'
    ];

    public function rawMaterials()
    {
        return $this->hasMany(RawMaterial::class);
    }
}

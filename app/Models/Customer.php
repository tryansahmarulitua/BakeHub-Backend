<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'kode_customer', 'nama', 'nomor_telepon', 'email', 
        'alamat', 'tipe_customer'
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}

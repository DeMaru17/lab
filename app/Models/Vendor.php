<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    // Di dalam class Vendor
    protected $fillable = ['name', 'logo_path']; // Tambahkan fillable

    public function users()
    {
        return $this->hasMany(User::class, 'vendor_id');
    }
}

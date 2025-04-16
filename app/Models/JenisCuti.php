<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisCuti extends Model
{
    use HasFactory;

    // Nama tabel (opsional jika nama tabel sesuai konvensi Laravel)
    protected $table = 'jenis_cuti';

    // Kolom yang dapat diisi (mass assignable)
    protected $fillable = [
        'nama_cuti',
        'durasi_default',
    ];

    // Relasi ke model CutiQuota
    public function cutiQuota()
    {
        return $this->hasMany(CutiQuota::class, 'jenis_cuti_id');
    }

    // Relasi ke model Cuti
    public function cuti()
    {
        // return $this->hasMany(Cuti::class, 'jenis_cuti_id');
    }
}

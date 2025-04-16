<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CutiQuota extends Model
{
    use HasFactory;

    // Nama tabel (opsional jika nama tabel sesuai konvensi Laravel)
    protected $table = 'cuti_quota';

    // Kolom yang dapat diisi (mass assignable)
    protected $fillable = [
        'user_id',
        'jenis_cuti_id',
        'durasi_cuti',
    ];

    // Relasi ke model User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relasi ke model JenisCuti
    public function jenisCuti()
    {
        return $this->belongsTo(JenisCuti::class, 'jenis_cuti_id');
    }
}
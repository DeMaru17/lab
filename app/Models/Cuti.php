<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuti extends Model
{

    protected $table = 'cuti';
    protected $fillable = [
        'user_id',
        'jenis_cuti_id',
        'mulai_cuti',
        'selesai_cuti',
        'lama_cuti',
        'keperluan',
        'alamat_selama_cuti',
        'surat_sakit',
        'status',
        'notes',
    ];

    public function jenisCuti()
    {
        return $this->belongsTo(JenisCuti::class, 'jenis_cuti_id');
    }


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

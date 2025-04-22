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

    protected $casts = [
        'mulai_cuti'        => 'date', // <-- Pastikan ini ada
        'selesai_cuti'      => 'date', // <-- Pastikan ini ada
        'approved_at_asisten' => 'datetime', // <-- Tambahkan ini (jika pakai timestamp)
        'approved_at_manager' => 'datetime', // <-- Tambahkan ini (jika pakai timestamp)
        'rejected_at'       => 'datetime', // <-- Tambahkan ini (jika pakai timestamp)

        // created_at dan updated_at biasanya otomatis di-cast jika $timestamps = true (default)
        // tapi tidak ada salahnya ditambahkan eksplisit jika ragu:
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',

        // Cast password jika ada (tidak relevan di model Cuti)
        // 'password' => 'hashed',
    ];

    public function jenisCuti()
    {
        return $this->belongsTo(JenisCuti::class, 'jenis_cuti_id');
    }


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approverAsisten()
    {
        return $this->belongsTo(User::class, 'approved_by_asisten_id');
    }

    /**
     * Relasi ke User (Approver Manager) 
     */
    public function approverManager()
    {
        return $this->belongsTo(User::class, 'approved_by_manager_id');
    }

    /**
     * Relasi ke User (Rejecter) 
     */
    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }
}

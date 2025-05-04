<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'shifts';

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'crosses_midnight',
        'applicable_gender',
        'is_active',
    ];

    /**
     * Atribut yang harus di-cast ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Casting waktu ke format H:i:s agar mudah dimanipulasi Carbon
        // Jika Anda tidak perlu manipulasi detik, 'H:i' cukup
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'crosses_midnight' => 'boolean',
        'is_active' => 'boolean',
        // created_at dan updated_at otomatis (jika timestamps() ada di migrasi)
    ];

    /**
     * Relasi ke Attendances (Satu Shift bisa dimiliki banyak Absensi).
     * Opsional, tergantung kebutuhan query Anda nanti.
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Relasi ke Users (Satu Shift bisa dimiliki banyak User sebagai shift default).
     * Opsional, jika Anda menambahkan kolom shift_id ke users.
     */
    // public function users()
    // {
    //     return $this->hasMany(User::class);
    // }
}

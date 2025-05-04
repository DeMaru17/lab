<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'attendances';

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'shift_id', // Dipilih saat check-in
        'attendance_date',
        'clock_in_time',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_in_photo_path',
        'clock_in_location_status',
        'clock_out_time',
        'clock_out_latitude',
        'clock_out_longitude',
        'clock_out_photo_path',
        'clock_out_location_status',
        'attendance_status', // Diisi oleh scheduled task
        'notes',
        'is_corrected', // Diubah jika ada koreksi
    ];

    /**
     * Atribut yang harus di-cast ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attendance_date'           => 'date',
        'clock_in_time'             => 'datetime', // Simpan sebagai datetime
        'clock_out_time'            => 'datetime', // Simpan sebagai datetime
        'clock_in_latitude'         => 'decimal:8', // Sesuaikan presisi jika perlu
        'clock_in_longitude'        => 'decimal:8', // Sesuaikan presisi jika perlu
        'clock_out_latitude'        => 'decimal:8',
        'clock_out_longitude'       => 'decimal:8',
        'is_corrected'              => 'boolean',
        // created_at dan updated_at otomatis
    ];

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // --------------------------------------------------------------------

    /**
     * Mendapatkan user (karyawan) yang memiliki data absensi ini.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mendapatkan shift yang dipilih untuk absensi ini.
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * Relasi ke Koreksi Absensi (jika ada).
     * Satu absensi bisa memiliki satu koreksi (atau tidak sama sekali).
     */
    // public function correction()
    // {
    //     return $this->hasOne(AttendanceCorrection::class);
    // }

    // --------------------------------------------------------------------
    // ACCESSORS & MUTATORS (Opsional)
    // --------------------------------------------------------------------

    // Contoh accessor untuk mendapatkan durasi kerja (jika diperlukan)
    // public function getWorkDurationMinutesAttribute(): ?int
    // {
    //     if ($this->clock_in_time && $this->clock_out_time) {
    //         return $this->clock_in_time->diffInMinutes($this->clock_out_time);
    //     }
    //     return null;
    // }
}

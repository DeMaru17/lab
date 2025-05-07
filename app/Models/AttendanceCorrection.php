<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model
{
    use HasFactory; // Jika Anda berencana menggunakan factory nanti

    /**
     * Nama tabel yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'attendance_corrections';

    /**
     * Atribut yang dapat diisi secara massal.
     * Sesuaikan ini berdasarkan kolom di migrasi Anda.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'attendance_id', // Bisa null
        'user_id', // User yg mengajukan
        'correction_date',
        'requested_clock_in', // Jam masuk yg diajukan
        'requested_clock_out', // Jam keluar yg diajukan
        'requested_shift_id', // Shift yg diajukan (jika ada koreksi shift)
        'reason', // Alasan koreksi
        'status', // Akan diisi 'pending' saat dibuat
        'processed_by', // User yg approve/reject (diisi nanti)
        'processed_at', // Waktu approve/reject (diisi nanti)
        'reject_reason', // Alasan reject (diisi nanti)
    ];

    /**
     * Atribut yang harus di-cast ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'correction_date' => 'date',
        // Laravel tidak punya cast 'time' bawaan. Biasanya disimpan sebagai string H:i:s.
        // Kita bisa biarkan sebagai string atau buat accessor/mutator jika perlu manipulasi
        // 'requested_clock_in' => 'datetime:H:i:s', // Opsi jika ingin objek Carbon, tapi bisa jadi rumit
        // 'requested_clock_out' => 'datetime:H:i:s',
        'processed_at' => 'datetime',
        // created_at dan updated_at otomatis di-cast jika $timestamps = true (default)
    ];

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // --------------------------------------------------------------------

    /**
     * Mendapatkan data absensi asli yang dikoreksi (jika ada).
     * Relasi ini bisa null.
     */
    public function originalAttendance()
    {
        // Nama method bisa juga 'attendance' saja jika tidak membingungkan
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    /**
     * Mendapatkan user (karyawan) yang mengajukan koreksi ini.
     */
    public function requester()
    {
        // Nama method bisa juga 'user'
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mendapatkan user (Asisten Manager) yang memproses (approve/reject) koreksi ini.
     * Relasi ini bisa null.
     */
    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Mendapatkan shift yang diajukan dalam koreksi (jika ada).
     * Relasi ini bisa null.
     */
    public function requestedShift()
    {
         return $this->belongsTo(Shift::class, 'requested_shift_id');
    }

}

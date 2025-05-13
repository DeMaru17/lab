<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AttendanceCorrection
 *
 * Model ini merepresentasikan pengajuan koreksi data absensi oleh karyawan.
 * Karyawan dapat mengajukan koreksi jika ada kesalahan pada jam masuk/keluar
 * atau data shift pada absensi aslinya. Pengajuan ini akan melalui proses persetujuan.
 *
 * @property int $id ID unik untuk setiap pengajuan koreksi.
 * @property int|null $attendance_id ID absensi asli yang dikoreksi (bisa null jika koreksi untuk hari Alpha).
 * @property int $user_id ID karyawan yang mengajukan koreksi.
 * @property \Illuminate\Support\Carbon $correction_date Tanggal absensi yang dikoreksi.
 * @property string|null $requested_clock_in Jam masuk yang diajukan oleh karyawan (format H:i).
 * @property string|null $requested_clock_out Jam keluar yang diajukan oleh karyawan (format H:i).
 * @property int|null $requested_shift_id ID shift yang diajukan dalam koreksi.
 * @property string $reason Alasan mengapa koreksi diajukan.
 * @property string $status Status pengajuan koreksi (misal: 'pending', 'approved', 'rejected').
 * @property int|null $processed_by ID pengguna (approver/rejecter) yang memproses pengajuan ini.
 * @property \Illuminate\Support\Carbon|null $processed_at Timestamp kapan pengajuan diproses.
 * @property string|null $reject_reason Alasan penolakan jika statusnya 'rejected'.
 * @property \Illuminate\Support\Carbon|null $created_at Timestamp pembuatan record.
 * @property \Illuminate\Support\Carbon|null $updated_at Timestamp pembaruan record terakhir.
 *
 * @property-read \App\Models\Attendance|null $originalAttendance Relasi ke model Attendance (absensi asli).
 * @property-read \App\Models\User $requester Relasi ke model User (pengaju koreksi).
 * @property-read \App\Models\User|null $processor Relasi ke model User (yang memproses).
 * @property-read \App\Models\Shift|null $requestedShift Relasi ke model Shift (shift yang diajukan).
 *
 * @package App\Models
 */
class AttendanceCorrection extends Model
{
    use HasFactory; // Mengaktifkan penggunaan factory untuk testing atau seeding.

    /**
     * Nama tabel yang terhubung dengan model ini di database.
     *
     * @var string
     */
    protected $table = 'attendance_corrections';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Ini mendefinisikan kolom mana saja yang boleh diisi saat menggunakan metode `create()`
     * atau `update()` pada model dengan array data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'attendance_id',      // ID absensi asli yang dikoreksi, bisa null jika ini koreksi untuk hari Alpha.
        'user_id',            // ID karyawan yang mengajukan koreksi ini.
        'correction_date',    // Tanggal absensi yang ingin dikoreksi.
        'requested_clock_in', // Jam masuk yang diajukan oleh karyawan (format H:i).
        'requested_clock_out',// Jam keluar yang diajukan oleh karyawan (format H:i).
        'requested_shift_id', // ID shift yang diajukan jika ada perubahan shift.
        'reason',             // Alasan mengapa karyawan mengajukan koreksi ini.
        'status',             // Status pengajuan, default 'pending' saat dibuat. Akan diubah oleh approver.
        'processed_by',       // ID pengguna (Asisten Manager) yang menyetujui atau menolak. Diisi saat proses approval.
        'processed_at',       // Timestamp kapan pengajuan ini diproses (disetujui/ditolak).
        'reject_reason',      // Alasan spesifik jika pengajuan ditolak. Diisi oleh approver.
    ];

    /**
     * Atribut yang harus di-cast ke tipe data native.
     * Casting memastikan bahwa ketika Anda mengakses atribut ini, nilainya akan dikonversi
     * ke tipe data yang ditentukan (misalnya, string tanggal menjadi objek Carbon).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'correction_date' => 'date', // Cast ke objek Carbon dengan format tanggal saja.
        // Laravel tidak memiliki tipe cast 'time' bawaan untuk kolom TIME di database.
        // Biasanya, kolom TIME disimpan sebagai string (misalnya 'HH:MM:SS').
        // Jika Anda menyimpan 'requested_clock_in' dan 'requested_clock_out' sebagai string,
        // tidak perlu cast khusus di sini, kecuali Anda ingin memanipulasinya sebagai objek Carbon
        // dengan format waktu tertentu. Namun, ini bisa menjadi kompleks karena hanya menyimpan waktu, bukan tanggal-waktu.
        // 'requested_clock_in' => 'datetime:H:i:s', // Opsi jika ingin Carbon, tapi bisa kompleks jika hanya waktu.
        // 'requested_clock_out' => 'datetime:H:i:s',
        'processed_at' => 'datetime', // Cast ke objek Carbon dengan format tanggal dan waktu.
        // Kolom 'created_at' dan 'updated_at' secara otomatis di-cast jika $timestamps = true (default).
    ];

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // Definisikan bagaimana model ini terhubung dengan model lain.
    // --------------------------------------------------------------------

    /**
     * Mendapatkan data absensi asli yang dikoreksi (jika ada).
     * Pengajuan koreksi mungkin tidak selalu terhubung ke absensi asli,
     * misalnya jika karyawan mengajukan koreksi untuk hari dimana ia dianggap 'Alpha'
     * (tidak ada record absensi sama sekali).
     * Relasi one-to-one (inverse) / belongsTo: Satu koreksi (mungkin) dimiliki oleh satu Attendance.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function originalAttendance()
    {
        // Nama method bisa juga 'attendance' jika tidak membingungkan dengan konteks lain.
        // 'attendance_id' adalah foreign key di tabel 'attendance_corrections'.
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    /**
     * Mendapatkan user (karyawan) yang mengajukan koreksi ini.
     * Relasi one-to-many (inverse) / belongsTo: Satu koreksi dimiliki oleh satu User.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function requester()
    {
        // Nama method bisa juga 'user'. 'user_id' adalah foreign key.
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mendapatkan user (Asisten Manager) yang memproses (menyetujui/menolak) koreksi ini.
     * Relasi ini bisa null jika pengajuan belum diproses.
     * Relasi one-to-many (inverse) / belongsTo: Satu koreksi (mungkin) diproses oleh satu User.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function processor()
    {
        // 'processed_by' adalah foreign key yang merujuk ke ID User yang memproses.
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Mendapatkan shift yang diajukan dalam koreksi (jika ada).
     * Koreksi mungkin melibatkan perubahan shift. Relasi ini bisa null.
     * Relasi one-to-many (inverse) / belongsTo: Satu koreksi (mungkin) merujuk ke satu Shift.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function requestedShift()
    {
         // 'requested_shift_id' adalah foreign key.
         return $this->belongsTo(Shift::class, 'requested_shift_id');
    }

}

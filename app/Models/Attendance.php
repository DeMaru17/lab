<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Attendance
 *
 * Model ini merepresentasikan catatan absensi harian setiap karyawan.
 * Ini mencakup waktu check-in dan check-out, lokasi, foto selfie,
 * serta status akhir absensi setelah diproses.
 *
 * @property int $id ID unik untuk setiap catatan absensi.
 * @property int $user_id ID karyawan yang memiliki absensi ini.
 * @property int|null $shift_id ID shift yang dipilih saat check-in, bisa null jika tidak relevan.
 * @property \Illuminate\Support\Carbon $attendance_date Tanggal absensi dicatat.
 * @property \Illuminate\Support\Carbon|null $clock_in_time Waktu check-in karyawan.
 * @property float|null $clock_in_latitude Koordinat latitude saat check-in.
 * @property float|null $clock_in_longitude Koordinat longitude saat check-in.
 * @property string|null $clock_in_photo_path Path ke file foto selfie saat check-in.
 * @property string|null $clock_in_location_status Status lokasi saat check-in (misal: 'Dalam Radius', 'Luar Radius').
 * @property \Illuminate\Support\Carbon|null $clock_out_time Waktu check-out karyawan.
 * @property float|null $clock_out_latitude Koordinat latitude saat check-out.
 * @property float|null $clock_out_longitude Koordinat longitude saat check-out.
 * @property string|null $clock_out_photo_path Path ke file foto selfie saat check-out.
 * @property string|null $clock_out_location_status Status lokasi saat check-out.
 * @property string|null $attendance_status Status akhir absensi (misal: 'Hadir', 'Terlambat', 'Alpha'). Diisi oleh scheduled task.
 * @property string|null $notes Catatan tambahan terkait absensi (misal: alasan terlambat dari sistem).
 * @property bool $is_corrected Menandakan apakah data absensi ini pernah dikoreksi.
 * @property \Illuminate\Support\Carbon|null $last_correction_reminder_sent_at Timestamp kapan terakhir email pengingat koreksi dikirim.
 * @property \Illuminate\Support\Carbon|null $created_at Timestamp pembuatan record.
 * @property \Illuminate\Support\Carbon|null $updated_at Timestamp pembaruan record terakhir.
 *
 * @property-read \App\Models\User $user Relasi ke model User (karyawan).
 * @property-read \App\Models\Shift|null $shift Relasi ke model Shift.
 *
 * @package App\Models
 */
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
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Ini mendefinisikan kolom mana saja yang boleh diisi saat menggunakan metode `create()` atau `update()`
     * pada model dengan array data.
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
        'last_correction_reminder_sent_at', // Timestamp untuk pengingat
    ];

    /**
     * Atribut yang harus di-cast ke tipe data native.
     * Casting memastikan bahwa ketika Anda mengakses atribut ini, nilainya akan dikonversi
     * ke tipe data yang ditentukan (misalnya, string tanggal menjadi objek Carbon).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attendance_date'           => 'date',       // Cast ke objek Carbon dengan format tanggal saja
        'clock_in_time'             => 'datetime',   // Cast ke objek Carbon dengan format tanggal dan waktu
        'clock_out_time'            => 'datetime',   // Cast ke objek Carbon dengan format tanggal dan waktu
        'clock_in_latitude'         => 'decimal:8',  // Cast ke tipe decimal dengan 8 angka di belakang koma
        'clock_in_longitude'        => 'decimal:8',  // Cast ke tipe decimal dengan 8 angka di belakang koma
        'clock_out_latitude'        => 'decimal:8',  // Cast ke tipe decimal dengan 8 angka di belakang koma
        'clock_out_longitude'       => 'decimal:8',  // Cast ke tipe decimal dengan 8 angka di belakang koma
        'is_corrected'              => 'boolean',    // Cast ke tipe boolean (true/false)
        'last_correction_reminder_sent_at' => 'datetime', // Cast ke objek Carbon untuk timestamp pengingat
        // Kolom 'created_at' dan 'updated_at' secara otomatis di-cast ke datetime jika $timestamps = true (default)
    ];

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // Definisikan bagaimana model ini terhubung dengan model lain.
    // --------------------------------------------------------------------

    /**
     * Mendapatkan user (karyawan) yang memiliki data absensi ini.
     * Relasi one-to-many (inverse) / belongsTo: Satu record absensi dimiliki oleh satu User.
     * `foreign_key` default adalah `user_id`.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mendapatkan shift yang dipilih untuk absensi ini.
     * Relasi one-to-many (inverse) / belongsTo: Satu record absensi (mungkin) memiliki satu Shift.
     * `foreign_key` default adalah `shift_id`.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * Relasi ke Koreksi Absensi (jika ada).
     * Satu absensi bisa memiliki satu koreksi (atau tidak sama sekali).
     * Ini adalah relasi one-to-one.
     * (Saat ini di-comment, bisa diaktifkan jika diperlukan query dari Attendance ke Correction)
     */
    // public function correction()
    // {
    //     return $this->hasOne(AttendanceCorrection::class);
    // }

    // --------------------------------------------------------------------
    // ACCESSORS & MUTATORS (Opsional)
    // Accessor digunakan untuk memformat atribut saat diambil dari model.
    // Mutator digunakan untuk memformat atribut sebelum disimpan ke database.
    // --------------------------------------------------------------------

    // Contoh accessor untuk mendapatkan durasi kerja dalam menit (jika diperlukan)
    // Nama method harus mengikuti format getNamaAtributAttribute.
    // Dapat dipanggil dengan $attendance->work_duration_minutes.
    // public function getWorkDurationMinutesAttribute(): ?int
    // {
    //     if ($this->clock_in_time && $this->clock_out_time) {
    //         // Menggunakan objek Carbon (hasil cast) untuk menghitung selisih
    //         return $this->clock_in_time->diffInMinutes($this->clock_out_time);
    //     }
    //     return null; // Kembalikan null jika salah satu waktu tidak ada
    // }
}

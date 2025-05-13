<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Shift
 *
 * Model ini merepresentasikan jadwal atau shift kerja yang berlaku di perusahaan.
 * Setiap shift memiliki nama, jam mulai, jam selesai, dan informasi tambahan
 * seperti apakah shift tersebut melewati tengah malam atau berlaku untuk jenis kelamin tertentu.
 *
 * @property int $id ID unik untuk setiap shift.
 * @property string $name Nama shift (misal: "Pagi", "Siang", "Malam", "Normal Office").
 * @property \Illuminate\Support\Carbon $start_time Waktu mulai shift (disimpan sebagai datetime dengan format H:i:s).
 * @property \Illuminate\Support\Carbon $end_time Waktu selesai shift (disimpan sebagai datetime dengan format H:i:s).
 * @property bool $crosses_midnight Menandakan apakah shift ini melewati tengah malam (misal: shift malam dari 22:00 - 06:00).
 * @property string $applicable_gender Jenis kelamin yang berlaku untuk shift ini ('Laki-laki', 'Perempuan', atau 'Semua').
 * @property bool $is_active Menandakan apakah shift ini masih aktif dan dapat dipilih.
 * @property \Illuminate\Support\Carbon|null $created_at Timestamp pembuatan record.
 * @property \Illuminate\Support\Carbon|null $updated_at Timestamp pembaruan record terakhir.
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Attendance[] $attendances Relasi ke data absensi yang menggunakan shift ini.
 *
 * @package App\Models
 */
class Shift extends Model
{
    use HasFactory; // Mengaktifkan penggunaan factory jika diperlukan.

    /**
     * Nama tabel yang terhubung dengan model ini di database.
     *
     * @var string
     */
    protected $table = 'shifts';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Mendefinisikan kolom mana saja yang boleh diisi saat menggunakan metode `create()`
     * atau `update()` pada model dengan array data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',             // Nama shift, contoh: "Pagi", "Normal Office", "Malam".
        'start_time',       // Waktu mulai shift (format H:i atau H:i:s).
        'end_time',         // Waktu selesai shift (format H:i atau H:i:s).
        'crosses_midnight', // Boolean (true/false), menandakan apakah shift ini melewati tengah malam.
        'applicable_gender',// Untuk siapa shift ini berlaku ('Laki-laki', 'Perempuan', atau 'Semua').
        'is_active',        // Boolean (true/false), menandakan apakah shift ini masih aktif dan bisa dipilih.
    ];

    /**
     * Atribut yang harus di-cast ke tipe data native.
     * Casting memastikan bahwa ketika Anda mengakses atribut ini, nilainya akan dikonversi
     * ke tipe data yang ditentukan.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Casting kolom TIME (start_time, end_time) ke 'datetime:H:i:s'
        // memungkinkan manipulasi menggunakan objek Carbon seolah-olah itu adalah datetime,
        // tetapi hanya bagian waktu yang relevan. Ini membantu dalam perhitungan dan perbandingan waktu.
        // Jika Anda tidak memerlukan presisi detik, 'datetime:H:i' juga bisa digunakan.
        'start_time' => 'datetime:H:i:s', // Cast ke Carbon, hanya format H:i:s yang signifikan.
        'end_time'   => 'datetime:H:i:s', // Cast ke Carbon, hanya format H:i:s yang signifikan.
        'crosses_midnight' => 'boolean',  // Cast ke tipe boolean (true/false).
        'is_active'        => 'boolean',  // Cast ke tipe boolean (true/false).
        // Kolom 'created_at' dan 'updated_at' secara otomatis di-cast jika $timestamps = true (default).
        // Pastikan kolom timestamps ada di migrasi jika Anda ingin menggunakannya.
    ];

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // Definisikan bagaimana model ini terhubung dengan model lain.
    // --------------------------------------------------------------------

    /**
     * Mendapatkan semua data absensi (Attendance) yang menggunakan shift ini.
     * Relasi one-to-many: Satu Shift bisa dimiliki oleh banyak record Attendance.
     * Ini opsional, tergantung kebutuhan query Anda. Jika Anda sering perlu
     * mengambil semua absensi untuk shift tertentu, relasi ini berguna.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attendances()
    {
        // 'shift_id' adalah foreign key di tabel 'attendances'.
        return $this->hasMany(Attendance::class);
    }

    /**
     * Mendapatkan semua pengguna (User) yang memiliki shift ini sebagai shift default mereka.
     * Relasi one-to-many: Satu Shift bisa menjadi default untuk banyak User.
     * Ini opsional dan memerlukan penambahan kolom 'default_shift_id' (atau serupa)
     * di tabel 'users' yang merujuk ke ID shift ini.
     * (Saat ini di-comment, aktifkan dan sesuaikan jika Anda mengimplementasikan fitur shift default untuk user).
     */
    // public function users()
    // {
    //     // Asumsi ada kolom 'default_shift_id' di tabel 'users'.
    //     return $this->hasMany(User::class, 'default_shift_id');
    // }
}

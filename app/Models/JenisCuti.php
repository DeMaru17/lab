<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class JenisCuti
 *
 * Model ini merepresentasikan berbagai jenis cuti yang tersedia dalam sistem,
 * seperti Cuti Tahunan, Cuti Sakit, Cuti Melahirkan, dll. Setiap jenis cuti
 * memiliki nama dan durasi default (dalam hari) yang bisa digunakan sebagai acuan.
 *
 * @property int $id ID unik untuk setiap jenis cuti.
 * @property string $nama_cuti Nama atau deskripsi dari jenis cuti (misal: "Cuti Tahunan", "Cuti Sakit").
 * @property int $durasi_default Durasi standar (dalam hari) untuk jenis cuti ini. Bisa digunakan sebagai acuan awal kuota.
 * @property \Illuminate\Support\Carbon|null $created_at Timestamp pembuatan record.
 * @property \Illuminate\Support\Carbon|null $updated_at Timestamp pembaruan record terakhir.
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\CutiQuota[] $cutiQuota Relasi ke kuota cuti yang terkait dengan jenis ini.
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Cuti[] $cuti Relasi ke pengajuan cuti yang menggunakan jenis ini (saat ini di-comment).
 *
 * @package App\Models
 */
class JenisCuti extends Model
{
    use HasFactory; // Mengaktifkan penggunaan factory jika diperlukan.

    /**
     * Nama tabel yang terhubung dengan model ini di database.
     * Opsional jika nama tabel mengikuti konvensi Laravel (plural snake_case dari nama model).
     *
     * @var string
     */
    protected $table = 'jenis_cuti';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Mendefinisikan kolom mana saja yang boleh diisi saat menggunakan metode `create()`
     * atau `update()` pada model dengan array data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nama_cuti',      // Nama jenis cuti, misalnya "Cuti Tahunan", "Cuti Sakit".
        'durasi_default', // Durasi standar (dalam hari) untuk jenis cuti ini.
                          // Dapat digunakan sebagai nilai awal saat generate kuota.
    ];

    // Tidak ada $casts yang didefinisikan secara eksplisit di sini,
    // karena 'nama_cuti' adalah string dan 'durasi_default' adalah integer.
    // 'created_at' dan 'updated_at' akan otomatis di-cast ke datetime jika $timestamps true (default).

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // Definisikan bagaimana model ini terhubung dengan model lain.
    // --------------------------------------------------------------------

    /**
     * Mendapatkan semua record kuota cuti (CutiQuota) yang terkait dengan jenis cuti ini.
     * Relasi one-to-many: Satu JenisCuti bisa memiliki banyak CutiQuota (untuk user yang berbeda).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cutiQuota()
    {
        // 'jenis_cuti_id' adalah foreign key di tabel 'cuti_quota'.
        return $this->hasMany(CutiQuota::class, 'jenis_cuti_id');
    }

    /**
     * Mendapatkan semua pengajuan cuti (Cuti) yang menggunakan jenis cuti ini.
     * Relasi one-to-many: Satu JenisCuti bisa digunakan oleh banyak pengajuan Cuti.
     * (Saat ini di-comment, bisa diaktifkan jika diperlukan query dari JenisCuti ke Cuti).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    // public function cuti()
    // {
    //     // 'jenis_cuti_id' adalah foreign key di tabel 'cuti'.
    //     return $this->hasMany(Cuti::class, 'jenis_cuti_id');
    // }
}

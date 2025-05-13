<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CutiQuota
 *
 * Model ini merepresentasikan sisa kuota cuti yang dimiliki oleh setiap karyawan
 * untuk setiap jenis cuti tertentu. Data ini digunakan untuk validasi saat pengajuan cuti
 * dan untuk menampilkan informasi sisa kuota kepada karyawan dan manajemen.
 *
 * @property int $id ID unik untuk setiap record kuota cuti.
 * @property int $user_id ID karyawan yang memiliki kuota ini.
 * @property int $jenis_cuti_id ID jenis cuti yang kuotanya dicatat.
 * @property int $durasi_cuti Jumlah hari sisa kuota cuti untuk jenis cuti tersebut.
 * @property \Illuminate\Support\Carbon|null $created_at Timestamp pembuatan record.
 * @property \Illuminate\Support\Carbon|null $updated_at Timestamp pembaruan record terakhir.
 *
 * @property-read \App\Models\User $user Relasi ke model User (pemilik kuota).
 * @property-read \App\Models\JenisCuti $jenisCuti Relasi ke model JenisCuti.
 *
 * @package App\Models
 */
class CutiQuota extends Model
{
    use HasFactory; // Mengaktifkan penggunaan factory jika diperlukan.

    /**
     * Nama tabel yang terhubung dengan model ini di database.
     * Opsional jika nama tabel mengikuti konvensi Laravel (plural snake_case dari nama model).
     *
     * @var string
     */
    protected $table = 'cuti_quota';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Mendefinisikan kolom mana saja yang boleh diisi saat menggunakan metode `create()`
     * atau `update()` pada model dengan array data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',        // ID karyawan pemilik kuota.
        'jenis_cuti_id',  // ID jenis cuti yang terkait dengan kuota ini.
        'durasi_cuti',    // Jumlah sisa hari kuota untuk jenis cuti tersebut.
    ];

    // Tidak ada $casts yang didefinisikan secara eksplisit di sini,
    // karena 'user_id', 'jenis_cuti_id', dan 'durasi_cuti' adalah integer.
    // 'created_at' dan 'updated_at' akan otomatis di-cast ke datetime jika $timestamps true (default).

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // Definisikan bagaimana model ini terhubung dengan model lain.
    // --------------------------------------------------------------------

    /**
     * Mendapatkan pengguna (karyawan) yang memiliki kuota cuti ini.
     * Relasi one-to-many (inverse) / belongsTo: Satu record CutiQuota dimiliki oleh satu User.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        // 'user_id' adalah foreign key di tabel 'cuti_quota'.
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mendapatkan jenis cuti yang terkait dengan kuota ini.
     * Relasi one-to-many (inverse) / belongsTo: Satu record CutiQuota merujuk ke satu JenisCuti.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function jenisCuti()
    {
        // 'jenis_cuti_id' adalah foreign key di tabel 'cuti_quota'.
        return $this->belongsTo(JenisCuti::class, 'jenis_cuti_id');
    }
}

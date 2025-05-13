<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// Tambahkan use HasFactory jika berencana menggunakan factory
// use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class Vendor
 *
 * Model ini merepresentasikan data vendor atau perusahaan pihak ketiga yang
 * mungkin menyediakan karyawan (outsourcing) untuk perusahaan utama.
 * Setiap vendor memiliki nama dan bisa memiliki logo.
 *
 * @property int $id ID unik untuk setiap vendor.
 * @property string $name Nama vendor.
 * @property string|null $logo_path Path ke file gambar logo vendor.
 * @property \Illuminate\Support\Carbon|null $created_at Timestamp pembuatan record.
 * @property \Illuminate\Support\Carbon|null $updated_at Timestamp pembaruan record terakhir.
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $users Relasi ke pengguna (karyawan) yang terafiliasi dengan vendor ini.
 *
 * @package App\Models
 */
class Vendor extends Model
{
    // use HasFactory; // Aktifkan jika Anda menggunakan factories.

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Mendefinisikan kolom mana saja yang boleh diisi saat menggunakan metode `create()`
     * atau `update()` pada model dengan array data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',      // Nama vendor.
        'logo_path', // Path ke file logo vendor (bisa null jika tidak ada logo).
    ];

    // Tidak ada $casts yang didefinisikan secara eksplisit di sini,
    // karena 'name' dan 'logo_path' adalah string.
    // 'created_at' dan 'updated_at' akan otomatis di-cast ke datetime jika $timestamps true (default).

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // Definisikan bagaimana model ini terhubung dengan model lain.
    // --------------------------------------------------------------------

    /**
     * Mendapatkan semua pengguna (User) yang terafiliasi dengan vendor ini.
     * Relasi one-to-many: Satu Vendor bisa memiliki banyak User.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users()
    {
        // 'vendor_id' adalah foreign key di tabel 'users' yang merujuk ke ID vendor ini.
        return $this->hasMany(User::class, 'vendor_id');
    }
}

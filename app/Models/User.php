<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
// Models CutiQuota dan JenisCuti digunakan dalam event 'created' untuk generate kuota awal.
use App\Models\CutiQuota;
use App\Models\JenisCuti;
use Carbon\Carbon; // Untuk manipulasi tanggal, terutama dalam accessor dan event.
use Illuminate\Support\Facades\Log; // Untuk logging informasi dan error saat event 'created'.

/**
 * Class User
 *
 * Model ini merepresentasikan pengguna sistem, yang bisa berupa karyawan (personil),
 * manajemen, atau admin. Model ini meng-extend Authenticatable untuk fungsionalitas
 * autentikasi Laravel dan menggunakan trait Notifiable untuk fitur notifikasi.
 *
 * @property int $id ID unik untuk setiap pengguna.
 * @property string $name Nama lengkap pengguna.
 * @property string $email Alamat email pengguna, digunakan untuk login dan notifikasi.
 * @property string $jenis_kelamin Jenis kelamin pengguna ('Laki-laki' atau 'Perempuan').
 * @property string $password Password pengguna yang sudah di-hash.
 * @property string $role Peran pengguna dalam sistem (misal: 'personil', 'manajemen', 'admin').
 * @property string $jabatan Jabatan pengguna (misal: 'Analis', 'Manager', 'Admin Sistem').
 * @property \Illuminate\Support\Carbon|null $tanggal_mulai_bekerja Tanggal pengguna mulai bekerja.
 * @property int|null $vendor_id ID vendor tempat pengguna bernaung (jika outsourcing).
 * @property string|null $signature_path Path ke file gambar tanda tangan digital pengguna.
 * @property \Illuminate\Support\Carbon|null $email_verified_at Timestamp verifikasi email (jika fitur ini digunakan).
 * @property string|null $remember_token Token untuk fitur "remember me" saat login.
 * @property \Illuminate\Support\Carbon|null $created_at Timestamp pembuatan record.
 * @property \Illuminate\Support\Carbon|null $updated_at Timestamp pembaruan record terakhir.
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\CutiQuota[] $cutiQuotas Relasi ke kuota cuti milik pengguna.
 * @property-read \App\Models\Vendor|null $vendor Relasi ke model Vendor.
 * @property-read int $lama_bekerja Accessor untuk mendapatkan lama bekerja dalam bulan.
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications Notifikasi milik pengguna.
 *
 * @package App\Models
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable; // Menggunakan trait HasFactory dan Notifiable.

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Mendefinisikan kolom mana saja yang boleh diisi saat menggunakan metode `create()`
     * atau `update()` pada model dengan array data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'jenis_kelamin',
        'password',           // Akan di-hash otomatis oleh mutator atau event.
        'role',               // Ditentukan berdasarkan jabatan saat pembuatan user.
        'jabatan',
        'tanggal_mulai_bekerja',
        'vendor_id',          // Bisa null jika karyawan internal.
        'signature_path',     // Path ke file tanda tangan digital.
    ];

    /**
     * Atribut yang harus disembunyikan saat serialisasi model (misalnya, saat dikonversi ke JSON).
     * Biasanya digunakan untuk menyembunyikan informasi sensitif seperti password.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',         // Sembunyikan password.
        'remember_token',   // Sembunyikan token "remember me".
    ];

    /**
     * Atribut yang harus di-cast ke tipe data native.
     * Casting memastikan bahwa ketika Anda mengakses atribut ini, nilainya akan dikonversi
     * ke tipe data yang ditentukan.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',      // Cast ke objek Carbon (jika fitur verifikasi email digunakan).
        'tanggal_mulai_bekerja' => 'date',      // Cast ke objek Carbon (format tanggal).
        'password' => 'hashed',                // Otomatis hash password saat diset (Laravel 9+).
                                               // Untuk Laravel < 9, hashing dilakukan manual di mutator atau controller.
    ];

    // Untuk Laravel 9+, $casts array bisa juga didefinisikan dalam method casts():
    /**
     * Get the attributes that should be cast. (Metode alternatif untuk $casts di Laravel 9+)
     *
     * @return array<string, string>
     */
    // protected function casts(): array
    // {
    //     return [
    //         'email_verified_at' => 'datetime',
    //         'password' => 'hashed',
    //         'tanggal_mulai_bekerja' => 'date',
    //     ];
    // }

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // Definisikan bagaimana model ini terhubung dengan model lain.
    // --------------------------------------------------------------------

    /**
     * Mendapatkan semua record kuota cuti (CutiQuota) yang dimiliki oleh pengguna ini.
     * Relasi one-to-many: Satu User bisa memiliki banyak CutiQuota (untuk berbagai jenis cuti).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cutiQuotas()
    {
        return $this->hasMany(CutiQuota::class);
    }

    /**
     * Mendapatkan data vendor tempat pengguna ini bernaung (jika ada).
     * Relasi one-to-many (inverse) / belongsTo: Satu User (mungkin) dimiliki oleh satu Vendor.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vendor()
    {
        // 'vendor_id' adalah foreign key di tabel 'users'.
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    // --------------------------------------------------------------------
    // ACCESSORS & MUTATORS
    // Accessor digunakan untuk memformat atribut saat diambil.
    // Mutator digunakan untuk memformat atribut sebelum disimpan.
    // --------------------------------------------------------------------

    /**
     * Accessor untuk menghitung lama bekerja pengguna dalam satuan bulan.
     * Dihitung dari 'tanggal_mulai_bekerja' hingga tanggal saat ini.
     * Dapat diakses sebagai properti: `$user->lama_bekerja`.
     *
     * @return int Jumlah bulan lama bekerja. Mengembalikan 0 jika tanggal mulai bekerja tidak ada.
     */
    public function getLamaBekerjaAttribute(): int
    {
        // Jika tanggal mulai bekerja tidak ada, kembalikan 0.
        if (!$this->tanggal_mulai_bekerja) {
            return 0;
        }
        // Parse 'tanggal_mulai_bekerja' (sudah menjadi Carbon karena $casts)
        // dan hitung selisihnya dalam bulan dengan tanggal saat ini.
        return Carbon::parse($this->tanggal_mulai_bekerja)->diffInMonths(now(config('app.timezone', 'Asia/Jakarta')));
    }

    // --------------------------------------------------------------------
    // MODEL EVENTS
    // Logika yang dijalankan secara otomatis saat event tertentu pada model terjadi.
    // --------------------------------------------------------------------

    /**
     * Method `booted` dijalankan saat model diinisialisasi.
     * Digunakan di sini untuk mendaftarkan event listener 'created'.
     *
     * @return void
     */
    protected static function booted(): void
    {
        /**
         * Event 'created' berjalan setelah record User baru berhasil dibuat dan disimpan ke database.
         * Digunakan di sini untuk secara otomatis membuat kuota cuti awal untuk pengguna baru tersebut.
         *
         * @param  \App\Models\User  $user Instance model User yang baru saja dibuat.
         * @return void
         */
        static::created(function ($user) {
            // Jika tanggal mulai bekerja tidak diisi, lewati proses pembuatan kuota.
            if (!$user->tanggal_mulai_bekerja) {
                Log::info("User creation event: Skipping leave quota generation for User ID {$user->id} due to missing start date.");
                return;
            }

            // Ambil semua jenis cuti yang ada di sistem.
            $jenisCutiAll = JenisCuti::all();

            // Loop untuk setiap jenis cuti.
            foreach ($jenisCutiAll as $jenisCuti) {
                // Logika khusus untuk "Cuti Tahunan":
                // Hanya diberikan jika karyawan telah bekerja minimal 12 bulan.
                // Pada saat 'created', lama bekerja biasanya 0, jadi Cuti Tahunan
                // akan diberikan oleh command `leave:grant-annual` atau `cuti:generate-quota`
                // yang lebih sesuai untuk pengecekan masa kerja.
                // Namun, jika kebijakan adalah memberikan Cuti Tahunan langsung saat user dibuat
                // JIKA tanggal mulai bekerjanya sudah lebih dari 12 bulan yang lalu (misal, migrasi data user lama),
                // maka logika ini bisa dipertahankan.
                // Untuk konsistensi dengan command `cuti:generate-quota`, kita bisa menerapkan cek masa kerja di sini juga.
                if (strtolower($jenisCuti->nama_cuti) === 'cuti tahunan') {
                    // Hitung lama bekerja dari tanggal_mulai_bekerja user.
                    $lamaBekerjaBulanUser = Carbon::parse($user->tanggal_mulai_bekerja)->diffInMonths(now(config('app.timezone', 'Asia/Jakarta')));
                    if ($lamaBekerjaBulanUser < 12) {
                        Log::info("User creation event: Skipping annual leave quota for User ID {$user->id}. Work duration: {$lamaBekerjaBulanUser} months (< 12).");
                        continue; // Lewati Cuti Tahunan jika belum 12 bulan masa kerja.
                    }
                }

                // Tentukan durasi default untuk jenis cuti ini.
                $durasiCuti = $jenisCuti->durasi_default;

                // Periksa apakah kuota untuk jenis cuti ini sudah ada untuk pengguna ini
                // (seharusnya tidak ada karena ini event 'created', tapi untuk jaga-jaga).
                $existingQuota = CutiQuota::where('user_id', $user->id)
                    ->where('jenis_cuti_id', $jenisCuti->id)
                    ->first();

                if (!$existingQuota) {
                    // Buat record kuota cuti baru jika belum ada.
                    try {
                        CutiQuota::create([
                            'user_id' => $user->id,
                            'jenis_cuti_id' => $jenisCuti->id,
                            'durasi_cuti' => $durasiCuti,
                        ]);
                        Log::info("User creation event: Leave quota '{$jenisCuti->nama_cuti}' ({$durasiCuti} days) created for User ID {$user->id}.");
                    } catch (\Exception $e) {
                        Log::error("User creation event: Failed to create quota '{$jenisCuti->nama_cuti}' for User ID {$user->id}. Error: " . $e->getMessage());
                    }
                }
            }
        });
    }
}

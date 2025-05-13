<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon; // Import Carbon untuk manipulasi tanggal dan perhitungan durasi.

/**
 * Class PerjalananDinas
 *
 * Model ini merepresentasikan data perjalanan dinas yang dilakukan oleh karyawan.
 * Ini mencakup informasi tanggal berangkat, perkiraan pulang, tanggal pulang aktual,
 * tujuan, dan lama dinas. Model ini juga memiliki logika untuk menghitung
 * lama dinas secara otomatis dan memberikan kuota cuti khusus perjalanan dinas
 * setelah perjalanan selesai dan data diproses.
 *
 * @property int $id ID unik untuk setiap record perjalanan dinas.
 * @property int $user_id ID karyawan yang melakukan perjalanan dinas.
 * @property \Illuminate\Support\Carbon $tanggal_berangkat Tanggal keberangkatan perjalanan dinas.
 * @property \Illuminate\Support\Carbon $perkiraan_tanggal_pulang Perkiraan tanggal karyawan akan kembali.
 * @property \Illuminate\Support\Carbon|null $tanggal_pulang Tanggal aktual karyawan kembali (diisi setelah selesai).
 * @property string $jurusan Tujuan atau kota/daerah perjalanan dinas.
 * @property int|null $lama_dinas Durasi perjalanan dinas dalam hari (dihitung otomatis).
 * @property string $status Status perjalanan dinas (misal: 'berlangsung', 'selesai').
 * @property bool $is_processed Flag yang menandakan apakah kuota cuti khusus PD sudah diberikan (default false).
 * @property \Illuminate\Support\Carbon|null $created_at Timestamp pembuatan record.
 * @property \Illuminate\Support\Carbon|null $updated_at Timestamp pembaruan record terakhir.
 *
 * @property-read \App\Models\User $user Relasi ke model User (karyawan).
 *
 * @package App\Models
 */
class PerjalananDinas extends Model
{
    use HasFactory; // Mengaktifkan penggunaan factory jika diperlukan.

    /**
     * Nama tabel yang terhubung dengan model ini di database.
     * Opsional jika nama tabel mengikuti konvensi Laravel (plural snake_case dari nama model).
     *
     * @var string
     */
    protected $table = 'perjalanan_dinas';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Mendefinisikan kolom mana saja yang boleh diisi saat menggunakan metode `create()`
     * atau `update()` pada model dengan array data.
     * 'lama_dinas' dan 'is_processed' tidak termasuk karena dihandle oleh sistem.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'tanggal_berangkat',
        'perkiraan_tanggal_pulang',
        'tanggal_pulang',          // Diisi saat karyawan sudah kembali.
        'jurusan',                 // Tujuan perjalanan dinas.
        'status',                  // Status perjalanan (misal: 'berlangsung', 'selesai').
        // 'lama_dinas' dihitung otomatis.
        // 'is_processed' dihandle otomatis setelah pemberian kuota cuti PD.
    ];

    /**
     * Atribut yang harus di-cast ke tipe data native.
     * Casting memastikan bahwa ketika Anda mengakses atribut ini, nilainya akan dikonversi
     * ke tipe data yang ditentukan (misalnya, string tanggal menjadi objek Carbon).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tanggal_berangkat'         => 'date', // Cast ke objek Carbon (format tanggal).
        'perkiraan_tanggal_pulang'  => 'date', // Cast ke objek Carbon (format tanggal).
        'tanggal_pulang'            => 'date', // Cast ke objek Carbon (format tanggal).
        'is_processed'              => 'boolean', // Cast ke tipe boolean (true/false).
    ];

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // Definisikan bagaimana model ini terhubung dengan model lain.
    // --------------------------------------------------------------------

    /**
     * Mendapatkan pengguna (karyawan) yang melakukan perjalanan dinas ini.
     * Relasi one-to-many (inverse) / belongsTo: Satu PerjalananDinas dimiliki oleh satu User.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // --------------------------------------------------------------------
    // MODEL EVENTS / LOGIKA OTOMATIS
    // Model events memungkinkan Anda menjalankan kode secara otomatis saat
    // berbagai aksi terjadi pada model (misalnya, sebelum atau sesudah menyimpan).
    // --------------------------------------------------------------------

    /**
     * Method `booted` dijalankan saat model diinisialisasi.
     * Kita menggunakan ini untuk mendaftarkan event listener model.
     *
     * @return void
     */
    protected static function booted()
    {
        /**
         * Event 'saving' berjalan setiap kali model akan disimpan ke database,
         * baik saat membuat record baru (creating) maupun saat memperbarui (updating).
         * Digunakan di sini untuk menghitung dan mengisi kolom 'lama_dinas' secara otomatis.
         *
         * @param  \App\Models\PerjalananDinas  $dinas Instance model PerjalananDinas yang akan disimpan.
         * @return void
         */
        static::saving(function ($dinas) {
            // Tentukan tanggal akhir untuk perhitungan: gunakan 'tanggal_pulang' jika sudah ada,
            // jika belum, gunakan 'perkiraan_tanggal_pulang'.
            $endDate = $dinas->tanggal_pulang ?? $dinas->perkiraan_tanggal_pulang;

            // Hanya hitung jika tanggal akhir dan tanggal berangkat valid.
            if ($endDate && $dinas->tanggal_berangkat) {
                $start = Carbon::parse($dinas->tanggal_berangkat);
                $end = Carbon::parse($endDate);

                // Pastikan tanggal akhir tidak sebelum tanggal mulai untuk perhitungan yang logis.
                if ($end->greaterThanOrEqualTo($start)) {
                    // Hitung selisih hari (inklusif, jadi +1).
                    $dinas->lama_dinas = $start->diffInDays($end) + 1;
                } else {
                    // Jika tanggal tidak logis, set lama_dinas ke null atau 0.
                    $dinas->lama_dinas = null; // Atau bisa juga 0, atau biarkan sebagai warning.
                }
            } else {
                // Jika salah satu tanggal tidak ada, set lama_dinas ke null.
                $dinas->lama_dinas = null;
            }
        });

        /**
         * Event 'saved' berjalan setelah model berhasil disimpan ke database
         * (baik setelah create maupun update).
         * Digunakan di sini untuk menambahkan kuota 'Cuti Khusus Perjalanan Dinas'
         * secara otomatis setelah perjalanan dinas selesai dan datanya diproses.
         *
         * @param  \App\Models\PerjalananDinas  $dinas Instance model PerjalananDinas yang baru saja disimpan.
         * @return void
         */
        static::saved(function ($dinas) {
            // Periksa apakah perjalanan dinas ini sudah pernah diproses untuk pemberian kuota.
            // Ini mencegah pemberian kuota berulang kali jika record di-update lagi setelah 'is_processed' true.
            if ($dinas->is_processed) {
                return; // Jika sudah diproses, hentikan eksekusi lebih lanjut.
            }

            // Hanya proses jika 'lama_dinas' ada nilainya dan status perjalanan adalah 'selesai'.
            if ($dinas->lama_dinas && $dinas->status === 'selesai') {
                // Hitung kuota cuti perjalanan dinas yang akan diberikan.
                // Aturan: 1 hari cuti untuk setiap 10 hari dinas. Pembulatan ke bawah (floor).
                $cutiTambahan = floor($dinas->lama_dinas / 10);

                if ($cutiTambahan > 0) {
                    // Ambil jenis cuti 'Cuti Khusus Perjalanan Dinas' dari database.
                    // Pastikan nama ini konsisten dengan data di tabel 'jenis_cuti'.
                    $jenisCuti = JenisCuti::where('nama_cuti', 'Cuti Khusus Perjalanan Dinas')->first();

                    if ($jenisCuti) {
                        // Cari atau buat record kuota cuti untuk pengguna dan jenis cuti ini.
                        // `firstOrCreate` akan mencari record berdasarkan kriteria pertama,
                        // jika tidak ada, akan membuat record baru dengan data dari kriteria pertama DAN kedua.
                        $cutiQuota = CutiQuota::firstOrCreate(
                            [
                                'user_id' => $dinas->user_id,
                                'jenis_cuti_id' => $jenisCuti->id,
                            ],
                            [
                                'durasi_cuti' => 0, // Nilai awal jika record baru dibuat.
                            ]
                        );

                        // Tambahkan kuota cuti perjalanan dinas yang baru dihitung.
                        $cutiQuota->durasi_cuti += $cutiTambahan;
                        $cutiQuota->save();

                        // Tandai bahwa perjalanan dinas ini sudah diproses untuk pemberian kuota.
                        // Ini penting untuk mencegah penambahan kuota berulang jika record PD ini di-update lagi.
                        // Update langsung tanpa memicu event 'saving' lagi untuk kolom 'is_processed'.
                        $dinas->is_processed = true;
                        $dinas->saveQuietly(); // Menyimpan tanpa memicu event model (termasuk 'saving' dan 'saved' lagi).
                    }
                }
            }
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Holiday
 *
 * Model ini merepresentasikan data hari libur nasional atau hari libur perusahaan.
 * Data ini digunakan dalam perhitungan hari kerja efektif untuk pengajuan cuti,
 * penjadwalan, dan modul lain yang memerlukan informasi hari libur.
 * Primary key untuk model ini adalah kolom 'tanggal'.
 *
 * @property string $tanggal Tanggal hari libur (format YYYY-MM-DD), bertindak sebagai primary key.
 * @property string $nama_libur Nama atau deskripsi dari hari libur tersebut.
 *
 * @package App\Models
 */
class Holiday extends Model
{
    use HasFactory; // Mengaktifkan penggunaan factory jika diperlukan.

    /**
     * Nama tabel yang terhubung dengan model ini di database.
     * Eksplisit didefinisikan untuk kejelasan, meskipun 'holidays' adalah konvensi Laravel.
     *
     * @var string
     */
    protected $table = 'holidays';

    /**
     * Nama primary key untuk model ini.
     * Karena primary key bukan 'id' (integer auto-increment), kita perlu mendefinisikannya.
     *
     * @var string
     */
    protected $primaryKey = 'tanggal';

    /**
     * Menunjukkan bahwa primary key model ini BUKAN auto-incrementing.
     * Penting karena 'tanggal' (string/date) bukanlah integer yang bertambah otomatis.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Tipe data dari primary key.
     * Karena primary key 'tanggal' adalah tipe date (yang diperlakukan sebagai string oleh Eloquent
     * saat digunakan sebagai primary key), kita set $keyType menjadi 'string'.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Menunjukkan apakah model harus menggunakan timestamps 'created_at' dan 'updated_at'.
     * Untuk data hari libur yang statis, timestamps ini mungkin tidak diperlukan.
     *
     * @var bool
     */
    public $timestamps = false; // Tidak menggunakan kolom created_at dan updated_at.

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Mendefinisikan kolom mana saja yang boleh diisi saat menggunakan metode `create()`
     * atau `update()` pada model dengan array data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tanggal',    // Tanggal hari libur.
        'nama_libur', // Nama atau deskripsi hari libur.
    ];

    /**
     * Atribut yang harus di-cast ke tipe data native.
     * Casting 'tanggal' ke objek Carbon memastikan bahwa saat diakses,
     * nilainya akan berupa objek Carbon, memudahkan manipulasi tanggal.
     * Ini opsional jika $primaryKey sudah date dan interaksi via string Y-m-d cukup,
     * namun sangat direkomendasikan untuk konsistensi.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tanggal' => 'date', // Cast kolom 'tanggal' ke objek Carbon (format tanggal saja).
    ];

    // Tidak ada relasi Eloquent yang didefinisikan di sini,
    // karena model Holiday biasanya berdiri sendiri atau diakses oleh model lain,
    // bukan sebaliknya (Holiday memiliki relasi ke model lain).
}

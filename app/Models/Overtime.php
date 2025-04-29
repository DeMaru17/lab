<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon; // Import Carbon untuk manipulasi tanggal/waktu

class Overtime extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'overtimes'; // Eksplisit mendefinisikan nama tabel

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'tanggal_lembur',
        'jam_mulai',
        'jam_selesai',
        // 'durasi_menit', // Dihitung otomatis, tidak perlu fillable
        'uraian_pekerjaan',
        'status',
        'approved_by_asisten_id', // Diisi saat approval
        'approved_at_asisten',    // Diisi saat approval
        'approved_by_manager_id', // Diisi saat approval
        'approved_at_manager',    // Diisi saat approval
        'rejected_by_id',         // Diisi saat rejection
        'rejected_at',            // Diisi saat rejection
        'notes',                  // Bisa diisi saat rejection atau update
        'last_reminder_sent_at',  // Diisi oleh sistem reminder
    ];

    /**
     * Atribut yang harus di-cast ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tanggal_lembur'        => 'date',
        // Casting TIME ke datetime dengan format H:i:s agar bisa dimanipulasi Carbon
        // Atau bisa juga coba 'datetime' saja jika Laravel handle TIME dengan baik
        'jam_mulai'             => 'datetime:H:i:s',
        'jam_selesai'           => 'datetime:H:i:s',
        'approved_at_asisten'   => 'datetime',
        'approved_at_manager'   => 'datetime',
        'rejected_at'           => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        // created_at dan updated_at otomatis di-cast
    ];

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // --------------------------------------------------------------------

    /**
     * Mendapatkan user (karyawan) yang memiliki data lembur ini.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mendapatkan user (Asisten Manager) yang menyetujui level 1.
     */
    public function approverAsisten()
    {
        return $this->belongsTo(User::class, 'approved_by_asisten_id');
    }

    /**
     * Mendapatkan user (Manager) yang menyetujui level 2.
     */
    public function approverManager()
    {
        return $this->belongsTo(User::class, 'approved_by_manager_id');
    }

    /**
     * Mendapatkan user yang menolak pengajuan.
     */
    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    // --------------------------------------------------------------------
    // MODEL EVENTS / LOGIKA OTOMATIS
    // --------------------------------------------------------------------

    /**
     * Boot the model.
     * Method ini otomatis dijalankan saat model diinisialisasi.
     */
    protected static function booted(): void
    {
        /**
         * Event 'saving' berjalan setiap kali model akan disimpan (create atau update).
         * Kita gunakan ini untuk menghitung durasi_menit secara otomatis.
         */
        static::saving(function ($overtime) {
            // Pastikan jam_mulai dan jam_selesai ada dan valid
            if ($overtime->jam_mulai && $overtime->jam_selesai) {
                // Konversi ke objek Carbon (seharusnya sudah otomatis karena $casts)
                $startTime = Carbon::parse($overtime->jam_mulai);
                $endTime = Carbon::parse($overtime->jam_selesai);

                // Penanganan jika lembur melewati tengah malam (jam selesai < jam mulai)
                if ($endTime->lessThanOrEqualTo($startTime)) {
                    // Tambahkan 1 hari ke waktu selesai
                    $endTime->addDay();
                }

                // Hitung selisih dalam menit
                $overtime->durasi_menit = $startTime->diffInMinutes($endTime);
            } else {
                // Jika salah satu waktu tidak ada, set durasi ke null
                $overtime->durasi_menit = null;
            }
        });

        // Anda bisa menambahkan event lain di sini jika perlu
        // Misalnya, event 'updating' untuk validasi sebelum update
        // Atau event 'creating' khusus saat data baru dibuat
    }

    // --------------------------------------------------------------------
    // ACCESSORS & MUTATORS
    // --------------------------------------------------------------------

    /**
     * Cara panggil: $overtime->durasi_formatted
     */
    public function getDurasiFormattedAttribute(): ?string
    {
        if (is_null($this->durasi_menit)) {
            return null;
        }
        $hours = floor($this->durasi_menit / 60);
        $minutes = $this->durasi_menit % 60;
        return sprintf('%d jam %02d menit', $hours, $minutes);
    }

}

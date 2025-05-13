<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// Tambahkan use HasFactory jika berencana menggunakan factory
// use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class Cuti
 *
 * Model ini merepresentasikan pengajuan cuti oleh karyawan.
 * Mencakup informasi mengenai jenis cuti, durasi, status persetujuan,
 * dan data terkait lainnya seperti lampiran surat sakit.
 *
 * @property int $id ID unik untuk setiap pengajuan cuti.
 * @property int $user_id ID karyawan yang mengajukan cuti.
 * @property int $jenis_cuti_id ID jenis cuti yang diajukan.
 * @property \Illuminate\Support\Carbon $mulai_cuti Tanggal mulai cuti.
 * @property \Illuminate\Support\Carbon $selesai_cuti Tanggal selesai cuti.
 * @property int $lama_cuti Durasi cuti dalam hari kerja efektif.
 * @property string $keperluan Alasan atau keperluan mengambil cuti.
 * @property string $alamat_selama_cuti Alamat karyawan selama periode cuti.
 * @property string|null $surat_sakit Path ke file surat sakit (jika jenis cuti adalah sakit).
 * @property string $status Status pengajuan cuti (misal: 'pending', 'approved', 'rejected', 'cancelled').
 * @property string|null $notes Catatan tambahan terkait pengajuan cuti (misal: alasan penolakan).
 * @property int|null $approved_by_asisten_id ID Asisten Manager yang menyetujui (L1).
 * @property \Illuminate\Support\Carbon|null $approved_at_asisten Timestamp persetujuan Asisten Manager.
 * @property int|null $approved_by_manager_id ID Manager yang menyetujui (L2).
 * @property \Illuminate\Support\Carbon|null $approved_at_manager Timestamp persetujuan Manager.
 * @property int|null $rejected_by_id ID approver yang menolak.
 * @property \Illuminate\Support\Carbon|null $rejected_at Timestamp penolakan.
 * @property \Illuminate\Support\Carbon|null $last_reminder_sent_at Timestamp reminder overdue dikirim.
 * @property \Illuminate\Support\Carbon|null $created_at Timestamp pembuatan record.
 * @property \Illuminate\Support\Carbon|null $updated_at Timestamp pembaruan record terakhir.
 *
 * @property-read \App\Models\JenisCuti $jenisCuti Relasi ke model JenisCuti.
 * @property-read \App\Models\User $user Relasi ke model User (pengaju).
 * @property-read \App\Models\User|null $approverAsisten Relasi ke model User (Asisten Manager).
 * @property-read \App\Models\User|null $approverManager Relasi ke model User (Manager).
 * @property-read \App\Models\User|null $rejecter Relasi ke model User (yang menolak).
 *
 * @package App\Models
 */
class Cuti extends Model
{
    // use HasFactory; // Aktifkan jika Anda menggunakan factories

    /**
     * Nama tabel yang terhubung dengan model ini di database.
     *
     * @var string
     */
    protected $table = 'cuti';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Mendefinisikan kolom mana saja yang boleh diisi saat menggunakan metode `create()` atau `update()`
     * dengan array data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'jenis_cuti_id',
        'mulai_cuti',
        'selesai_cuti',
        'lama_cuti',          // Durasi cuti dalam hari kerja efektif, dihitung di controller.
        'keperluan',
        'alamat_selama_cuti',
        'surat_sakit',        // Path ke file surat sakit, jika relevan.
        'status',             // Status pengajuan (pending, approved, rejected, dll.).
        'notes',              // Catatan tambahan, misal alasan penolakan.
        // Kolom-kolom approval berikut diisi oleh sistem saat proses persetujuan/penolakan:
        'approved_by_asisten_id',
        'approved_at_asisten',
        'approved_by_manager_id',
        'approved_at_manager',
        'rejected_by_id',
        'rejected_at',
        'last_reminder_sent_at', // Untuk melacak kapan reminder overdue terakhir dikirim.
    ];

    /**
     * Atribut yang harus di-cast ke tipe data native.
     * Casting memastikan bahwa ketika Anda mengakses atribut ini, nilainya akan dikonversi
     * ke tipe data yang ditentukan (misalnya, string tanggal menjadi objek Carbon).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'mulai_cuti'        => 'date',       // Cast ke objek Carbon dengan format tanggal.
        'selesai_cuti'      => 'date',       // Cast ke objek Carbon dengan format tanggal.
        'approved_at_asisten' => 'datetime', // Cast ke objek Carbon dengan format tanggal dan waktu.
        'approved_at_manager' => 'datetime', // Cast ke objek Carbon dengan format tanggal dan waktu.
        'rejected_at'       => 'datetime', // Cast ke objek Carbon dengan format tanggal dan waktu.
        'last_reminder_sent_at' => 'datetime', // Cast ke objek Carbon untuk timestamp reminder.

        // Kolom 'created_at' dan 'updated_at' biasanya otomatis di-cast jika $timestamps = true (default).
        // Tidak ada salahnya ditambahkan eksplisit jika ragu.
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // Definisikan bagaimana model ini terhubung dengan model lain.
    // --------------------------------------------------------------------

    /**
     * Mendapatkan jenis cuti yang terkait dengan pengajuan ini.
     * Relasi one-to-many (inverse) / belongsTo: Satu pengajuan Cuti dimiliki oleh satu JenisCuti.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function jenisCuti()
    {
        return $this->belongsTo(JenisCuti::class, 'jenis_cuti_id');
    }

    /**
     * Mendapatkan pengguna (karyawan) yang mengajukan cuti ini.
     * Relasi one-to-many (inverse) / belongsTo: Satu pengajuan Cuti dimiliki oleh satu User.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mendapatkan pengguna (Asisten Manager) yang menyetujui pengajuan ini pada level 1.
     * Relasi ini bisa null jika belum disetujui Asisten Manager atau jika ditolak sebelum L1.
     * Relasi one-to-many (inverse) / belongsTo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function approverAsisten()
    {
        return $this->belongsTo(User::class, 'approved_by_asisten_id');
    }

    /**
     * Mendapatkan pengguna (Manager) yang menyetujui pengajuan ini pada level 2 (final).
     * Relasi ini bisa null jika belum disetujui Manager atau jika ditolak.
     * Relasi one-to-many (inverse) / belongsTo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function approverManager()
    {
        return $this->belongsTo(User::class, 'approved_by_manager_id');
    }

    /**
     * Mendapatkan pengguna (approver) yang menolak pengajuan cuti ini.
     * Relasi ini bisa null jika pengajuan belum ditolak.
     * Relasi one-to-many (inverse) / belongsTo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }
}

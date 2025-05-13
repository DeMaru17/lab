<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon; // Import Carbon untuk manipulasi tanggal dan waktu, terutama dalam event 'saving'.

/**
 * Class Overtime
 *
 * Model ini merepresentasikan pengajuan lembur oleh karyawan.
 * Ini mencakup detail waktu lembur, uraian pekerjaan, status persetujuan,
 * dan informasi approver terkait. Durasi lembur dihitung secara otomatis.
 *
 * @property int $id ID unik untuk setiap pengajuan lembur.
 * @property int $user_id ID karyawan yang mengajukan lembur.
 * @property \Illuminate\Support\Carbon $tanggal_lembur Tanggal pelaksanaan lembur.
 * @property \Illuminate\Support\Carbon $jam_mulai Waktu mulai lembur (disimpan sebagai datetime dengan format H:i:s).
 * @property \Illuminate\Support\Carbon $jam_selesai Waktu selesai lembur (disimpan sebagai datetime dengan format H:i:s).
 * @property int|null $durasi_menit Durasi lembur dalam menit (dihitung otomatis saat saving).
 * @property string $uraian_pekerjaan Deskripsi pekerjaan yang dilakukan selama lembur.
 * @property string $status Status pengajuan lembur (misal: 'pending', 'approved', 'rejected', 'cancelled').
 * @property int|null $approved_by_asisten_id ID Asisten Manager yang menyetujui (L1).
 * @property \Illuminate\Support\Carbon|null $approved_at_asisten Timestamp persetujuan Asisten Manager.
 * @property int|null $approved_by_manager_id ID Manager yang menyetujui (L2).
 * @property \Illuminate\Support\Carbon|null $approved_at_manager Timestamp persetujuan Manager.
 * @property int|null $rejected_by_id ID approver yang menolak.
 * @property \Illuminate\Support\Carbon|null $rejected_at Timestamp penolakan.
 * @property string|null $notes Catatan tambahan (misal: alasan penolakan, atau info dari Admin).
 * @property \Illuminate\Support\Carbon|null $last_reminder_sent_at Timestamp kapan terakhir email pengingat overdue dikirim.
 * @property \Illuminate\Support\Carbon|null $created_at Timestamp pembuatan record.
 * @property \Illuminate\Support\Carbon|null $updated_at Timestamp pembaruan record terakhir.
 *
 * @property-read \App\Models\User $user Relasi ke model User (pengaju).
 * @property-read \App\Models\User|null $approverAsisten Relasi ke model User (Asisten Manager).
 * @property-read \App\Models\User|null $approverManager Relasi ke model User (Manager).
 * @property-read \App\Models\User|null $rejecter Relasi ke model User (yang menolak).
 * @property-read string|null $durasi_formatted Accessor untuk format durasi lembur (X jam YY menit).
 *
 * @package App\Models
 */
class Overtime extends Model
{
    use HasFactory; // Mengaktifkan penggunaan factory jika diperlukan.

    /**
     * Nama tabel yang terhubung dengan model ini di database.
     * Didefinisikan secara eksplisit untuk kejelasan.
     *
     * @var string
     */
    protected $table = 'overtimes';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Mendefinisikan kolom mana saja yang boleh diisi saat menggunakan metode `create()`
     * atau `update()` pada model dengan array data.
     * 'durasi_menit' tidak termasuk karena dihitung secara otomatis oleh model event.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'tanggal_lembur',
        'jam_mulai',
        'jam_selesai',
        // 'durasi_menit', // Dihitung otomatis oleh model event 'saving', jadi tidak perlu fillable.
        'uraian_pekerjaan',
        'status',
        'approved_by_asisten_id', // Diisi saat proses approval L1.
        'approved_at_asisten',    // Diisi saat proses approval L1.
        'approved_by_manager_id', // Diisi saat proses approval L2.
        'approved_at_manager',    // Diisi saat proses approval L2.
        'rejected_by_id',         // Diisi saat pengajuan ditolak.
        'rejected_at',            // Diisi saat pengajuan ditolak.
        'notes',                  // Bisa diisi saat penolakan, atau catatan tambahan oleh Admin/Manajemen.
        'last_reminder_sent_at',  // Diisi oleh sistem saat mengirim email reminder overdue.
    ];

    /**
     * Atribut yang harus di-cast ke tipe data native.
     * Casting memastikan bahwa ketika Anda mengakses atribut ini, nilainya akan dikonversi
     * ke tipe data yang ditentukan.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tanggal_lembur'        => 'date',           // Cast ke objek Carbon (format tanggal).
        // Casting kolom TIME (jam_mulai, jam_selesai) ke 'datetime:H:i:s'
        // memungkinkan manipulasi menggunakan objek Carbon seolah-olah itu adalah datetime,
        // tetapi hanya bagian waktu yang relevan. Ini membantu dalam perhitungan durasi.
        // Alternatif: biarkan sebagai string dan parse manual di accessor/mutator jika perlu.
        'jam_mulai'             => 'datetime:H:i:s', // Cast ke Carbon, hanya format H:i:s yang signifikan.
        'jam_selesai'           => 'datetime:H:i:s', // Cast ke Carbon, hanya format H:i:s yang signifikan.
        'approved_at_asisten'   => 'datetime',       // Cast ke Carbon (tanggal dan waktu).
        'approved_at_manager'   => 'datetime',       // Cast ke Carbon (tanggal dan waktu).
        'rejected_at'           => 'datetime',       // Cast ke Carbon (tanggal dan waktu).
        'last_reminder_sent_at' => 'datetime',       // Cast ke Carbon (tanggal dan waktu).
        // 'durasi_menit' adalah integer, tidak perlu cast khusus.
        // Kolom 'created_at' dan 'updated_at' secara otomatis di-cast jika $timestamps = true (default).
    ];

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // Definisikan bagaimana model ini terhubung dengan model lain.
    // --------------------------------------------------------------------

    /**
     * Mendapatkan user (karyawan) yang memiliki (mengajukan) data lembur ini.
     * Relasi one-to-many (inverse) / belongsTo: Satu Overtime dimiliki oleh satu User.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mendapatkan user (Asisten Manager) yang menyetujui pengajuan lembur ini pada level 1.
     * Relasi ini bisa null jika belum disetujui Asisten Manager atau jika ditolak.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function approverAsisten()
    {
        return $this->belongsTo(User::class, 'approved_by_asisten_id');
    }

    /**
     * Mendapatkan user (Manager) yang menyetujui pengajuan lembur ini pada level 2 (final).
     * Relasi ini bisa null jika belum disetujui Manager atau jika ditolak.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function approverManager()
    {
        return $this->belongsTo(User::class, 'approved_by_manager_id');
    }

    /**
     * Mendapatkan user (approver) yang menolak pengajuan lembur ini.
     * Relasi ini bisa null jika pengajuan belum pernah ditolak.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
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
    protected static function booted(): void
    {
        /**
         * Event 'saving' berjalan setiap kali model akan disimpan ke database,
         * baik saat membuat record baru (creating) maupun saat memperbarui record yang ada (updating).
         * Kita gunakan event ini untuk menghitung dan mengisi kolom 'durasi_menit' secara otomatis
         * berdasarkan 'jam_mulai' dan 'jam_selesai'.
         *
         * @param  \App\Models\Overtime  $overtime Instance model Overtime yang akan disimpan.
         * @return void
         */
        static::saving(function ($overtime) {
            // Pastikan 'jam_mulai' dan 'jam_selesai' ada dan valid sebelum perhitungan.
            if ($overtime->jam_mulai && $overtime->jam_selesai) {
                // Karena 'jam_mulai' dan 'jam_selesai' di-cast ke 'datetime:H:i:s',
                // mereka sudah menjadi objek Carbon saat diakses di sini.
                // Namun, tanggalnya akan default ke tanggal hari ini atau tanggal dari parsing string.
                // Untuk perhitungan durasi yang akurat, kita parse ulang dengan tanggal lembur jika perlu,
                // atau cukup fokus pada perbedaan waktunya saja jika tanggal sudah konsisten.
                // Cara yang lebih aman adalah memastikan tanggalnya sama (misal, tanggal_lembur)
                // sebelum menghitung diffInMinutes, terutama jika cast hanya H:i:s.
                // Dengan cast 'datetime:H:i:s', Carbon akan menggunakan tanggal saat ini jika hanya waktu yang diberikan.
                // Untuk konsistensi, kita gabungkan tanggal lembur dengan jam mulai/selesai.
                // Namun, karena $casts sudah menangani ini menjadi objek Carbon dengan tanggal saat ini + waktu,
                // kita bisa langsung menggunakannya.
                $startTime = Carbon::parse($overtime->jam_mulai); // Sudah menjadi Carbon karena $casts
                $endTime = Carbon::parse($overtime->jam_selesai);   // Sudah menjadi Carbon karena $casts

                // Penanganan jika lembur melewati tengah malam (jam selesai < jam mulai).
                // Jika jam selesai secara nominal lebih kecil atau sama dengan jam mulai,
                // kita asumsikan lembur berlanjut ke hari berikutnya.
                if ($endTime->lessThanOrEqualTo($startTime)) {
                    // Tambahkan 1 hari ke waktu selesai untuk perhitungan durasi yang benar.
                    $endTime->addDay();
                }

                // Hitung selisih waktu dalam menit.
                $overtime->durasi_menit = $startTime->diffInMinutes($endTime);
            } else {
                // Jika salah satu waktu (mulai/selesai) tidak ada, set durasi ke null.
                $overtime->durasi_menit = null;
            }
        });

        // Anda bisa menambahkan event model lain di sini jika diperlukan,
        // misalnya, 'updating' untuk validasi khusus sebelum update,
        // atau 'creating' untuk logika yang hanya berjalan saat record baru dibuat.
    }

    // --------------------------------------------------------------------
    // ACCESSORS & MUTATORS (Opsional)
    // Accessor digunakan untuk memformat atribut saat diambil dari model.
    // Mutator digunakan untuk memformat atribut sebelum disimpan ke database.
    // --------------------------------------------------------------------

    /**
     * Mendapatkan durasi lembur dalam format "X jam YY menit".
     * Ini adalah accessor, yang berarti Anda bisa mengaksesnya seperti properti biasa: `$overtime->durasi_formatted`.
     * Method harus dinamai dengan `getNamaAtributAttribute`.
     *
     * @return string|null String yang diformat atau null jika durasi_menit tidak ada.
     */
    public function getDurasiFormattedAttribute(): ?string
    {
        // Jika durasi_menit belum dihitung atau null, kembalikan null.
        if (is_null($this->durasi_menit)) {
            return null;
        }
        // Hitung jam dan menit dari total durasi_menit.
        $hours = floor($this->durasi_menit / 60);
        $minutes = $this->durasi_menit % 60;
        // Kembalikan string yang diformat. %02d memastikan menit selalu dua digit (misal: 05).
        return sprintf('%d jam %02d menit', $hours, $minutes);
    }

}

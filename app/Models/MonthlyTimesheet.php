<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Import BelongsTo untuk type hinting pada relasi.

/**
 * Class MonthlyTimesheet
 *
 * Model ini merepresentasikan rekapitulasi data kehadiran dan aktivitas bulanan
 * untuk setiap karyawan. Ini mencakup total hari kerja, hari hadir, keterlambatan,
 * cuti, dinas, lembur, dan status persetujuan timesheet tersebut.
 *
 * @property int $id ID unik untuk setiap rekap timesheet bulanan.
 * @property int $user_id ID karyawan yang memiliki rekap timesheet ini.
 * @property int|null $vendor_id ID vendor terkait karyawan (jika karyawan adalah outsourcing).
 * @property \Illuminate\Support\Carbon $period_start_date Tanggal mulai periode timesheet.
 * @property \Illuminate\Support\Carbon $period_end_date Tanggal selesai periode timesheet.
 * @property int $total_work_days Jumlah total hari kerja dalam periode tersebut.
 * @property int $total_present_days Jumlah hari karyawan hadir.
 * @property int $total_late_days Jumlah hari karyawan terlambat.
 * @property int $total_early_leave_days Jumlah hari karyawan pulang cepat.
 * @property int $total_alpha_days Jumlah hari karyawan absen tanpa keterangan (Alpha).
 * @property int $total_leave_days Jumlah hari karyawan mengambil cuti (termasuk sakit).
 * @property int $total_duty_days Jumlah hari karyawan melakukan perjalanan dinas.
 * @property int $total_holiday_duty_days Jumlah hari karyawan lembur pada hari libur.
 * @property int $total_overtime_minutes Total durasi lembur dalam menit.
 * @property int $total_overtime_occurrences Jumlah kejadian lembur.
 * @property string $status Status persetujuan timesheet (misal: 'generated', 'pending_asisten', 'pending_manager', 'approved', 'rejected').
 * @property int|null $approved_by_asisten_id ID Asisten Manager yang menyetujui (L1).
 * @property \Illuminate\Support\Carbon|null $approved_at_asisten Timestamp persetujuan Asisten Manager.
 * @property int|null $approved_by_manager_id ID Manager yang menyetujui (L2).
 * @property \Illuminate\Support\Carbon|null $approved_at_manager Timestamp persetujuan Manager.
 * @property int|null $rejected_by_id ID approver yang menolak.
 * @property \Illuminate\Support\Carbon|null $rejected_at Timestamp penolakan.
 * @property string|null $notes Catatan terkait proses persetujuan atau penolakan.
 * @property \Illuminate\Support\Carbon|null $generated_at Timestamp kapan timesheet ini di-generate atau di-regenerate.
 * @property \Illuminate\Support\Carbon|null $created_at Timestamp pembuatan record.
 * @property \Illuminate\Support\Carbon|null $updated_at Timestamp pembaruan record terakhir.
 *
 * @property-read \App\Models\User $user Relasi ke model User (karyawan).
 * @property-read \App\Models\Vendor|null $vendor Relasi ke model Vendor.
 * @property-read \App\Models\User|null $approverAsisten Relasi ke model User (Asisten Manager).
 * @property-read \App\Models\User|null $approverManager Relasi ke model User (Manager).
 * @property-read \App\Models\User|null $rejecter Relasi ke model User (yang menolak).
 * @property-read string|null $total_overtime_formatted Accessor untuk format total lembur (Jam:Menit).
 *
 * @package App\Models
 */
class MonthlyTimesheet extends Model
{
    use HasFactory; // Mengaktifkan penggunaan factory jika berencana membuat data dummy atau testing.

    /**
     * Nama tabel yang terhubung dengan model ini di database.
     *
     * @var string
     */
    protected $table = 'monthly_timesheets';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     * Ini mendefinisikan kolom mana saja yang boleh diisi saat menggunakan metode `create()`
     * atau `update()` pada model dengan array data. Kolom-kolom ini biasanya diisi
     * oleh sistem (command generator) atau saat proses persetujuan.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',                  // ID karyawan yang memiliki rekap ini.
        'vendor_id',                // ID vendor terkait karyawan (bisa null jika karyawan internal).
        'period_start_date',        // Tanggal mulai periode rekap.
        'period_end_date',          // Tanggal selesai periode rekap.
        'total_work_days',          // Jumlah total hari kerja dalam periode.
        'total_present_days',       // Jumlah hari karyawan hadir.
        'total_late_days',          // Jumlah hari karyawan terlambat.
        'total_early_leave_days',   // Jumlah hari karyawan pulang lebih awal.
        'total_alpha_days',         // Jumlah hari karyawan alpha (tidak masuk tanpa keterangan).
        'total_leave_days',         // Jumlah hari karyawan mengambil cuti (termasuk sakit).
        'total_duty_days',          // Jumlah hari karyawan melakukan perjalanan dinas.
        'total_holiday_duty_days',  // Jumlah hari karyawan melakukan lembur pada hari libur.
        'total_overtime_minutes',   // Total durasi lembur dalam satuan menit.
        'total_overtime_occurrences',// Jumlah kejadian lembur dalam periode.
        'status',                   // Status persetujuan timesheet (misal: 'generated', 'pending_asisten', 'approved').
        'approved_by_asisten_id',   // ID Asisten Manager yang menyetujui (level 1).
        'approved_at_asisten',      // Timestamp persetujuan oleh Asisten Manager.
        'approved_by_manager_id',   // ID Manager yang menyetujui (level 2/final).
        'approved_at_manager',      // Timestamp persetujuan oleh Manager.
        'rejected_by_id',           // ID approver yang menolak.
        'rejected_at',              // Timestamp saat timesheet ditolak.
        'notes',                    // Catatan tambahan terkait proses persetujuan atau penolakan.
        'generated_at',             // Timestamp kapan timesheet ini di-generate atau di-regenerate oleh sistem.
    ];

    /**
     * Atribut yang harus di-cast ke tipe data native.
     * Casting memastikan bahwa ketika Anda mengakses atribut ini, nilainya akan dikonversi
     * ke tipe data yang ditentukan (misalnya, string tanggal menjadi objek Carbon).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'period_start_date' => 'date',       // Cast ke objek Carbon (format tanggal).
        'period_end_date'   => 'date',       // Cast ke objek Carbon (format tanggal).
        'approved_at_asisten' => 'datetime', // Cast ke objek Carbon (format tanggal dan waktu).
        'approved_at_manager' => 'datetime', // Cast ke objek Carbon (format tanggal dan waktu).
        'rejected_at'       => 'datetime', // Cast ke objek Carbon (format tanggal dan waktu).
        'generated_at'      => 'datetime', // Cast ke objek Carbon (format tanggal dan waktu).
        // Kolom-kolom total (seperti total_work_days, dll.) biasanya adalah integer dan tidak memerlukan cast khusus,
        // kecuali jika Anda ingin memastikan tipenya secara eksplisit (misal: 'integer').
        // Kolom 'created_at' dan 'updated_at' secara otomatis di-cast ke datetime jika $timestamps = true (default).
    ];

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // Definisikan bagaimana model ini terhubung dengan model lain.
    // --------------------------------------------------------------------

    /**
     * Mendapatkan data user (karyawan) yang memiliki rekap timesheet bulanan ini.
     * Relasi one-to-many (inverse) / belongsTo: Satu MonthlyTimesheet dimiliki oleh satu User.
     * Foreign key: 'user_id' di tabel 'monthly_timesheets' merujuk ke 'id' di tabel 'users'.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mendapatkan data vendor terkait dengan rekap timesheet ini (jika ada).
     * Relasi ini bisa null jika timesheet milik karyawan internal (bukan outsourcing).
     * Relasi one-to-many (inverse) / belongsTo: Satu MonthlyTimesheet (mungkin) dimiliki oleh satu Vendor.
     * Foreign key: 'vendor_id' di tabel 'monthly_timesheets' merujuk ke 'id' di tabel 'vendors'.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * Mendapatkan data user (Asisten Manager) yang menyetujui timesheet ini pada level 1.
     * Relasi ini bisa null jika belum disetujui atau jika ditolak sebelum level ini.
     * Relasi one-to-many (inverse) / belongsTo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function approverAsisten(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_asisten_id');
    }

    /**
     * Mendapatkan data user (Manager) yang menyetujui timesheet ini pada level 2 (final).
     * Relasi ini bisa null jika belum mencapai persetujuan Manager atau jika ditolak.
     * Relasi one-to-many (inverse) / belongsTo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function approverManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_manager_id');
    }

    /**
     * Mendapatkan data user (approver) yang menolak rekap timesheet ini.
     * Relasi ini bisa null jika timesheet belum pernah ditolak.
     * Relasi one-to-many (inverse) / belongsTo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    // --------------------------------------------------------------------
    // ACCESSORS (Opsional, untuk format data saat ditampilkan)
    // Accessor memungkinkan Anda memformat nilai atribut Eloquent saat Anda mengambilnya.
    // --------------------------------------------------------------------

    /**
     * Mendapatkan total durasi lembur dalam format "X jam YY menit".
     * Ini adalah accessor, yang berarti Anda bisa mengaksesnya seperti properti biasa: `$timesheet->total_overtime_formatted`.
     * Method harus dinamai dengan `getTotalNamaAtributAttribute`.
     *
     * @return string|null String yang diformat atau null/tanda strip jika tidak ada lembur.
     */
    public function getTotalOvertimeFormattedAttribute(): ?string
    {
        // Jika total_overtime_minutes null atau 0, kembalikan tanda strip atau null.
        if (is_null($this->total_overtime_minutes) || $this->total_overtime_minutes == 0) {
            return '-'; // Atau return null; sesuai preferensi tampilan.
        }
        // Hitung jam dan menit dari total menit.
        $hours = floor($this->total_overtime_minutes / 60);
        $minutes = $this->total_overtime_minutes % 60;
        // Kembalikan string yang diformat. %02d memastikan menit selalu dua digit (misal: 05).
        return sprintf('%d jam %02d mnt', $hours, $minutes);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Import BelongsTo

class MonthlyTimesheet extends Model
{
    use HasFactory; // Aktifkan jika berencana menggunakan factory

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'monthly_timesheets';

    /**
     * The attributes that are mass assignable.
     * Kolom yang boleh diisi saat create() atau update().
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'vendor_id', // Bisa null
        'period_start_date',
        'period_end_date',
        'total_work_days',
        'total_present_days',
        'total_late_days',
        'total_early_leave_days',
        'total_alpha_days',
        'total_leave_days',
        'total_duty_days',
        'total_holiday_duty_days', // Hari Lembur di Libur
        'total_overtime_minutes',
        'total_overtime_occurrences',
        'status', // Diisi oleh sistem/approval
        'approved_by_asisten_id', // Diisi saat approval
        'approved_at_asisten',    // Diisi saat approval
        'approved_by_manager_id', // Diisi saat approval
        'approved_at_manager',    // Diisi saat approval
        'rejected_by_id',         // Diisi saat reject
        'rejected_at',            // Diisi saat reject
        'notes',                  // Catatan approval/reject
        'generated_at',           // Diisi saat generate
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date'   => 'date',
        'approved_at_asisten' => 'datetime',
        'approved_at_manager' => 'datetime',
        'rejected_at'       => 'datetime',
        'generated_at'      => 'datetime',
        // Kolom total biarkan integer, tidak perlu cast khusus
        // created_at dan updated_at otomatis
    ];

    // --------------------------------------------------------------------
    // RELASI ELOQUENT
    // --------------------------------------------------------------------

    /**
     * Mendapatkan data user (karyawan) yang memiliki rekap ini.
     * Relasi: monthly_timesheets.user_id -> users.id
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mendapatkan data vendor terkait (jika ada).
     * Relasi: monthly_timesheets.vendor_id -> vendors.id
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * Mendapatkan data user (Asisten Manager) yang menyetujui level 1.
     * Relasi: monthly_timesheets.approved_by_asisten_id -> users.id
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function approverAsisten(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_asisten_id');
    }

    /**
     * Mendapatkan data user (Manager) yang menyetujui level 2.
     * Relasi: monthly_timesheets.approved_by_manager_id -> users.id
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function approverManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_manager_id');
    }

    /**
     * Mendapatkan data user yang menolak rekap ini.
     * Relasi: monthly_timesheets.rejected_by_id -> users.id
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    // --- ACCESSORS (Opsional, untuk format data) ---

    /**
     * Mendapatkan total durasi lembur dalam format Jam:Menit.
     * Cara panggil: $timesheet->total_overtime_formatted
     *
     * @return string|null
     */
    public function getTotalOvertimeFormattedAttribute(): ?string
    {
        if (is_null($this->total_overtime_minutes) || $this->total_overtime_minutes == 0) {
            return '-';
        }
        $hours = floor($this->total_overtime_minutes / 60);
        $minutes = $this->total_overtime_minutes % 60;
        return sprintf('%d jam %02d mnt', $hours, $minutes);
    }
}

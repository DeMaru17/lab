<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Attendance;
use App\Models\Cuti;
use App\Models\PerjalananDinas;
use App\Models\Overtime;
use App\Models\Holiday;
use App\Models\Shift; // Import Shift
use App\Models\User; // Import User
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RecalculateAttendanceStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Attendance $attendance;

    /**
     * Create a new job instance.
     *
     * @param Attendance $attendance Record absensi yang akan dihitung ulang statusnya
     */
    public function __construct(Attendance $attendance)
    {
        $this->attendance = $attendance;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // Load relasi yang mungkin belum ada (terutama jika model $attendance
        // tidak di-fresh dari DB saat di-dispatch)
        $this->attendance->loadMissing(['user', 'shift']);

        $attendance = $this->attendance;
        $user = $attendance->user;
        $currentProcessDate = $attendance->attendance_date; // Tanggal absensi

        // Pastikan user ada
        if (!$user) {
            Log::error("RecalculateAttendanceStatus Job: User not found for Attendance ID {$attendance->id}.");
            return; // Hentikan job jika user tidak ada
        }

        Log::info("Recalculating status for Attendance ID: {$attendance->id} (User: {$user->id}, Date: {$currentProcessDate->format('Y-m-d')})");

        DB::beginTransaction();
        try {
            $finalStatus = null;
            $finalNotes = null;
            $isNormalAttendance = true; // Asumsi awal
            $shiftIdToSave = $attendance->shift_id; // Ambil dari data attendance saat ini

            $holiday = Holiday::where('tanggal', $currentProcessDate->toDateString())->first();
            $isWeekend = $currentProcessDate->isWeekend();
            $isHolidayOrWeekend = $holiday || $isWeekend;

            // --- 1. Cek Cuti (Approved) ---
            $approvedLeave = Cuti::where('user_id', $user->id)->where('status', 'approved')
                ->where('mulai_cuti', '<=', $currentProcessDate->toDateString())
                ->where('selesai_cuti', '>=', $currentProcessDate->toDateString())->first();
            if ($approvedLeave) {
                $finalStatus = (strtolower($approvedLeave->jenisCuti->nama_cuti ?? '') === 'cuti sakit') ? 'Sakit' : 'Cuti';
                $finalNotes = $approvedLeave->jenisCuti->nama_cuti ?? 'Cuti';
                $isNormalAttendance = false;
                $shiftIdToSave = null;
            }

            // --- 2. Cek Dinas Luar (Jika tidak Cuti) ---
            if (!$finalStatus) {
                $onDuty = PerjalananDinas::where('user_id', $user->id)
                    ->where('tanggal_berangkat', '<=', $currentProcessDate->toDateString())
                    ->where('perkiraan_tanggal_pulang', '>=', $currentProcessDate->toDateString())->first();
                if ($onDuty) {
                    $finalStatus = 'Dinas Luar';
                    $finalNotes = 'Perjalanan Dinas ke ' . $onDuty->jurusan;
                    $isNormalAttendance = false;
                    $shiftIdToSave = null;
                }
            }

            // --- 3. Cek Hari Libur / Weekend (Jika tidak Cuti/Dinas) ---
            if (!$finalStatus && $isHolidayOrWeekend) {
                 $approvedOvertime = Overtime::where('user_id', $user->id)
                        ->where('tanggal_lembur', $currentProcessDate->toDateString())
                        ->where('status', 'approved')->first();
                 $hasAttendanceRecord = (bool) $attendance->clock_in_time; // Cek apakah ada clock in

                if ($hasAttendanceRecord && $approvedOvertime) {
                     $finalStatus = 'Lembur'; $finalNotes = 'Lembur pada hari libur/weekend.';
                     $isNormalAttendance = false;
                     // $shiftIdToSave sudah dipreserve
                } elseif (!$hasAttendanceRecord && !$approvedOvertime) {
                     $finalStatus = 'Libur'; $finalNotes = $holiday ? $holiday->nama_libur : 'Weekend';
                     $isNormalAttendance = false;
                     $shiftIdToSave = null;
                } elseif (!$hasAttendanceRecord && $approvedOvertime) {
                     $finalStatus = 'Libur';
                     $finalNotes = 'Lembur disetujui tapi tidak ada data absensi.';
                     $isNormalAttendance = false;
                     $shiftIdToSave = null;
                }
                 // Jika $hasAttendanceRecord && !$approvedOvertime, $isNormalAttendance tetap true.
            }

            // --- 4. Proses Absensi Normal / Alpha (Jika status belum ditentukan) ---
            if ($isNormalAttendance) {
                // Cek kelengkapan data SETELAH potensi koreksi
                if (!$attendance->clock_in_time || !$attendance->clock_out_time) {
                    $finalStatus = 'Alpha';
                    $finalNotes = 'Data absensi tidak lengkap.';
                    if (!$attendance->clock_in_time && !$attendance->clock_out_time) $finalNotes .= ' (Tidak ada Check-in & Check-out)';
                    elseif (!$attendance->clock_in_time) $finalNotes .= ' (Tidak ada Check-in)';
                    else $finalNotes .= ' (Tidak ada Check-out)';
                     // Tidak perlu kirim reminder lagi dari sini
                } else {
                    // Data Lengkap -> Hitung Hadir/Terlambat/Pulang Cepat
                    $shift = $attendance->shift; // Ambil shift dari relasi yg di-load
                    if (!$shift) {
                        // Jika shift ID ada tapi relasi tidak ditemukan (misal shift dihapus)
                        if ($attendance->shift_id) {
                            Log::warning("RecalculateAttendanceStatus Job: Shift ID {$attendance->shift_id} not found for Attendance ID {$attendance->id}. Setting status to Error.");
                            $finalStatus = 'Error';
                            $finalNotes = 'Data shift tidak valid atau telah dihapus.';
                        } else {
                            // Jika shift ID memang null setelah koreksi (seharusnya tidak terjadi jika jam lengkap)
                            Log::warning("RecalculateAttendanceStatus Job: Shift ID is null for complete attendance ID {$attendance->id}. Setting status to Alpha.");
                            $finalStatus = 'Alpha';
                            $finalNotes = 'Data shift tidak ditentukan.';
                        }
                    } else {
                        // Shift ditemukan, lanjutkan perhitungan
                        $clockIn = Carbon::parse($attendance->clock_in_time);
                        $clockOut = Carbon::parse($attendance->clock_out_time);
                        $shiftStartTime = Carbon::parse($currentProcessDate->toDateString() . ' ' . $shift->start_time->format('H:i:s'));
                        $shiftEndTime = Carbon::parse($currentProcessDate->toDateString() . ' ' . $shift->end_time->format('H:i:s'));
                        if ($shift->crosses_midnight) $shiftEndTime->addDay();

                        $gracePeriodMinutes = 5; // Ambil dari config jika perlu
                        $allowedStartTime = $shiftStartTime->copy()->addMinutes($gracePeriodMinutes);
                        $isLate = $clockIn->gt($allowedStartTime);
                        $lateMinutes = $isLate ? $clockIn->diffInMinutes($shiftStartTime) : 0;

                        $actualClockOut = $clockOut;
                        if ($shift->crosses_midnight && $actualClockOut->hour < 5 && $actualClockOut->toDateString() == $currentProcessDate->toDateString()) {
                            $actualClockOut = $actualClockOut->copy()->addDay();
                        }
                        $isEarly = $actualClockOut->lt($shiftEndTime);
                        $earlyLeaveMinutes = $isEarly ? $actualClockOut->diffInMinutes($shiftEndTime) : 0;

                        if ($isLate && $isEarly) { $finalStatus = 'Terlambat & Pulang Cepat'; $finalNotes = "Terlambat {$lateMinutes} menit, Pulang Cepat {$earlyLeaveMinutes} menit."; }
                        elseif ($isLate) { $finalStatus = 'Terlambat'; $finalNotes = "Terlambat {$lateMinutes} menit."; }
                        elseif ($isEarly) { $finalStatus = 'Pulang Cepat'; $finalNotes = "Pulang Cepat {$earlyLeaveMinutes} menit."; }
                        else { $finalStatus = 'Hadir'; $finalNotes = 'Tepat Waktu.'; }
                    }
                }
            }

            // --- 5. Simpan Hasil ke Database ---
            // Hanya update jika status atau notes berubah
            if ($attendance->attendance_status !== $finalStatus || $attendance->notes !== $finalNotes || $attendance->shift_id !== $shiftIdToSave) {
                $attendance->attendance_status = $finalStatus;
                $attendance->notes = $finalNotes;
                $attendance->shift_id = $shiftIdToSave; // Update shift ID juga jika berubah
                $attendance->save();
                Log::info("Status updated to '{$finalStatus}' for Attendance ID: {$attendance->id}");
            } else {
                Log::info("No status change needed for Attendance ID: {$attendance->id}");
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error recalculating status for Attendance ID {$this->attendance->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            // Anda bisa menambahkan mekanisme retry atau notifikasi error di sini jika perlu
            // $this->fail($e); // Untuk menandai job gagal dan mungkin dicoba lagi oleh queue worker
        }
    }
}

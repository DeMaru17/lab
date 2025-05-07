<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\User;
use App\Models\Shift;
use App\Models\Cuti;
use App\Models\PerjalananDinas;
use App\Models\Overtime;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail; // Import Mail facade
use App\Mail\AttendanceCorrectionReminderMail; // Import Mailable Anda

class ProcessDailyAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:process-daily {--days=5 : Jumlah hari ke belakang yang akan diproses ulang.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process daily attendance records for the last N days to determine final status, prioritizing accuracy.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $daysToProcess = $this->option('days');
        if (!is_numeric($daysToProcess) || $daysToProcess < 1) {
            $this->warn("Opsi --days tidak valid, menggunakan default 5 hari.");
            $daysToProcess = 5;
        }

        $endDate = Carbon::yesterday()->startOfDay();
        $startDate = $endDate->copy()->subDays($daysToProcess - 1);

        $this->info("Memulai proses status absensi dari {$startDate->format('Y-m-d')} hingga {$endDate->format('Y-m-d')} ({$daysToProcess} hari).");
        Log::info("Starting daily attendance processing from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}.");

        $usersToProcess = User::whereIn('role', ['personil', 'admin'])
            ->whereNotNull('tanggal_mulai_bekerja')
            ->get();

        if ($usersToProcess->isEmpty()) {
            $this->info("Tidak ada user 'personil' atau 'admin' yang perlu diproses.");
            Log::info("No 'personil' or 'admin' users found to process.");
            return 0;
        }

        $this->info("Ditemukan " . $usersToProcess->count() . " user.");
        $totalProcessed = 0;
        $totalErrors = 0;

        $dateRange = Carbon::parse($startDate)->toPeriod($endDate);
        foreach ($dateRange as $currentProcessDate) {
            $this->line("--- Memproses Tanggal: {$currentProcessDate->format('Y-m-d')} ---");

            $holiday = Holiday::where('tanggal', $currentProcessDate->toDateString())->first();
            $isWeekend = $currentProcessDate->isWeekend();
            $isHolidayOrWeekend = $holiday || $isWeekend;

            foreach ($usersToProcess as $user) {
                if ($user->tanggal_mulai_bekerja->gt($currentProcessDate)) {
                    // $this->line("    Skipping User ID: {$user->id} - {$user->name} (Belum mulai bekerja pada {$currentProcessDate->format('Y-m-d')})");
                    continue;
                }

                $this->line("  Memproses User ID: {$user->id} - {$user->name}");
                DB::beginTransaction();
                try {
                    $finalStatus = null;
                    $finalNotes = null;
                    $isNormalAttendance = true; // Asumsi awal adalah absensi normal
                    $attendance = Attendance::with('shift')
                        ->where('user_id', $user->id)
                        ->where('attendance_date', $currentProcessDate->toDateString())
                        ->first();
                    $shiftIdToSave = $attendance->shift_id ?? null;
                    $shouldSendCorrectionReminder = false; // Flag untuk reminder

                    // --- 1. Cek Cuti (Approved) ---
                    $approvedLeave = Cuti::where('user_id', $user->id)->where('status', 'approved')
                        ->where('mulai_cuti', '<=', $currentProcessDate->toDateString())
                        ->where('selesai_cuti', '>=', $currentProcessDate->toDateString())->first();
                    if ($approvedLeave) {
                        $finalStatus = (strtolower($approvedLeave->jenisCuti->nama_cuti ?? '') === 'cuti sakit') ? 'Sakit' : 'Cuti';
                        $finalNotes = $approvedLeave->jenisCuti->nama_cuti ?? 'Cuti';
                        $this->info("    -> Status: {$finalStatus}");
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
                            $this->info("    -> Status: Dinas Luar");
                            $isNormalAttendance = false;
                            $shiftIdToSave = null;
                        }
                    }

                    // --- 3. Cek Hari Libur / Weekend (Jika tidak Cuti/Dinas) ---
                    if (!$finalStatus && $isHolidayOrWeekend) {
                        $approvedOvertime = Overtime::where('user_id', $user->id)
                            ->where('tanggal_lembur', $currentProcessDate->toDateString())
                            ->where('status', 'approved')->first();
                        $hasAttendanceRecord = (bool) $attendance?->clock_in_time;

                        if ($hasAttendanceRecord && $approvedOvertime) {
                            $finalStatus = 'Lembur';
                            $finalNotes = 'Lembur pada hari libur/weekend.';
                            $this->info("    -> Status: Lembur (Weekend/Holiday)");
                            $isNormalAttendance = false;
                        } elseif (!$hasAttendanceRecord && !$approvedOvertime) {
                            $finalStatus = 'Libur';
                            $finalNotes = $holiday ? $holiday->nama_libur : 'Weekend';
                            $this->info("    -> Status: Libur (Weekend/Holiday)");
                            $isNormalAttendance = false;
                            $shiftIdToSave = null;
                        } elseif (!$hasAttendanceRecord && $approvedOvertime) {
                            $finalStatus = 'Libur';
                            $finalNotes = 'Lembur disetujui tapi tidak ada data absensi.';
                            $this->info("    -> Status: Libur (Lembur tanpa Absensi)");
                            $isNormalAttendance = false;
                            $shiftIdToSave = null;
                        }
                        // Jika $hasAttendanceRecord && !$approvedOvertime, $isNormalAttendance tetap true,
                        // akan diproses sebagai hari biasa di bawah.
                    }

                    // --- 4. Proses Absensi Normal / Alpha (Jika tidak Cuti/Dinas/Libur/Lembur yang sudah final) ---
                    if ($isNormalAttendance) {
                        if (!$attendance) {
                            $finalStatus = 'Alpha';
                            $finalNotes = 'Tidak ada data check-in/check-out.';
                            $this->warn("    -> Status: Alpha (No record)");
                            $shiftIdToSave = null;
                            // Tidak ada reminder untuk Alpha murni tanpa record attendance
                        } elseif (!$attendance->clock_in_time || !$attendance->clock_out_time) {
                            $finalStatus = 'Alpha';
                            $finalNotes = 'Data absensi tidak lengkap.';
                            if (!$attendance->clock_in_time && !$attendance->clock_out_time) $finalNotes .= ' (Tidak ada Check-in & Check-out)';
                            elseif (!$attendance->clock_in_time) $finalNotes .= ' (Tidak ada Check-in)';
                            else $finalNotes .= ' (Tidak ada Check-out)';
                            $this->warn("    -> Status: Alpha (Data Tidak Lengkap) untuk User ID: {$user->id}");

                            // Cek apakah perlu kirim reminder
                            if (
                                is_null($attendance->last_correction_reminder_sent_at) ||
                                Carbon::parse($attendance->last_correction_reminder_sent_at)->lt(Carbon::today()->subDays(1))
                            ) {
                                $shouldSendCorrectionReminder = true;
                            }
                            // $shiftIdToSave sudah dipreserve
                        } else {
                            // Data Lengkap -> Hitung Hadir/Terlambat/Pulang Cepat
                            $shift = $attendance->shift;
                            if (!$shift) {
                                throw new \Exception("Shift tidak ditemukan (ID: {$attendance->shift_id}) untuk absensi ID {$attendance->id}.");
                            }
                            $this->info("    -> Shift: {$shift->name} ({$shift->start_time->format('H:i')} - {$shift->end_time->format('H:i')})");

                            $clockIn = Carbon::parse($attendance->clock_in_time);
                            $clockOut = Carbon::parse($attendance->clock_out_time);
                            $shiftStartTime = Carbon::parse($currentProcessDate->toDateString() . ' ' . $shift->start_time->format('H:i:s'));
                            $shiftEndTime = Carbon::parse($currentProcessDate->toDateString() . ' ' . $shift->end_time->format('H:i:s'));
                            if ($shift->crosses_midnight) $shiftEndTime->addDay();

                            $gracePeriodMinutes = 5;
                            $allowedStartTime = $shiftStartTime->copy()->addMinutes($gracePeriodMinutes);
                            $isLate = $clockIn->gt($allowedStartTime);
                            $lateMinutes = $isLate ? $clockIn->diffInMinutes($shiftStartTime) : 0;

                            $actualClockOut = $clockOut;
                            if ($shift->crosses_midnight && $actualClockOut->hour < 5 && $actualClockOut->toDateString() == $currentProcessDate->toDateString()) {
                                $actualClockOut = $actualClockOut->copy()->addDay();
                                $this->info("      -> Menyesuaikan Clock Out ke hari berikutnya: " . $actualClockOut->format('Y-m-d H:i:s'));
                            }
                            $isEarly = $actualClockOut->lt($shiftEndTime);
                            $earlyLeaveMinutes = $isEarly ? $actualClockOut->diffInMinutes($shiftEndTime) : 0;

                            if ($isLate && $isEarly) {
                                $finalStatus = 'Terlambat & Pulang Cepat';
                                $finalNotes = "Terlambat {$lateMinutes} menit, Pulang Cepat {$earlyLeaveMinutes} menit.";
                            } elseif ($isLate) {
                                $finalStatus = 'Terlambat';
                                $finalNotes = "Terlambat {$lateMinutes} menit.";
                            } elseif ($isEarly) {
                                $finalStatus = 'Pulang Cepat';
                                $finalNotes = "Pulang Cepat {$earlyLeaveMinutes} menit.";
                            } else {
                                $finalStatus = 'Hadir';
                                $finalNotes = 'Tepat Waktu.';
                            }
                            $this->info("    -> Status Final: {$finalStatus}");
                            // $shiftIdToSave sudah dipreserve
                        }
                    }

                    // --- 5. Simpan Hasil ke Database ---
                    $dataToUpdateOrCreate = [
                        'attendance_status' => $finalStatus,
                        'notes' => $finalNotes,
                        'shift_id' => $shiftIdToSave,
                        'clock_in_time' => ($isNormalAttendance && $attendance && $attendance->clock_in_time && $finalStatus !== 'Alpha') ? $attendance->clock_in_time : null,
                        'clock_out_time' => ($isNormalAttendance && $attendance && $attendance->clock_out_time && $finalStatus !== 'Alpha') ? $attendance->clock_out_time : null,
                        'clock_in_latitude' => $attendance?->clock_in_latitude,
                        'clock_in_longitude' => $attendance?->clock_in_longitude,
                        'clock_in_location_status' => $attendance?->clock_in_location_status,
                        'clock_out_latitude' => $attendance?->clock_out_latitude,
                        'clock_out_longitude' => $attendance?->clock_out_longitude,
                        'clock_out_location_status' => $attendance?->clock_out_location_status,
                        'clock_in_photo_path' => $attendance?->clock_in_photo_path,
                        'clock_out_photo_path' => $attendance?->clock_out_photo_path,
                        'is_corrected' => $attendance?->is_corrected ?? false,
                    ];

                    // Tambahkan update last_correction_reminder_sent_at jika reminder dikirim
                    if ($shouldSendCorrectionReminder && $user->email) {
                        $dataToUpdateOrCreate['last_correction_reminder_sent_at'] = now();
                    } elseif ($attendance && $attendance->last_correction_reminder_sent_at) {
                        // Pertahankan nilai lama jika ada dan reminder tidak dikirim sekarang
                        $dataToUpdateOrCreate['last_correction_reminder_sent_at'] = $attendance->last_correction_reminder_sent_at;
                    } else {
                        $dataToUpdateOrCreate['last_correction_reminder_sent_at'] = null;
                    }

                    Attendance::updateOrCreate(
                        ['user_id' => $user->id, 'attendance_date' => $currentProcessDate->toDateString()],
                        $dataToUpdateOrCreate
                    );

                    // --- KIRIM EMAIL REMINDER SETELAH DATA DISIMPAN (jika perlu) ---
                    if ($shouldSendCorrectionReminder && $user->email) {
                        // Ambil lagi record attendance yang baru diupdate untuk data terbaru
                        $updatedAttendance = Attendance::where('user_id', $user->id)
                            ->where('attendance_date', $currentProcessDate->toDateString())
                            ->first();
                        if ($updatedAttendance) {
                            try {
                                Mail::to($user->email)->queue(new AttendanceCorrectionReminderMail($updatedAttendance, $user));
                                Log::info("    -> Reminder koreksi absensi DIANTRIKAN untuk {$user->email} tanggal {$updatedAttendance->attendance_date->format('Y-m-d')}");
                            } catch (\Exception $mailError) {
                                Log::error("    -> Gagal MENGANTRIKAN reminder koreksi ke {$user->email}: " . $mailError->getMessage());
                            }
                        }
                    }

                    DB::commit();
                    $totalProcessed++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("    -> Gagal memproses User ID: {$user->id}. Error: " . $e->getMessage());
                    Log::error("Error processing attendance for User ID {$user->id} on {$currentProcessDate->format('Y-m-d')}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                    $totalErrors++;
                }
            } // End user loop
        } // End date loop

        $this->info("-----------------------------------------");
        $this->info("Proses status absensi selesai.");
        $this->info("Total Record Diproses/Dicek Ulang: {$totalProcessed}");
        $this->info("Total Error                    : {$totalErrors}");
        Log::info("Finished daily attendance processing for range {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}. Processed: {$totalProcessed}, Errors: {$totalErrors}.");

        return Command::SUCCESS; // atau 0
    }
}

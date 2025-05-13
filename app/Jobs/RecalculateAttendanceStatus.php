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

/**
 * Class RecalculateAttendanceStatus
 *
 * Job ini bertanggung jawab untuk menghitung ulang dan memperbarui status absensi harian
 * seorang karyawan berdasarkan data terbaru (misalnya setelah koreksi absensi disetujui).
 * Proses ini mempertimbangkan cuti, perjalanan dinas, hari libur, lembur, dan data check-in/out
 * untuk menentukan status final seperti 'Hadir', 'Terlambat', 'Alpha', dll.
 * Job ini diimplementasikan sebagai ShouldQueue agar dapat diproses secara asynchronous
 * di background, mengurangi waktu tunggu pengguna dan meningkatkan responsivitas aplikasi.
 *
 * @package App\Jobs
 */
class RecalculateAttendanceStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Instance model Attendance yang statusnya akan dihitung ulang.
     *
     * @var \App\Models\Attendance
     */
    protected Attendance $attendance;

    /**
     * Membuat instance job baru.
     *
     * @param \App\Models\Attendance $attendance Record absensi yang akan dihitung ulang statusnya.
     * Instance ini akan diserialisasi dan dikirim ke queue.
     * @return void
     */
    public function __construct(Attendance $attendance)
    {
        $this->attendance = $attendance;
    }

    /**
     * Menjalankan logika utama dari job.
     * Method ini akan dipanggil oleh queue worker untuk memproses job.
     *
     * @return void
     */
    public function handle(): void
    {
        // Memuat relasi yang mungkin belum ada pada instance $this->attendance,
        // terutama jika model tidak di-fresh dari database saat job di-dispatch ke queue.
        // Ini penting untuk memastikan data user dan shift tersedia.
        $this->attendance->loadMissing(['user', 'shift']);

        /** @var \App\Models\Attendance $attendance Instance absensi yang diproses. */
        $attendance = $this->attendance;
        /** @var \App\Models\User|null $user Pengguna pemilik absensi. */
        $user = $attendance->user;
        /** @var \Carbon\Carbon $currentProcessDate Tanggal absensi yang diproses. */
        $currentProcessDate = $attendance->attendance_date;

        // Validasi dasar: pastikan data pengguna ada.
        if (!$user) {
            Log::error("RecalculateAttendanceStatus Job: User not found for Attendance ID {$attendance->id}. Job terminated.");
            return; // Hentikan job jika user tidak ditemukan untuk menghindari error lebih lanjut.
        }

        Log::info("RecalculateAttendanceStatus Job: Starting recalculation for Attendance ID: {$attendance->id} (User: {$user->id}, Date: {$currentProcessDate->format('Y-m-d')})");

        DB::beginTransaction(); // Memulai transaksi database untuk memastikan konsistensi data.
        try {
            $finalStatus = null;        // Status akhir absensi yang akan ditentukan.
            $finalNotes = null;         // Catatan tambahan untuk status akhir.
            $isNormalAttendance = true; // Flag, true jika ini adalah absensi normal (bukan cuti/dinas/libur).
            $shiftIdToSave = $attendance->shift_id; // ID shift yang akan disimpan, defaultnya dari data absensi saat ini.

            // Mengambil data hari libur dan mengecek apakah tanggal ini akhir pekan.
            $holiday = Holiday::where('tanggal', $currentProcessDate->toDateString())->first();
            $isWeekend = $currentProcessDate->isWeekend();
            $isHolidayOrWeekend = $holiday || $isWeekend; // True jika hari libur nasional atau akhir pekan.

            // --- Prioritas 1: Cek Cuti yang Sudah Disetujui (Approved) ---
            $approvedLeave = Cuti::where('user_id', $user->id)->where('status', 'approved')
                ->where('mulai_cuti', '<=', $currentProcessDate->toDateString())
                ->where('selesai_cuti', '>=', $currentProcessDate->toDateString())
                ->with('jenisCuti:id,nama_cuti') // Eager load untuk efisiensi
                ->first();

            if ($approvedLeave) {
                $finalStatus = (strtolower(optional($approvedLeave->jenisCuti)->nama_cuti ?? '') === 'cuti sakit') ? 'Sakit' : 'Cuti';
                $finalNotes = optional($approvedLeave->jenisCuti)->nama_cuti ?? 'Cuti Disetujui';
                $isNormalAttendance = false; // Bukan absensi normal.
                $shiftIdToSave = null;       // Tidak ada shift yang relevan untuk cuti.
                Log::info("RecalculateAttendanceStatus Job (ID: {$attendance->id}): Status determined as '{$finalStatus}' from Cuti module.");
            }

            // --- Prioritas 2: Cek Perjalanan Dinas (Jika Tidak Sedang Cuti) ---
            if (!$finalStatus) { // Hanya proses jika status belum ditentukan oleh cuti.
                $onDuty = PerjalananDinas::where('user_id', $user->id)
                    ->where('tanggal_berangkat', '<=', $currentProcessDate->toDateString())
                    ->where('perkiraan_tanggal_pulang', '>=', $currentProcessDate->toDateString())
                    // Pertimbangkan juga status perjalanan dinas jika perlu (misal: 'berlangsung').
                    ->first();
                if ($onDuty) {
                    $finalStatus = 'Dinas Luar';
                    $finalNotes = 'Perjalanan Dinas ke ' . $onDuty->jurusan;
                    $isNormalAttendance = false;
                    $shiftIdToSave = null;
                    Log::info("RecalculateAttendanceStatus Job (ID: {$attendance->id}): Status determined as 'Dinas Luar'.");
                }
            }

            // --- Prioritas 3: Cek Hari Libur / Akhir Pekan (Jika Tidak Cuti/Dinas) ---
            if (!$finalStatus && $isHolidayOrWeekend) {
                 // Cek apakah ada lembur yang disetujui pada hari libur/weekend ini.
                 $approvedOvertime = Overtime::where('user_id', $user->id)
                        ->where('tanggal_lembur', $currentProcessDate->toDateString())
                        ->where('status', 'approved')->first();
                 // Cek apakah ada record check-in (bukti kehadiran).
                 $hasAttendanceRecord = (bool) $attendance->clock_in_time;

                if ($hasAttendanceRecord && $approvedOvertime) {
                     // Jika ada absensi DAN lembur disetujui -> status 'Lembur'.
                     $finalStatus = 'Lembur';
                     $finalNotes = 'Lembur pada ' . ($holiday ? $holiday->nama_libur : 'Akhir Pekan');
                     $isNormalAttendance = false; // Dianggap bukan absensi normal karena ada perlakuan khusus.
                     // $shiftIdToSave sudah di-preserve dari data absensi.
                } elseif (!$hasAttendanceRecord && !$approvedOvertime) {
                     // Jika TIDAK ada absensi DAN TIDAK ada lembur disetujui -> status 'Libur'.
                     $finalStatus = 'Libur';
                     $finalNotes = $holiday ? $holiday->nama_libur : 'Akhir Pekan';
                     $isNormalAttendance = false;
                     $shiftIdToSave = null;
                } elseif (!$hasAttendanceRecord && $approvedOvertime) {
                     // Jika TIDAK ada absensi TAPI lembur disetujui -> status tetap 'Libur'.
                     // Catatan: Lembur disetujui tapi tidak ada bukti kehadiran.
                     $finalStatus = 'Libur';
                     $finalNotes = ($holiday ? $holiday->nama_libur : 'Akhir Pekan') . ' (Lembur disetujui tanpa data absensi)';
                     $isNormalAttendance = false;
                     $shiftIdToSave = null;
                }
                // Kasus lain: Jika $hasAttendanceRecord && !$approvedOvertime (ada absensi tapi tidak ada lembur disetujui di hari libur),
                // maka $isNormalAttendance akan tetap true dan akan diproses di blok absensi normal di bawah.
                // Statusnya bisa jadi 'Hadir', 'Terlambat', dll. tergantung data check-in/out.
                if ($finalStatus) { // Log jika status sudah ditentukan di blok ini
                    Log::info("RecalculateAttendanceStatus Job (ID: {$attendance->id}): Status determined as '{$finalStatus}' due to Holiday/Weekend check.");
                }
            }

            // --- Prioritas 4: Proses Absensi Normal / Alpha (Jika status belum final dan bukan hari yang sudah pasti Libur/Cuti/Dinas) ---
            if ($isNormalAttendance) {
                // Cek kelengkapan data SETELAH potensi koreksi (data $attendance adalah yang terbaru).
                if (!$attendance->clock_in_time || !$attendance->clock_out_time) {
                    // Ada record absensi, tapi data tidak lengkap.
                    $finalStatus = 'Alpha';
                    $finalNotes = 'Data absensi tidak lengkap';
                    if (!$attendance->clock_in_time && !$attendance->clock_out_time) $finalNotes .= ' (Tidak ada Check-in & Check-out).';
                    elseif (!$attendance->clock_in_time) $finalNotes .= ' (Tidak ada Check-in).';
                    else $finalNotes .= ' (Tidak ada Check-out).';
                    // Reminder koreksi sudah dihandle oleh command ProcessDailyAttendance, tidak perlu di job ini.
                    Log::warn("RecalculateAttendanceStatus Job (ID: {$attendance->id}): Status determined as 'Alpha' due to incomplete data (Post-Correction).");
                } else {
                    // Data absensi lengkap (ada check-in dan check-out).
                    /** @var \App\Models\Shift|null $shift Shift terkait absensi. */
                    $shift = $attendance->shift; // Ambil shift dari relasi yang sudah di-load.

                    if (!$shift) {
                        // Jika shift ID ada di $attendance tapi relasi tidak ditemukan (misal, shift dihapus dari DB).
                        if ($attendance->shift_id) {
                            Log::warning("RecalculateAttendanceStatus Job: Shift ID {$attendance->shift_id} not found for Attendance ID {$attendance->id}, but attendance time is complete. Setting status to Error.");
                            $finalStatus = 'Error'; // Atau status lain yang menandakan data tidak konsisten.
                            $finalNotes = 'Data shift tidak valid atau telah dihapus untuk absensi ini.';
                        } else {
                            // Jika shift ID memang null (misal, setelah koreksi tidak memilih shift padahal jam lengkap).
                            // Ini seharusnya tidak terjadi jika validasi form koreksi benar.
                            Log::warning("RecalculateAttendanceStatus Job: Shift ID is null for complete attendance ID {$attendance->id}. Setting status to Alpha.");
                            $finalStatus = 'Alpha';
                            $finalNotes = 'Data shift tidak ditentukan meskipun jam absensi lengkap.';
                        }
                    } else {
                        // Shift ditemukan, lanjutkan perhitungan status Hadir/Terlambat/Pulang Cepat.
                        Log::info("RecalculateAttendanceStatus Job (ID: {$attendance->id}): Processing normal attendance with Shift: {$shift->name} ({$shift->start_time->format('H:i')} - {$shift->end_time->format('H:i')})");

                        $clockIn = Carbon::parse($attendance->clock_in_time);
                        $clockOut = Carbon::parse($attendance->clock_out_time);

                        // Tentukan jam mulai dan selesai shift pada tanggal absensi yang diproses.
                        $shiftStartTimeOnDate = Carbon::parse($currentProcessDate->toDateString() . ' ' . $shift->start_time->format('H:i:s'));
                        $shiftEndTimeOnDate = Carbon::parse($currentProcessDate->toDateString() . ' ' . $shift->end_time->format('H:i:s'));

                        // Sesuaikan tanggal selesai shift jika shift melewati tengah malam (crosses_midnight).
                        if ($shift->crosses_midnight) {
                            $shiftEndTimeOnDate->addDay();
                        }

                        // Toleransi keterlambatan (grace period) dalam menit.
                        $gracePeriodMinutes = config('hris.attendance_grace_period_minutes', 5); // Ambil dari config, default 5 menit.
                        $allowedStartTime = $shiftStartTimeOnDate->copy()->addMinutes($gracePeriodMinutes);

                        $isLate = $clockIn->gt($allowedStartTime);
                        $lateMinutes = $isLate ? $clockIn->diffInMinutes($shiftStartTimeOnDate) : 0; // Hitung menit terlambat.

                        // Penyesuaian jam pulang aktual jika shift cross midnight dan jam pulang tercatat di hari yang sama dengan check-in.
                        $actualClockOut = $clockOut->copy(); // Salin agar tidak mengubah objek asli.
                        if ($shift->crosses_midnight && $actualClockOut->hour < config('hris.cross_midnight_checkout_day_change_hour', 5) && $actualClockOut->isSameDay($clockIn)) {
                            // Jika shift cross midnight, jam pulang < 05:00 (configurable), dan masih di hari yang sama dengan check-in,
                            // anggap itu adalah hari berikutnya.
                            $actualClockOut->addDay();
                            Log::info("RecalculateAttendanceStatus Job (ID: {$attendance->id}): Clock-out time adjusted to next day for cross-midnight shift: " . $actualClockOut->format('Y-m-d H:i:s'));
                        }

                        $isEarly = $actualClockOut->lt($shiftEndTimeOnDate);
                        $earlyLeaveMinutes = $isEarly ? $actualClockOut->diffInMinutes($shiftEndTimeOnDate) : 0; // Hitung menit pulang cepat.

                        // Tentukan status final berdasarkan keterlambatan dan kepulangan cepat.
                        if ($isLate && $isEarly) {
                            $finalStatus = 'Terlambat & Pulang Cepat';
                            $finalNotes = "Terlambat {$lateMinutes} menit, dan Pulang Cepat {$earlyLeaveMinutes} menit.";
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
                        Log::info("RecalculateAttendanceStatus Job (ID: {$attendance->id}): Final status determined (Normal): {$finalStatus}");
                    }
                }
            }

            // --- 5. Simpan Hasil Status Final ke Database ---
            // Hanya lakukan update jika status, catatan, atau shift ID berubah untuk menghindari query DB yang tidak perlu.
            if ($attendance->attendance_status !== $finalStatus || $attendance->notes !== $finalNotes || $attendance->shift_id !== $shiftIdToSave) {
                $attendance->attendance_status = $finalStatus;
                $attendance->notes = $finalNotes;
                $attendance->shift_id = $shiftIdToSave; // Update shift_id juga jika ada perubahan (misal, saat Cuti/Libur jadi null).
                $attendance->save(); // Menyimpan perubahan ke database.
                Log::info("RecalculateAttendanceStatus Job (ID: {$attendance->id}): Status successfully updated to '{$finalStatus}'. Notes: '{$finalNotes}'. Shift ID: " . ($shiftIdToSave ?? 'NULL'));
            } else {
                Log::info("RecalculateAttendanceStatus Job (ID: {$attendance->id}): No status change needed. Current status '{$attendance->attendance_status}' is already correct.");
            }

            DB::commit(); // Simpan semua perubahan jika tidak ada error.

        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan semua perubahan jika terjadi error selama proses.
            Log::error("RecalculateAttendanceStatus Job: Error recalculating status for Attendance ID {$this->attendance->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            // Anda bisa menambahkan mekanisme retry atau notifikasi error di sini jika perlu.
            // Misalnya, menandai job sebagai gagal agar bisa dicoba lagi oleh queue worker:
            // $this->fail($e);
        }
    }
}

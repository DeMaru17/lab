<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\User;
use App\Models\Shift;
use App\Models\Cuti; // Digunakan untuk mengecek apakah user sedang cuti
use App\Models\PerjalananDinas; // Digunakan untuk mengecek apakah user sedang perjalanan dinas
use App\Models\Overtime; // Digunakan untuk mengecek lembur di hari libur
use App\Models\Holiday; // Digunakan untuk mengecek hari libur nasional
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail; // Untuk mengirim email
use App\Mail\AttendanceCorrectionReminderMail; // Mailable untuk reminder koreksi

/**
 * Class ProcessDailyAttendance
 *
 * Command Artisan untuk memproses catatan absensi harian karyawan.
 * Command ini akan menentukan status kehadiran final (Hadir, Terlambat, Pulang Cepat, Alpha, Cuti, Sakit, Dinas Luar, Lembur, Libur)
 * untuk N hari ke belakang (default 5 hari). Proses ini memprioritaskan data Cuti dan Perjalanan Dinas
 * yang sudah disetujui, kemudian mengecek hari libur/weekend, dan terakhir memproses absensi normal.
 * Juga mengirimkan email reminder jika ada data absensi yang tidak lengkap.
 *
 * @package App\Console\Commands
 */
class ProcessDailyAttendance extends Command
{
    /**
     * Nama dan signature dari command Artisan.
     * Opsi '--days' memungkinkan pengguna menentukan berapa hari ke belakang yang akan diproses.
     *
     * @var string
     */
    protected $signature = 'attendance:process-daily {--days=5 : Jumlah hari ke belakang yang akan diproses ulang.}';

    /**
     * Deskripsi command Artisan.
     * Akan muncul saat menjalankan 'php artisan list'.
     *
     * @var string
     */
    protected $description = 'Memproses catatan absensi harian untuk N hari terakhir guna menentukan status final, dengan prioritas akurasi.';

    /**
     * Menjalankan logika utama command.
     *
     * @return int Kode status eksekusi (0 untuk sukses, selain itu untuk error).
     */
    public function handle()
    {
        // Mengambil opsi --days dari input command, default 5 jika tidak valid
        $daysToProcess = $this->option('days');
        if (!is_numeric($daysToProcess) || $daysToProcess < 1) {
            $this->warn("Opsi --days tidak valid ('{$daysToProcess}'), menggunakan default 5 hari.");
            Log::warning("ProcessDailyAttendance: Invalid --days option provided ('{$daysToProcess}'), defaulting to 5 days.");
            $daysToProcess = 5;
        }

        // Menentukan rentang tanggal yang akan diproses: dari (kemarin - N hari + 1) hingga kemarin.
        // Tidak memproses hari ini karena data absensi hari ini mungkin belum lengkap.
        $endDate = Carbon::yesterday(config('app.timezone', 'Asia/Jakarta'))->startOfDay();
        $startDate = $endDate->copy()->subDays($daysToProcess - 1);

        $this->info("Memulai proses status absensi dari {$startDate->format('Y-m-d')} hingga {$endDate->format('Y-m-d')} ({$daysToProcess} hari).");
        Log::info("ProcessDailyAttendance: Starting daily attendance processing from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}.");

        // Mengambil semua pengguna dengan peran 'personil' atau 'admin' yang aktif dan sudah mulai bekerja.
        // Hanya pengguna ini yang absensinya perlu diproses.
        $usersToProcess = User::whereIn('role', ['personil', 'admin'])
            ->whereNotNull('tanggal_mulai_bekerja') // Pastikan ada tanggal mulai bekerja
            // ->where('is_active', true) // Tambahkan ini jika ada kolom status aktif user
            ->get();

        if ($usersToProcess->isEmpty()) {
            $this->info("Tidak ada user 'personil' atau 'admin' yang memenuhi kriteria untuk diproses.");
            Log::info("ProcessDailyAttendance: No 'personil' or 'admin' users found to process.");
            return Command::SUCCESS; // Keluar dengan sukses jika tidak ada user
        }

        $this->info("Ditemukan " . $usersToProcess->count() . " user yang akan diproses.");
        $totalProcessed = 0; // Counter untuk record yang berhasil diproses/dicek ulang
        $totalErrors = 0;    // Counter untuk error yang terjadi

        // Membuat periode tanggal dari tanggal mulai hingga tanggal selesai
        $dateRange = Carbon::parse($startDate)->toPeriod($endDate);

        // Loop untuk setiap tanggal dalam rentang yang ditentukan
        foreach ($dateRange as $currentProcessDate) {
            $this->line("--- Memproses Tanggal: {$currentProcessDate->format('Y-m-d')} ---");

            // Cek apakah tanggal yang diproses adalah hari libur nasional
            $holiday = Holiday::where('tanggal', $currentProcessDate->toDateString())->first();
            // Cek apakah tanggal yang diproses adalah akhir pekan (Sabtu/Minggu)
            $isWeekend = $currentProcessDate->isWeekend();
            $isHolidayOrWeekend = $holiday || $isWeekend; // True jika hari libur atau akhir pekan

            // Loop untuk setiap pengguna yang akan diproses
            foreach ($usersToProcess as $user) {
                // Lewati pengguna jika tanggal mulai bekerjanya setelah tanggal yang sedang diproses
                if ($user->tanggal_mulai_bekerja && Carbon::parse($user->tanggal_mulai_bekerja)->gt($currentProcessDate)) {
                    // $this->line("    Melewati User ID: {$user->id} - {$user->name} (Belum mulai bekerja pada {$currentProcessDate->format('Y-m-d')})");
                    continue;
                }

                $this->line("  Memproses User ID: {$user->id} - {$user->name}");
                DB::beginTransaction(); // Memulai transaksi database untuk setiap user per hari
                try {
                    $finalStatus = null; // Status akhir absensi
                    $finalNotes = null;  // Catatan untuk status akhir
                    $isNormalAttendance = true; // Flag, true jika ini adalah absensi normal (bukan cuti/dinas/libur)
                    $shiftIdToSave = null; // ID shift yang akan disimpan, bisa null
                    $shouldSendCorrectionReminder = false; // Flag apakah perlu mengirim email reminder koreksi

                    // Ambil data absensi pengguna untuk tanggal yang sedang diproses
                    $attendance = Attendance::with('shift') // Eager load data shift terkait
                        ->where('user_id', $user->id)
                        ->where('attendance_date', $currentProcessDate->toDateString())
                        ->first();

                    // Jika ada record absensi, pertahankan shift_id aslinya, kecuali jika nanti di-override
                    if ($attendance) {
                        $shiftIdToSave = $attendance->shift_id;
                    }

                    // --- Prioritas 1: Cek Cuti yang Sudah Disetujui (Approved) ---
                    $approvedLeave = Cuti::where('user_id', $user->id)->where('status', 'approved')
                        ->where('mulai_cuti', '<=', $currentProcessDate->toDateString())
                        ->where('selesai_cuti', '>=', $currentProcessDate->toDateString())
                        ->with('jenisCuti:id,nama_cuti') // Eager load nama jenis cuti
                        ->first();

                    if ($approvedLeave) {
                        // Jika jenis cuti adalah 'Cuti Sakit', statusnya 'Sakit', selain itu 'Cuti'
                        $finalStatus = (strtolower(optional($approvedLeave->jenisCuti)->nama_cuti ?? '') === 'cuti sakit') ? 'Sakit' : 'Cuti';
                        $finalNotes = optional($approvedLeave->jenisCuti)->nama_cuti ?? 'Cuti Disetujui';
                        $this->info("    -> Status ditentukan: {$finalStatus} (dari Modul Cuti)");
                        $isNormalAttendance = false; // Bukan absensi normal
                        $shiftIdToSave = null;       // Tidak ada shift relevan untuk cuti
                    }

                    // --- Prioritas 2: Cek Perjalanan Dinas (Jika Tidak Sedang Cuti) ---
                    if (!$finalStatus) { // Hanya proses jika status belum ditentukan oleh cuti
                        $onDuty = PerjalananDinas::where('user_id', $user->id)
                            // Tanggal proses harus berada dalam rentang perjalanan dinas
                            ->where('tanggal_berangkat', '<=', $currentProcessDate->toDateString())
                            ->where('perkiraan_tanggal_pulang', '>=', $currentProcessDate->toDateString())
                            // ->where('status', 'berlangsung') // Anda mungkin ingin filter status perjalanan dinas juga
                            ->first();
                        if ($onDuty) {
                            $finalStatus = 'Dinas Luar';
                            $finalNotes = 'Perjalanan Dinas ke ' . $onDuty->jurusan;
                            $this->info("    -> Status ditentukan: Dinas Luar");
                            $isNormalAttendance = false;
                            $shiftIdToSave = null;
                        }
                    }

                    // --- Prioritas 3: Cek Hari Libur / Akhir Pekan (Jika Tidak Cuti/Dinas) ---
                    if (!$finalStatus && $isHolidayOrWeekend) {
                        // Cek apakah ada lembur yang disetujui pada hari libur/weekend ini
                        $approvedOvertime = Overtime::where('user_id', $user->id)
                            ->where('tanggal_lembur', $currentProcessDate->toDateString())
                            ->where('status', 'approved')->first();
                        $hasAttendanceRecord = $attendance && $attendance->clock_in_time; // Apakah ada record check-in

                        if ($hasAttendanceRecord && $approvedOvertime) {
                            // Jika ada absensi DAN lembur disetujui -> status 'Lembur'
                            $finalStatus = 'Lembur';
                            $finalNotes = 'Lembur pada ' . ($holiday ? $holiday->nama_libur : 'Akhir Pekan');
                            $this->info("    -> Status ditentukan: Lembur (pada Hari Libur/Weekend)");
                            $isNormalAttendance = false; // Dianggap bukan absensi normal karena ada perlakuan khusus
                        } elseif (!$hasAttendanceRecord && !$approvedOvertime) {
                            // Jika TIDAK ada absensi DAN TIDAK ada lembur disetujui -> status 'Libur'
                            $finalStatus = 'Libur';
                            $finalNotes = $holiday ? $holiday->nama_libur : 'Akhir Pekan';
                            $this->info("    -> Status ditentukan: Libur (pada Hari Libur/Weekend)");
                            $isNormalAttendance = false;
                            $shiftIdToSave = null;
                        } elseif (!$hasAttendanceRecord && $approvedOvertime) {
                            // Jika TIDAK ada absensi TAPI lembur disetujui -> status tetap 'Libur'
                            // Catatan: Lembur disetujui tapi tidak ada bukti kehadiran.
                            $finalStatus = 'Libur';
                            $finalNotes = ($holiday ? $holiday->nama_libur : 'Akhir Pekan') . ' (Lembur disetujui tanpa data absensi)';
                            $this->info("    -> Status ditentukan: Libur (Lembur disetujui tanpa absensi pada Hari Libur/Weekend)");
                            $isNormalAttendance = false;
                            $shiftIdToSave = null;
                        }
                        // Kasus lain: Jika ada absensi TAPI lembur TIDAK disetujui pada hari libur/weekend,
                        // maka $isNormalAttendance akan tetap true dan akan diproses di blok absensi normal di bawah.
                        // Ini berarti karyawan tersebut dianggap masuk pada hari libur/weekend tanpa perintah lembur resmi.
                        // Statusnya bisa jadi 'Hadir' atau 'Alpha' tergantung data check-in/out.
                    }

                    // --- Prioritas 4: Proses Absensi Normal / Alpha (Jika Bukan Cuti/Dinas/Libur yang sudah final) ---
                    if ($isNormalAttendance) {
                        if (!$attendance) {
                            // Tidak ada record absensi sama sekali untuk hari ini
                            $finalStatus = 'Alpha';
                            $finalNotes = 'Tidak ada data check-in maupun check-out.';
                            $this->warn("    -> Status ditentukan: Alpha (Tidak ada record absensi)");
                            $shiftIdToSave = null;
                            // Tidak mengirim reminder untuk Alpha murni tanpa ada record absensi sama sekali.
                        } elseif (!$attendance->clock_in_time || !$attendance->clock_out_time) {
                            // Ada record absensi, tapi data tidak lengkap (misal, hanya check-in atau hanya check-out)
                            $finalStatus = 'Alpha';
                            $finalNotes = 'Data absensi tidak lengkap';
                            if (!$attendance->clock_in_time && !$attendance->clock_out_time) $finalNotes .= ' (Tidak ada Check-in & Check-out).';
                            elseif (!$attendance->clock_in_time) $finalNotes .= ' (Tidak ada Check-in).';
                            else $finalNotes .= ' (Tidak ada Check-out).';
                            $this->warn("    -> Status ditentukan: Alpha (Data absensi tidak lengkap) untuk User ID: {$user->id}");

                            // Cek apakah perlu mengirim email reminder untuk melengkapi data absensi.
                            // Reminder dikirim jika belum pernah dikirim, atau jika reminder terakhir sudah lebih dari 1 hari yang lalu.
                            if (
                                is_null($attendance->last_correction_reminder_sent_at) ||
                                Carbon::parse($attendance->last_correction_reminder_sent_at)->lt(Carbon::today()->subDays(1))
                            ) {
                                $shouldSendCorrectionReminder = true;
                            }
                            // $shiftIdToSave (ID shift) sudah di-preserve dari $attendance di awal.
                        } else {
                            // Data absensi lengkap (ada check-in dan check-out)
                            $shift = $attendance->shift; // Ambil shift dari relasi yang sudah di-load
                            if (!$shift) {
                                // Ini seharusnya tidak terjadi jika validasi saat check-in mengharuskan shift
                                $finalStatus = 'Alpha';
                                $finalNotes = 'Data shift tidak valid atau tidak ditemukan untuk absensi ini.';
                                $this->error("    -> Status ditentukan: Alpha (Shift tidak valid) untuk Absensi ID {$attendance->id}.");
                                Log::error("ProcessDailyAttendance: Shift not found (ID: {$attendance->shift_id}) for Attendance ID {$attendance->id}. Setting status to Alpha.");
                                // $shiftIdToSave sudah di-preserve.
                            } else {
                                $this->info("    -> Memproses absensi normal dengan Shift: {$shift->name} ({$shift->start_time->format('H:i')} - {$shift->end_time->format('H:i')})");

                                $clockIn = Carbon::parse($attendance->clock_in_time);
                                $clockOut = Carbon::parse($attendance->clock_out_time);

                                // Tentukan jam mulai dan selesai shift pada tanggal yang diproses
                                $shiftStartTime = Carbon::parse($currentProcessDate->toDateString() . ' ' . $shift->start_time->format('H:i:s'));
                                $shiftEndTime = Carbon::parse($currentProcessDate->toDateString() . ' ' . $shift->end_time->format('H:i:s'));

                                // Sesuaikan tanggal selesai shift jika shift melewati tengah malam (crosses_midnight)
                                if ($shift->crosses_midnight) {
                                    $shiftEndTime->addDay();
                                }

                                $gracePeriodMinutes = 5; // Toleransi keterlambatan dalam menit
                                $allowedStartTime = $shiftStartTime->copy()->addMinutes($gracePeriodMinutes);

                                $isLate = $clockIn->gt($allowedStartTime);
                                $lateMinutes = $isLate ? $clockIn->diffInMinutes($shiftStartTime) : 0; // Hitung menit terlambat

                                // Penyesuaian jam pulang aktual jika shift cross midnight dan jam pulang tercatat di hari yang sama dengan check-in
                                // Ini untuk kasus di mana karyawan pulang setelah tengah malam tapi sistem mencatatnya di hari check-in.
                                $actualClockOut = $clockOut->copy(); // Salin agar tidak mengubah objek asli
                                if ($shift->crosses_midnight && $actualClockOut->hour < 5 && $actualClockOut->isSameDay($clockIn)) {
                                    // Jika shift cross midnight, jam pulang < 05:00, dan masih di hari yang sama dengan check-in,
                                    // anggap itu adalah hari berikutnya.
                                    $actualClockOut->addDay();
                                    $this->info("      -> Jam Pulang disesuaikan ke hari berikutnya (untuk shift cross midnight): " . $actualClockOut->format('Y-m-d H:i:s'));
                                }

                                $isEarly = $actualClockOut->lt($shiftEndTime);
                                $earlyLeaveMinutes = $isEarly ? $actualClockOut->diffInMinutes($shiftEndTime) : 0; // Hitung menit pulang cepat

                                // Tentukan status final berdasarkan keterlambatan dan kepulangan cepat
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
                                $this->info("    -> Status Final ditentukan (Normal): {$finalStatus}");
                                // $shiftIdToSave sudah di-preserve.
                            }
                        }
                    }

                    // --- 5. Simpan Hasil Status Final ke Database ---
                    // Data yang akan di-update atau di-create
                    $dataToUpdateOrCreate = [
                        'attendance_status' => $finalStatus,
                        'notes' => $finalNotes,
                        'shift_id' => $shiftIdToSave, // Bisa null jika Cuti/Dinas/Libur/Alpha murni
                        // Pertahankan data asli jika ada dan bukan Alpha murni, atau set null jika statusnya Alpha/Libur/Cuti/Dinas
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
                        'is_corrected' => $attendance?->is_corrected ?? false, // Pertahankan status koreksi jika ada
                    ];

                    // Update timestamp pengiriman reminder koreksi jika reminder dikirim
                    if ($shouldSendCorrectionReminder && $user->email) {
                        $dataToUpdateOrCreate['last_correction_reminder_sent_at'] = now();
                    } elseif ($attendance && $attendance->last_correction_reminder_sent_at) {
                        // Pertahankan nilai lama jika sudah ada dan reminder tidak dikirim sekarang
                        $dataToUpdateOrCreate['last_correction_reminder_sent_at'] = $attendance->last_correction_reminder_sent_at;
                    } else {
                        // Set null jika tidak ada reminder sebelumnya dan tidak dikirim sekarang
                        $dataToUpdateOrCreate['last_correction_reminder_sent_at'] = null;
                    }

                    // Gunakan updateOrCreate untuk membuat record baru jika belum ada (misal untuk Alpha murni)
                    // atau memperbarui record yang sudah ada.
                    Attendance::updateOrCreate(
                        ['user_id' => $user->id, 'attendance_date' => $currentProcessDate->toDateString()],
                        $dataToUpdateOrCreate
                    );

                    // --- Kirim Email Reminder Koreksi SETELAH data disimpan (jika perlu) ---
                    if ($shouldSendCorrectionReminder && $user->email) {
                        // Ambil lagi record attendance yang baru diupdate untuk memastikan data terbaru dikirim ke Mailable
                        $updatedAttendanceForReminder = Attendance::where('user_id', $user->id)
                            ->where('attendance_date', $currentProcessDate->toDateString())
                            ->first();
                        if ($updatedAttendanceForReminder) {
                            try {
                                Mail::to($user->email)->queue(new AttendanceCorrectionReminderMail($updatedAttendanceForReminder, $user));
                                Log::info("    -> Reminder koreksi absensi telah DIANTRIKAN untuk {$user->email} pada tanggal {$updatedAttendanceForReminder->attendance_date->format('Y-m-d')}");
                            } catch (\Exception $mailError) {
                                Log::error("    -> GAGAL MENGANTRIKAN email reminder koreksi ke {$user->email} untuk tanggal {$currentProcessDate->format('Y-m-d')}: " . $mailError->getMessage());
                            }
                        }
                    }

                    DB::commit(); // Simpan semua perubahan jika tidak ada error
                    $totalProcessed++;
                } catch (\Exception $e) {
                    DB::rollBack(); // Batalkan semua perubahan jika terjadi error
                    $this->error("    -> Gagal memproses User ID: {$user->id} pada tanggal {$currentProcessDate->format('Y-m-d')}. Error: " . $e->getMessage());
                    Log::error("Error processing attendance for User ID {$user->id} on {$currentProcessDate->format('Y-m-d')}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                    $totalErrors++;
                }
            } // Akhir loop user
        } // Akhir loop tanggal

        $this->info("-----------------------------------------");
        $this->info("Proses status absensi harian selesai.");
        $this->info("Total Record Diproses/Dicek Ulang: {$totalProcessed}");
        $this->info("Total Error Terjadi              : {$totalErrors}");
        Log::info("ProcessDailyAttendance: Finished daily attendance processing for range {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}. Processed/Rechecked: {$totalProcessed}, Errors: {$totalErrors}.");

        return Command::SUCCESS; // Mengembalikan 0 jika sukses
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cuti;
use App\Models\Overtime;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\OverdueLeaveReminderMail; // Mailable untuk reminder cuti overdue
use App\Mail\OverdueOvertimeReminderMail; // Mailable untuk reminder lembur overdue
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Tidak digunakan secara langsung, tapi bisa relevan jika ada transaksi
use Illuminate\Database\Eloquent\Collection as EloquentCollection; // Untuk type-hinting

/**
 * Class SendOverdueReminders
 *
 * Command Artisan untuk mengirimkan email pengingat kepada approver (Asisten Manager dan Manager)
 * mengenai pengajuan Cuti dan Lembur yang telah melewati batas waktu tertentu (overdue)
 * dan belum diproses. Command ini juga mencatat kapan terakhir reminder dikirim
 * untuk menghindari pengiriman reminder yang terlalu sering.
 *
 * @package App\Console\Commands
 */
class SendOverdueReminders extends Command
{
    /**
     * Nama dan signature (tanda tangan) dari command Artisan.
     * Mendefinisikan bagaimana command ini dipanggil dari CLI.
     * Saat ini tidak memiliki opsi tambahan.
     *
     * @var string
     */
    protected $signature = 'reminders:send-overdue';

    /**
     * Deskripsi command Artisan.
     * Deskripsi ini akan muncul saat pengguna menjalankan 'php artisan list'.
     *
     * @var string
     */
    protected $description = 'Mengirim email pengingat untuk pengajuan Cuti dan Lembur yang overdue.';

    /**
     * Menjalankan logika utama dari command Artisan.
     * Method ini akan dipanggil ketika command `reminders:send-overdue` dieksekusi.
     *
     * @return int Kode status eksekusi (0 untuk sukses, selain itu untuk error).
     */
    public function handle()
    {
        // Menampilkan pesan awal di console dan log
        $this->info('Memulai pengecekan pengajuan overdue (Cuti & Lembur)...');
        Log::info('Scheduled Task ' . $this->signature . ' started.');

        $overdueDays = 3; // Batas hari dianggap overdue (misalnya, 3 hari kerja)
        $today = Carbon::today(config('app.timezone', 'Asia/Jakarta'));
        // Tanggal batas untuk created_at (untuk status 'pending') atau approved_at_asisten (untuk 'pending_manager_approval')
        $cutoffDate = $today->copy()->subDays($overdueDays); // Pengajuan sebelum tanggal ini dianggap overdue

        // 1. Mengambil data approver (Asisten Manager dan Manager)
        // Menggunakan keyBy('jabatan') untuk memudahkan pengambilan berdasarkan jabatan.
        $approvers = User::where('role', 'manajemen')
            ->whereIn('jabatan', ['asisten manager analis', 'asisten manager preparator', 'manager'])
            ->get()
            ->keyBy('jabatan');

        // Mengambil objek User untuk setiap peran approver
        $asistenAnalis = $approvers->get('asisten manager analis');
        $asistenPreparator = $approvers->get('asisten manager preparator');
        $manager = $approvers->get('manager');

        // Validasi apakah semua peran approver yang dibutuhkan ditemukan di database.
        // Jika salah satu tidak ada, proses dibatalkan untuk menghindari error lebih lanjut.
        $requiredJabatans = ['asisten manager analis', 'asisten manager preparator', 'manager'];
        $missingJabatans = [];
        foreach ($requiredJabatans as $jabatan) {
            if (!$approvers->has($jabatan)) {
                $missingJabatans[] = $jabatan;
            }
        }

        if (!empty($missingJabatans)) {
            $missing = implode(', ', $missingJabatans);
            $errorMessage = 'Gagal menemukan user untuk jabatan manajemen berikut: ' . $missing . '. Proses pengiriman reminder dibatalkan.';
            $this->error($errorMessage); // Menampilkan error di console
            Log::error('SendOverdueReminders: ' . $errorMessage);
            return 1; // Keluar dari command dengan status error
        }

        // --- Memproses Pengajuan Cuti yang Overdue ---
        $this->info('Memproses Cuti Overdue...');
        // Mengambil semua pengajuan cuti yang overdue
        $overdueCuti = $this->getOverdueRequests(Cuti::class, $cutoffDate, $overdueDays);
        // Mengelompokkan pengajuan cuti overdue berdasarkan email approver yang relevan
        $overdueCutiByApprover = $this->groupRequestsByApprover($overdueCuti, $asistenAnalis, $asistenPreparator, $manager);
        // Mengirim email reminder dan mendapatkan hasil pengiriman
        list($sentCuti, $errorCuti, $processedCutiIds) = $this->sendReminderEmails($overdueCutiByApprover, OverdueLeaveReminderMail::class);
        // Mengupdate timestamp 'last_reminder_sent_at' untuk cuti yang berhasil dikirim remindernya
        $this->updateLastReminderTimestamp(Cuti::class, $processedCutiIds);
        $this->info("Cuti: Email Reminder Terkirim = {$sentCuti}, Gagal Kirim = {$errorCuti}");

        // --- Memproses Pengajuan Lembur yang Overdue ---
        $this->info('Memproses Lembur Overdue...');
        // Mengambil semua pengajuan lembur yang overdue
        $overdueOvertime = $this->getOverdueRequests(Overtime::class, $cutoffDate, $overdueDays);
        // Mengelompokkan pengajuan lembur overdue berdasarkan email approver yang relevan
        $overdueOvertimeByApprover = $this->groupRequestsByApprover($overdueOvertime, $asistenAnalis, $asistenPreparator, $manager);
        // Mengirim email reminder dan mendapatkan hasil pengiriman
        list($sentOvertime, $errorOvertime, $processedOvertimeIds) = $this->sendReminderEmails($overdueOvertimeByApprover, OverdueOvertimeReminderMail::class);
        // Mengupdate timestamp 'last_reminder_sent_at' untuk lembur yang berhasil dikirim remindernya
        $this->updateLastReminderTimestamp(Overtime::class, $processedOvertimeIds);
        $this->info("Lembur: Email Reminder Terkirim = {$sentOvertime}, Gagal Kirim = {$errorOvertime}");

        // --- Ringkasan Total Proses ---
        $this->info("-----------------------------------------");
        $this->info("Proses pengiriman pengingat overdue selesai.");
        $this->info("Total Email Terkirim : " . ($sentCuti + $sentOvertime));
        $this->info("Total Gagal Kirim    : " . ($errorCuti + $errorOvertime));
        Log::info("Scheduled Task " . $this->signature . " finished. Cuti (Sent/Error): {$sentCuti}/{$errorCuti}. Overtime (Sent/Error): {$sentOvertime}/{$errorOvertime}.");

        return 0; // Mengembalikan 0 (Command::SUCCESS) jika command selesai
    }

    /**
     * Mengambil data pengajuan (Cuti atau Lembur) yang dianggap overdue.
     * Overdue ditentukan jika:
     * 1. Status 'pending', dan tanggal pengajuan (`created_at`) sudah melewati `cutoffDate`,
     * DAN belum pernah dikirim reminder ATAU reminder terakhir dikirim lebih lama dari `$overdueDays` yang lalu.
     * 2. Status 'pending_manager_approval', dan tanggal persetujuan asisten (`approved_at_asisten`)
     * sudah melewati `cutoffDate`, DAN belum pernah dikirim reminder ATAU reminder terakhir dikirim
     * lebih lama dari `$overdueDays` yang lalu.
     *
     * @param  string  $modelClass Nama class model ('App\Models\Cuti' atau 'App\Models\Overtime').
     * @param  \Carbon\Carbon  $cutoffDate Tanggal batas untuk menentukan overdue.
     * @param  int  $overdueDays Jumlah hari untuk interval pengiriman reminder berikutnya.
     * @return \Illuminate\Database\Eloquent\Collection Kumpulan objek pengajuan yang overdue.
     */
    protected function getOverdueRequests(string $modelClass, Carbon $cutoffDate, int $overdueDays): EloquentCollection
    {
        // Menyiapkan relasi yang akan di-eager load
        $relationsToLoad = ['user:id,name,jabatan']; // Selalu load user
        if ($modelClass === Cuti::class) {
            // Jika model adalah Cuti, load juga jenis cutinya
            $relationsToLoad[] = 'jenisCuti:id,nama_cuti';
        }

        // Query untuk mengambil pengajuan overdue
        return $modelClass::with($relationsToLoad)
            ->where(function ($query) use ($cutoffDate, $overdueDays) {
                // Kondisi untuk status 'pending' (menunggu approval Asisten Manager)
                $query->where('status', 'pending')
                    ->where('created_at', '<=', $cutoffDate) // Tanggal pengajuan sudah lewat batas
                    ->where(function ($q) use ($overdueDays) { // Kondisi untuk reminder
                        $q->whereNull('last_reminder_sent_at') // Belum pernah dikirim reminder
                            ->orWhere('last_reminder_sent_at', '<=', Carbon::today(config('app.timezone', 'Asia/Jakarta'))->subDays($overdueDays)); // Atau reminder terakhir sudah lama
                    });
            })
            ->orWhere(function ($query) use ($cutoffDate, $overdueDays) {
                // Kondisi untuk status 'pending_manager_approval' (menunggu approval Manager)
                $query->where('status', 'pending_manager_approval') // Atau 'pending_manager' tergantung konsistensi Anda
                    ->whereNotNull('approved_at_asisten') // Pastikan sudah diapprove Asisten
                    ->where('approved_at_asisten', '<=', $cutoffDate) // Tanggal approval Asisten sudah lewat batas
                    ->where(function ($q) use ($overdueDays) { // Kondisi untuk reminder
                        $q->whereNull('last_reminder_sent_at')
                            ->orWhere('last_reminder_sent_at', '<=', Carbon::today(config('app.timezone', 'Asia/Jakarta'))->subDays($overdueDays));
                    });
            })
            ->get();
    }


    /**
     * Mengelompokkan koleksi pengajuan (Cuti atau Lembur) berdasarkan email approver yang relevan.
     * Menentukan approver berdasarkan status pengajuan dan jabatan pengaju.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $requests Koleksi objek Cuti atau Overtime.
     * @param  \App\Models\User|null  $asistenAnalis Objek User untuk Asisten Manager Analis.
     * @param  \App\Models\User|null  $asistenPreparator Objek User untuk Asisten Manager Preparator.
     * @param  \App\Models\User|null  $manager Objek User untuk Manager.
     * @return array Array asosiatif dengan key email approver, dan value berisi objek approver dan daftar requestnya.
     * Format: ['email_approver' => ['approver_object' => User, 'requests' => [Cuti/Overtime, ...]], ...]
     */
    protected function groupRequestsByApprover(EloquentCollection $requests, ?User $asistenAnalis, ?User $asistenPreparator, ?User $manager): array
    {
        $grouped = []; // Array untuk hasil pengelompokan
        foreach ($requests as $request) {
            $approver = null; // Inisialisasi approver untuk request ini
            // Pastikan relasi 'user' (pengaju) sudah di-load untuk mendapatkan jabatannya
            $pengajuJabatan = $request->user?->jabatan ?? null;

            if (!$pengajuJabatan) {
                // Jika data pengaju atau jabatannya tidak ada, log peringatan dan lewati request ini
                Log::warning("SendOverdueReminders: Relasi user atau jabatan user tidak ditemukan untuk " . class_basename($request) . " ID {$request->id}. Pengajuan ini dilewati untuk reminder.");
                continue;
            }

            // Tentukan approver berdasarkan status pengajuan
            if ($request->status === 'pending') {
                // Jika status 'pending', approver adalah Asisten Manager sesuai scope jabatan pengaju
                if (in_array($pengajuJabatan, ['analis', 'admin'])) {
                    $approver = $asistenAnalis;
                } elseif (in_array($pengajuJabatan, ['preparator', 'mekanik'])) {
                    $approver = $asistenPreparator;
                }
                // Jika pengaju adalah 'admin' dan salah satu Asisten tidak terdefinisi,
                // bisa ditambahkan fallback ke Asisten lain jika ada.
                // Saat ini, jika $asistenAnalis atau $asistenPreparator null, $approver akan null.
            } elseif ($request->status === 'pending_manager_approval') { // Atau 'pending_manager'
                // Jika status 'pending_manager_approval', approver adalah Manager
                $approver = $manager;
            }

            // Jika approver berhasil ditentukan dan memiliki email, kelompokkan request
            if ($approver && !empty($approver->email)) {
                $grouped[$approver->email]['approver_object'] = $approver; // Simpan objek User approver
                $grouped[$approver->email]['requests'][] = $request;      // Tambahkan request ke daftar approver ini
            } else {
                // Log jika approver tidak bisa ditentukan atau tidak punya email
                Log::warning("SendOverdueReminders: Tidak dapat menentukan approver atau email approver untuk " . class_basename($request) . " ID {$request->id}. Status: {$request->status}, Jabatan Pengaju: {$pengajuJabatan}. Approver yang ditentukan: " . ($approver->name ?? 'Tidak Ada/Email Kosong'));
            }
        }
        return $grouped;
    }

    /**
     * Mengirim email pengingat ringkasan ke setiap approver untuk pengajuan yang dikelompokkan.
     *
     * @param  array  $groupedRequests Array pengajuan yang sudah dikelompokkan per email approver.
     * @param  string $mailableClass Nama class Mailable yang akan digunakan (misal, OverdueLeaveReminderMail::class).
     * @return array Mengembalikan array berisi: [jumlah email terkirim, jumlah email gagal kirim, array ID request yang diproses].
     */
    protected function sendReminderEmails(array $groupedRequests, string $mailableClass): array
    {
        $sentCount = 0;
        $errorCount = 0;
        $processedIds = []; // Untuk menyimpan ID dari request yang email remindernya berhasil diantrikan

        foreach ($groupedRequests as $email => $data) {
            /** @var \App\Models\User $approverUser Objek User approver. */
            $approverUser = $data['approver_object'];
            /** @var \Illuminate\Support\Collection $listOfRequests Koleksi objek Cuti atau Overtime. */
            $listOfRequests = collect($data['requests']); // Konversi array of requests menjadi Collection

            if ($listOfRequests->isEmpty()) {
                continue; // Lewati jika tidak ada request untuk approver ini
            }

            try {
                // Mengirim email menggunakan Mailable yang diberikan, dengan antrian (queue)
                Mail::to($email)->queue(new $mailableClass($listOfRequests, $approverUser));
                // Menampilkan output di console (jika dijalankan manual)
                $this->line(" - Mengirim email reminder ke: {$email} ({$approverUser->name}) untuk " . $listOfRequests->count() . " pengajuan " . class_basename($mailableClass) . ".");
                // Kumpulkan ID dari request yang berhasil dikirim remindernya
                $processedIds = array_merge($processedIds, $listOfRequests->pluck('id')->toArray());
                $sentCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("   Gagal mengirim email reminder ke: {$email}. Error: " . $e->getMessage());
                Log::error("SendOverdueReminders: Failed sending {$mailableClass} to {$email} for Approver ID {$approverUser->id}. Error: " . $e->getMessage());
            }
        }
        return [$sentCount, $errorCount, $processedIds];
    }

    /**
     * Memperbarui timestamp 'last_reminder_sent_at' pada record pengajuan
     * yang email remindernya telah berhasil dikirim/diantrikan.
     * Ini untuk mencegah pengiriman reminder yang terlalu sering untuk pengajuan yang sama.
     *
     * @param  string  $modelClass Nama class model ('App\Models\Cuti' atau 'App\Models\Overtime').
     * @param  array<int>  $processedIds Array berisi ID-ID pengajuan yang telah diproses.
     * @return void
     */
    protected function updateLastReminderTimestamp(string $modelClass, array $processedIds): void
    {
        if (!empty($processedIds)) {
            try {
                // Pastikan ID unik untuk menghindari masalah jika ada duplikasi (seharusnya tidak ada)
                $uniqueProcessedIds = array_unique($processedIds);
                // Update kolom 'last_reminder_sent_at' dengan waktu saat ini
                $updateCount = $modelClass::whereIn('id', $uniqueProcessedIds)
                    ->update(['last_reminder_sent_at' => now(config('app.timezone', 'Asia/Jakarta'))]);
                $this->line("   Berhasil mengupdate timestamp 'last_reminder_sent_at' untuk {$updateCount} record " . class_basename($modelClass) . ".");
                Log::info("SendOverdueReminders: Updated last_reminder_sent_at for {$updateCount} " . class_basename($modelClass) . " records. IDs: " . implode(',', $uniqueProcessedIds));
            } catch (\Exception $e) {
                $this->error("   Gagal mengupdate timestamp 'last_reminder_sent_at' untuk " . class_basename($modelClass) . ". Error: " . $e->getMessage());
                Log::error("SendOverdueReminders: Failed updating last_reminder_sent_at for " . class_basename($modelClass) . ". IDs: " . implode(',', $processedIds) . ". Error: " . $e->getMessage());
            }
        }
    }
}

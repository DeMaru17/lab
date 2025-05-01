<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cuti;
use App\Models\Overtime;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\OverdueLeaveReminderMail;
use App\Mail\OverdueOvertimeReminderMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class SendOverdueReminders extends Command
{
    /**
     * Signature & Description disesuaikan
     */
    protected $signature = 'reminders:send-overdue';
    protected $description = 'Mengirim email pengingat untuk pengajuan Cuti dan Lembur yang overdue.';

    public function handle()
    {
        // Gunakan $this->info() langsung
        $this->info('Memulai pengecekan pengajuan overdue (Cuti & Lembur)...');
        Log::info('Scheduled Task ' . $this->signature . ' started.');

        $overdueDays = 7;
        $today = Carbon::today();
        $cutoffDate = $today->copy()->subDays($overdueDays);

        // 1. Ambil data approver
        $approvers = User::where('role', 'manajemen')
            ->whereIn('jabatan', ['asisten manager analis', 'asisten manager preparator', 'manager'])
            ->get()
            ->keyBy('jabatan');

        $asistenAnalis = $approvers->get('asisten manager analis');
        $asistenPreparator = $approvers->get('asisten manager preparator');
        $manager = $approvers->get('manager');

        // Validasi apakah semua approver ditemukan (Logika Alternatif)
        $requiredJabatans = ['asisten manager analis', 'asisten manager preparator', 'manager'];
        $missingJabatans = [];
        foreach ($requiredJabatans as $jabatan) {
            if (!$approvers->has($jabatan)) {
                $missingJabatans[] = $jabatan;
            }
        }

        if (!empty($missingJabatans)) {
            $missing = implode(', ', $missingJabatans);
            $errorMessage = 'Gagal menemukan user untuk jabatan manajemen berikut: ' . $missing . '. Proses dibatalkan.';
            // Gunakan $this->error() langsung
            $this->error($errorMessage);
            Log::error('SendOverdueReminders: ' . $errorMessage);
            return 1; // Keluar dengan error
        }

        // --- Proses Cuti Overdue ---
        $this->info('Memproses Cuti Overdue...'); // Gunakan $this->info()
        $overdueCuti = $this->getOverdueRequests(Cuti::class, $cutoffDate, $overdueDays);
        $overdueCutiByApprover = $this->groupRequestsByApprover($overdueCuti, $asistenAnalis, $asistenPreparator, $manager);
        list($sentCuti, $errorCuti, $processedCutiIds) = $this->sendReminderEmails($overdueCutiByApprover, OverdueLeaveReminderMail::class);
        $this->updateLastReminderTimestamp(Cuti::class, $processedCutiIds);
        $this->info("Cuti: Email Terkirim={$sentCuti}, Gagal Kirim={$errorCuti}"); // Gunakan $this->info()

        // --- Proses Lembur Overdue ---
        $this->info('Memproses Lembur Overdue...'); // Gunakan $this->info()
        $overdueOvertime = $this->getOverdueRequests(Overtime::class, $cutoffDate, $overdueDays);
        $overdueOvertimeByApprover = $this->groupRequestsByApprover($overdueOvertime, $asistenAnalis, $asistenPreparator, $manager);
        list($sentOvertime, $errorOvertime, $processedOvertimeIds) = $this->sendReminderEmails($overdueOvertimeByApprover, OverdueOvertimeReminderMail::class);
        $this->updateLastReminderTimestamp(Overtime::class, $processedOvertimeIds);
        $this->info("Lembur: Email Terkirim={$sentOvertime}, Gagal Kirim={$errorOvertime}"); // Gunakan $this->info()

        // --- Ringkasan Total ---
        $this->info("-----------------------------------------"); // Gunakan $this->info()
        $this->info("Proses pengiriman pengingat selesai."); // Gunakan $this->info()
        $this->info("Total Email Terkirim : " . ($sentCuti + $sentOvertime)); // Gunakan $this->info()
        $this->info("Total Gagal Kirim    : " . ($errorCuti + $errorOvertime)); // Gunakan $this->info()
        Log::info("Scheduled Task " . $this->signature . " finished. Cuti (Sent/Err): {$sentCuti}/{$errorCuti}. Overtime (Sent/Err): {$sentOvertime}/{$errorOvertime}.");

        return 0;
    }

    /**
     * Mengambil data pengajuan yang overdue.
     * @param string $modelClass Nama class model (Cuti::class atau Overtime::class)
     * @param Carbon $cutoffDate Tanggal batas
     * @param int $overdueDays Jumlah hari overdue
     * @return EloquentCollection
     */
    protected function getOverdueRequests(string $modelClass, Carbon $cutoffDate, int $overdueDays): EloquentCollection
    {
        // Eager load relasi user (termasuk jabatan) dan jenis cuti jika modelnya Cuti
        $relationsToLoad = ['user:id,name,jabatan'];
        if ($modelClass === Cuti::class) {
            $relationsToLoad[] = 'jenisCuti:id,nama_cuti';
        }

        return $modelClass::with($relationsToLoad)
            ->where(function ($query) use ($cutoffDate, $overdueDays) {
                $query->where('status', 'pending')
                    ->where('created_at', '<=', $cutoffDate)
                    ->where(function ($q) use ($overdueDays) {
                        $q->whereNull('last_reminder_sent_at')
                            ->orWhere('last_reminder_sent_at', '<=', Carbon::today()->subDays($overdueDays));
                    });
            })
            ->orWhere(function ($query) use ($cutoffDate, $overdueDays) {
                $query->where('status', 'pending_manager_approval')
                    ->whereNotNull('approved_at_asisten')
                    ->where('approved_at_asisten', '<=', $cutoffDate)
                    ->where(function ($q) use ($overdueDays) {
                        $q->whereNull('last_reminder_sent_at')
                            ->orWhere('last_reminder_sent_at', '<=', Carbon::today()->subDays($overdueDays));
                    });
            })
            ->get();
    }


    /**
     * Mengelompokkan pengajuan berdasarkan email approver.
     * @param EloquentCollection $requests Koleksi Cuti atau Overtime
     * @param User|null $asistenAnalis
     * @param User|null $asistenPreparator
     * @param User|null $manager
     * @return array
     */
    protected function groupRequestsByApprover(EloquentCollection $requests, ?User $asistenAnalis, ?User $asistenPreparator, ?User $manager): array
    {
        $grouped = [];
        foreach ($requests as $request) {
            $approver = null;
            // Pastikan relasi user sudah di-load
            $pengajuJabatan = $request->user->jabatan ?? null;

            if (!$pengajuJabatan) {
                Log::warning("SendOverdueReminders: User relation or jabatan missing for " . class_basename($request) . " ID {$request->id}. Skipping.");
                continue; // Lewati jika data user/jabatan tidak ada
            }

            if ($request->status === 'pending') {
                if (in_array($pengajuJabatan, ['analis', 'admin'])) {
                    $approver = $asistenAnalis;
                } elseif (in_array($pengajuJabatan, ['preparator', 'mekanik'])) {
                    $approver = $asistenPreparator;
                } elseif ($pengajuJabatan === 'admin') {
                    $approver = $asistenAnalis ?? $asistenPreparator;
                } // Fallback jika salah satu null
            } elseif ($request->status === 'pending_manager_approval') {
                $approver = $manager;
            }

            if ($approver && !empty($approver->email)) {
                $grouped[$approver->email]['approver_object'] = $approver;
                $grouped[$approver->email]['requests'][] = $request;
            } else {
                Log::warning("SendOverdueReminders: Could not determine approver or email for " . class_basename($request) . " ID {$request->id}. Approver determined: " . ($approver->name ?? 'None'));
            }
        }
        return $grouped;
    }

    /**
     * Mengirim email ringkasan dan mengembalikan hasil.
     * @param array $groupedRequests Array pengajuan yg dikelompokkan per email approver
     * @param string $mailableClass Nama class Mailable yang akan digunakan
     * @return array [int sentCount, int errorCount, array processedIds]
     */
    protected function sendReminderEmails(array $groupedRequests, string $mailableClass): array
    {
        $sentCount = 0;
        $errorCount = 0;
        $processedIds = [];
        foreach ($groupedRequests as $email => $data) {
            $approverUser = $data['approver_object'];
            $listOfRequests = collect($data['requests']); // Jadikan collection
            try {
                Mail::to($email)->send(new $mailableClass($listOfRequests, $approverUser));
                // $this->line(" - Mengirim email ke: {$email} ({$approverUser->name}) untuk " . $listOfRequests->count() . " pengajuan."); // Gunakan $this->line()
                $processedIds = array_merge($processedIds, $listOfRequests->pluck('id')->toArray());
                $sentCount++;
            } catch (\Exception $e) {
                $errorCount++;
                // Gunakan $this->error()
                $this->error("   Gagal mengirim email ke: {$email}. Error: " . $e->getMessage());
                Log::error("SendOverdueReminders: Failed sending {$mailableClass} to {$email}. Error: " . $e->getMessage());
            }
        }
        return [$sentCount, $errorCount, $processedIds];
    }

    /**
     * Mengupdate timestamp last_reminder_sent_at.
     * @param string $modelClass Nama class model (Cuti::class atau Overtime::class)
     * @param array $processedIds Array ID yang berhasil diproses
     */
    protected function updateLastReminderTimestamp(string $modelClass, array $processedIds): void
    {
        if (!empty($processedIds)) {
            try {
                $uniqueProcessedIds = array_unique($processedIds);
                $updateCount = $modelClass::whereIn('id', $uniqueProcessedIds)
                    ->update(['last_reminder_sent_at' => now()]);
                // $this->line("   Berhasil mengupdate timestamp {$updateCount} record " . class_basename($modelClass) . "."); // Gunakan $this->line()
                Log::info("SendOverdueReminders: Updated last_reminder_sent_at for {$updateCount} " . class_basename($modelClass) . " records.");
            } catch (\Exception $e) {
                // Gunakan $this->error()
                $this->error("   Gagal mengupdate timestamp last_reminder_sent_at untuk " . class_basename($modelClass) . ". Error: " . $e->getMessage());
                Log::error("SendOverdueReminders: Failed updating last_reminder_sent_at for " . class_basename($modelClass) . ". Error: " . $e->getMessage());
            }
        }
    }
} // End Class SendOverdueReminders

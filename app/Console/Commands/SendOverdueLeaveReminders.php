<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cuti;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\OverdueLeaveReminderMail; 
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SendOverdueLeaveReminders extends Command
{
    /**
     * The name and signature of the console command.
     * Nama perintah Artisan.
     * @var string
     */
    protected $signature = 'leave:send-overdue-reminders';

    /**
     * The console command description.
     * Deskripsi perintah.
     * @var string
     */
    protected $description = 'Mengirim email pengingat ringkasan untuk pengajuan cuti yang overdue ke approver terkait.';

    /**
     * Execute the console command.
     * Menjalankan logika command.
     */
    public function handle()
    {
        $this->info('Memulai pengecekan pengajuan cuti overdue...');
        Log::info('Scheduled Task ' . $this->signature . ' started.');

        $overdueDays = 7; // Batas hari overdue
        $today = Carbon::today();
        $cutoffDate = $today->copy()->subDays($overdueDays);

        // 1. Ambil data approver (Asumsi hanya ada 1 per jabatan manajemen)
        $approvers = User::where('role', 'manajemen')
                         ->whereIn('jabatan', ['asisten manager analis', 'asisten manager preparator', 'manager'])
                         ->get()
                         ->keyBy('jabatan'); // Key berdasarkan jabatan

        $asistenAnalis = $approvers->get('asisten manager analis');
        $asistenPreparator = $approvers->get('asisten manager preparator');
        $manager = $approvers->get('manager');

        // Validasi apakah semua approver penting ditemukan
        if (!$asistenAnalis || !$asistenPreparator || !$manager) {
            $missing = collect(['asisten manager analis', 'asisten manager preparator', 'manager'])
                        ->filter(fn($jabatan) => !$approvers->has($jabatan))
                        ->implode(', ');
            $errorMessage = 'Gagal menemukan user untuk jabatan manajemen: ' . $missing . '. Proses dibatalkan.';
            $this->error($errorMessage);
            Log::error('SendOverdueLeaveReminders: ' . $errorMessage);
            return 1; // Keluar dengan error
        }

        // 2. Query Cuti Overdue
        $overdueCuti = Cuti::with(['user:id,name,jabatan', 'jenisCuti:id,nama_cuti'])
            ->where(function ($query) use ($cutoffDate, $overdueDays) {
                // Kondisi 1: Menunggu Asisten (Pending L1)
                $query->where('status', 'pending')
                      ->where('created_at', '<=', $cutoffDate) // Dibuat >= 7 hari lalu
                      ->where(function($q) use ($overdueDays) { // Cek interval reminder
                          $q->whereNull('last_reminder_sent_at')
                            ->orWhere('last_reminder_sent_at', '<=', Carbon::today()->subDays($overdueDays));
                      });
            })
            ->orWhere(function ($query) use ($cutoffDate, $overdueDays) {
                 // Kondisi 2: Menunggu Manager (Pending L2)
                 $query->where('status', 'pending_manager_approval')
                       ->whereNotNull('approved_at_asisten') // Pastikan L1 sudah approve
                       ->where('approved_at_asisten', '<=', $cutoffDate) // Approve L1 >= 7 hari lalu
                       ->where(function($q) use ($overdueDays) { // Cek interval reminder
                           $q->whereNull('last_reminder_sent_at')
                             ->orWhere('last_reminder_sent_at', '<=', Carbon::today()->subDays($overdueDays));
                       });
            })
            ->get();

        if ($overdueCuti->isEmpty()) {
            $this->info('Tidak ada pengajuan cuti overdue yang perlu diingatkan hari ini.');
            Log::info('SendOverdueLeaveReminders: No overdue leave requests found.');
            return 0; // Selesai tanpa ada yg diproses
        }

        // 3. Kelompokkan Cuti berdasarkan Email Approver
        $overdueByApproverEmail = [];
        foreach ($overdueCuti as $cuti) {
            $approver = null;
            $pengajuJabatan = $cuti->user->jabatan; // Ambil jabatan pengaju

            if ($cuti->status === 'pending') {
                // Tentukan Asisten Manager yang tepat
                if (in_array($pengajuJabatan, ['analis', 'admin'])) {
                    $approver = $asistenAnalis;
                } elseif (in_array($pengajuJabatan, ['preparator', 'mekanik'])) {
                    $approver = $asistenPreparator;
                }
                 // Jika admin bisa diapprove keduanya, logika perlu disesuaikan
                 // Saat ini, admin akan diarahkan ke Asisten Analis jika ada

            } elseif ($cuti->status === 'pending_manager_approval') {
                // Jika menunggu manager, targetnya adalah manager
                $approver = $manager;
            }

            // Jika approver ditemukan dan punya email, kelompokkan
            if ($approver && !empty($approver->email)) {
                $overdueByApproverEmail[$approver->email]['approver_object'] = $approver; // Simpan objek approver
                $overdueByApproverEmail[$approver->email]['requests'][] = $cuti; // Tambahkan cuti ke list
            } else {
                 Log::warning("SendOverdueLeaveReminders: Could not determine valid approver or email for Cuti ID {$cuti->id}. Approver determined: " . ($approver->name ?? 'None'));
            }
        }

        // 4. Kirim Email Ringkasan per Approver
        $sentCount = 0;
        $errorCount = 0;
        $processedCutiIds = []; // Kumpulkan ID cuti yg emailnya berhasil dikirim

        if (empty($overdueByApproverEmail)) {
             $this->warn('Tidak dapat menentukan approver yang valid untuk cuti overdue.');
             Log::warning('SendOverdueLeaveReminders: Could not determine valid approvers for found overdue leaves.');
             return 0;
        }

        foreach ($overdueByApproverEmail as $email => $data) {
            $approverUser = $data['approver_object']; // Ambil objek approver
            $listOfCuti = collect($data['requests']); // Jadikan collection

            try {
                // Kirim email menggunakan Mailable (akan dibuat)
                Mail::to($email)->send(new OverdueLeaveReminderMail($listOfCuti, $approverUser));
                $this->info("Mengirim email ringkasan ke: {$email} ({$approverUser->name}) untuk " . $listOfCuti->count() . " pengajuan.");

                // Kumpulkan ID cuti yang berhasil dikirim notifikasinya
                $processedCutiIds = array_merge($processedCutiIds, $listOfCuti->pluck('id')->toArray());
                $sentCount++;

            } catch (\Exception $e) {
                $errorCount++;
                $this->error("Gagal mengirim email ke: {$email}. Error: " . $e->getMessage());
                Log::error("SendOverdueLeaveReminders: Failed sending email to {$email}. Error: " . $e->getMessage());
            }
        }

        // 5. Update Timestamp Pengingat untuk Cuti yang Berhasil Dinotifikasi
        if (!empty($processedCutiIds)) {
            try {
                // Lakukan update massal
                $uniqueProcessedIds = array_unique($processedCutiIds); // Pastikan ID unik
                $updateCount = Cuti::whereIn('id', $uniqueProcessedIds)
                                   ->update(['last_reminder_sent_at' => now()]);
                $this->info("Berhasil mengupdate timestamp {$updateCount} record cuti.");
                Log::info("SendOverdueLeaveReminders: Updated last_reminder_sent_at for {$updateCount} records.");
            } catch (\Exception $e) {
                 $this->error("Gagal mengupdate timestamp last_reminder_sent_at. Error: " . $e->getMessage());
                 Log::error("SendOverdueLeaveReminders: Failed updating last_reminder_sent_at. Error: " . $e->getMessage());
            }
        }

        $this->info("-----------------------------------------");
        $this->info("Proses pengiriman pengingat selesai.");
        $this->info("Email Terkirim : {$sentCount}");
        $this->info("Gagal Kirim    : {$errorCount}");
        Log::info("Scheduled Task " . $this->signature . " finished. Sent: {$sentCount}, Errors: {$errorCount}.");

        return 0;
    }
}

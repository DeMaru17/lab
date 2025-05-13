<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\JenisCuti;
use App\Models\CutiQuota;
use Carbon\Carbon; // Pastikan Carbon diimport untuk manipulasi tanggal
use Illuminate\Support\Facades\Log; // Untuk logging proses dan error
use Illuminate\Support\Facades\DB; // Untuk transaksi database jika diperlukan pada operasi massal

/**
 * Class RefreshAllLeaveQuotas
 *
 * Command Artisan untuk me-refresh atau mengatur ulang kuota semua jenis cuti
 * untuk semua karyawan yang memenuhi syarat. Proses ini biasanya dijalankan secara periodik
 * (misalnya, pada awal tahun atau periode tertentu) untuk memastikan kuota cuti karyawan
 * selalu terbarui sesuai dengan kebijakan perusahaan dan masa kerja.
 * Untuk 'Cuti Tahunan', kuota hanya diberikan jika masa kerja karyawan sudah mencapai 12 bulan.
 * Untuk jenis cuti lain, kuota diatur berdasarkan durasi default yang terdaftar.
 *
 * @package App\Console\Commands
 */
class RefreshAllLeaveQuotas extends Command
{
    /**
     * Nama dan signature (tanda tangan) dari command Artisan.
     * Mendefinisikan bagaimana command ini dipanggil dari CLI.
     * Nama 'leave:refresh-all-quotas' mengindikasikan fungsinya untuk me-refresh semua kuota cuti.
     *
     * @var string
     */
    protected $signature = 'leave:refresh-all-quotas'; // Nama perintah artisan yang akan dijalankan

    /**
     * Deskripsi console command.
     * Deskripsi ini akan muncul saat pengguna menjalankan 'php artisan list'
     * atau 'php artisan help leave:refresh-all-quotas'.
     *
     * @var string
     */
    protected $description = 'Mereset atau memperbarui kuota semua jenis cuti untuk semua karyawan yang memenuhi syarat pada awal periode (misal: tahunan).';

    /**
     * Menjalankan logika utama dari command Artisan.
     * Method ini akan dipanggil ketika command `leave:refresh-all-quotas` dieksekusi,
     * biasanya melalui penjadwal tugas (scheduler) pada interval tertentu (misalnya, tahunan atau bulanan).
     *
     * @return int Kode status eksekusi (0 untuk sukses, selain itu untuk error).
     */
    public function handle()
    {
        $this->info('Memulai proses refresh semua kuota cuti...');
        Log::info('Scheduled Task leave:refresh-all-quotas started.');

        // 1. Mengambil semua jenis cuti yang terdaftar di database.
        // Jika tidak ada jenis cuti, proses tidak dapat dilanjutkan.
        $allJenisCuti = JenisCuti::all();
        if ($allJenisCuti->isEmpty()) {
            $this->error('Tidak ada data Jenis Cuti ditemukan di database. Proses refresh kuota dibatalkan.');
            Log::error('RefreshAllLeaveQuotas: No Jenis Cuti found in the database. Process terminated.');
            return 1; // Keluar dengan kode error
        }

        // 2. Mengambil semua pengguna yang relevan untuk diproses kuota cutinya.
        // Hanya pengguna yang memiliki 'tanggal_mulai_bekerja' yang akan diproses.
        // Anda bisa menambahkan filter lain jika perlu (misal, hanya user aktif).
        $allUsers = User::whereNotNull('tanggal_mulai_bekerja')->get();
        // Contoh jika ada kolom status aktif: ->where('status_aktif', true)->get();

        if ($allUsers->isEmpty()) {
            $this->warn('Tidak ada user yang memenuhi kriteria (memiliki tanggal mulai bekerja) untuk diproses kuotanya.');
            Log::warning('RefreshAllLeaveQuotas: No eligible users (with a start date) found. Process finished.');
            return 0; // Selesai tanpa ada yang diproses, dianggap sukses
        }

        $this->info("Ditemukan {$allUsers->count()} user dan {$allJenisCuti->count()} jenis cuti yang akan diproses.");
        $processedUserCount = 0; // Counter untuk pengguna yang berhasil diproses setidaknya satu jenis kuotanya
        $totalQuotaUpdates = 0; // Counter untuk total kuota yang berhasil di-update atau di-create
        $errorCount = 0;        // Counter untuk error yang terjadi

        $today = Carbon::today(config('app.timezone', 'Asia/Jakarta')); // Tanggal hari ini untuk referensi

        // 3. Loop untuk setiap pengguna
        foreach ($allUsers as $user) {
            $this->line("Memproses kuota untuk pengguna: {$user->id} - {$user->name}");
            $userHasProcessedQuota = false; // Flag apakah user ini memiliki kuota yang diupdate/dibuat

            // 4. Loop untuk setiap jenis cuti yang ada
            foreach ($allJenisCuti as $jenisCuti) {
                $targetQuota = 0; // Inisialisasi kuota target untuk jenis cuti ini

                // 5. Tentukan kuota default berdasarkan aturan spesifik untuk jenis cuti
                if (strtolower($jenisCuti->nama_cuti) === 'cuti tahunan') {
                    // Aturan Khusus untuk 'Cuti Tahunan':
                    // Cek masa kerja pengguna. Hanya diberikan jika sudah bekerja minimal 12 bulan.
                    // Menggunakan accessor 'lama_bekerja' dari model User (jika ada).
                    // Jika tidak ada accessor, hitung manual:
                    // $lamaBekerjaBulan = Carbon::parse($user->tanggal_mulai_bekerja)->diffInMonths($today);
                    if (isset($user->lama_bekerja) && $user->lama_bekerja >= 12) { // Asumsi ada accessor 'lama_bekerja' di model User
                        $targetQuota = $jenisCuti->durasi_default; // Ambil durasi default dari JenisCuti
                        $this->info("  -> User {$user->name} memenuhi syarat untuk Cuti Tahunan ({$targetQuota} hari). Masa kerja: {$user->lama_bekerja} bulan.");
                    } else {
                        // Jika belum 12 bulan, kuota Cuti Tahunan adalah 0 untuk periode ini.
                        // Command GrantAnnualLeaveQuota akan menangani pemberian saat tepat 1 tahun.
                        $targetQuota = 0;
                        $this->line("  -> User {$user->name} belum memenuhi syarat masa kerja untuk Cuti Tahunan. Kuota diatur ke 0.");
                    }
                } else {
                    // Untuk jenis cuti lain, langsung gunakan durasi default yang terdaftar.
                    $targetQuota = $jenisCuti->durasi_default;
                    $this->info("  -> Jenis cuti '{$jenisCuti->nama_cuti}', target kuota: {$targetQuota} hari.");
                }

                // 6. Update atau Buat entri kuota di tabel 'cuti_quota'.
                // Menggunakan updateOrCreate untuk membuat jika belum ada, atau update jika sudah ada.
                // Kunci pencarian adalah kombinasi user_id dan jenis_cuti_id.
                try {
                    // Opsi: Gunakan transaksi per update/create untuk keamanan data individual,
                    // namun bisa memperlambat jika jumlahnya sangat banyak.
                    // DB::transaction(function () use ($user, $jenisCuti, $targetQuota) {
                    CutiQuota::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'jenis_cuti_id' => $jenisCuti->id,
                        ],
                        [
                            'durasi_cuti' => $targetQuota // Set atau reset kuota sesuai hasil perhitungan
                        ]
                    );
                    // }); // Akhir opsi transaksi
                    $totalQuotaUpdates++;
                    $userHasProcessedQuota = true;
                } catch (\Exception $e) {
                    $errorCount++;
                    $errorMessage = "Gagal update/create kuota '{$jenisCuti->nama_cuti}' untuk User ID: {$user->id}. Error: " . $e->getMessage();
                    $this->error("    " . $errorMessage); // Tampilkan error di console
                    Log::error("RefreshAllLeaveQuotas: " . $errorMessage);
                    // Proses dilanjutkan ke jenis cuti berikutnya atau pengguna berikutnya meskipun ada error individual.
                }
            } // Akhir loop jenis cuti

            if ($userHasProcessedQuota) {
                $processedUserCount++;
            }
        } // Akhir loop pengguna

        // Menampilkan ringkasan hasil akhir proses di console dan mencatatnya di log
        $this->info("-----------------------------------------");
        $this->info("Proses refresh semua kuota cuti selesai.");
        $this->info("Total Pengguna Diproses   : {$processedUserCount} dari {$allUsers->count()}");
        $this->info("Total Kuota Diupdate/Dibuat: {$totalQuotaUpdates}");
        $this->info("Total Error Terjadi       : {$errorCount}");
        Log::info("Scheduled Task leave:refresh-all-quotas finished. Users Processed: {$processedUserCount}/{$allUsers->count()}, Quotas Updated/Created: {$totalQuotaUpdates}, Errors: {$errorCount}.");

        return 0; // Mengembalikan 0 (Command::SUCCESS) jika command selesai (meskipun mungkin ada error individual yang sudah di-log)
    }
}

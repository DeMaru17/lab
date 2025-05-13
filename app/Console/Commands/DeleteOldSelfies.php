<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Class DeleteOldSelfies
 *
 * Command Artisan untuk menghapus file foto selfie absensi (check-in dan check-out)
 * yang sudah melewati periode retensi yang ditentukan. Setelah file fisik dihapus,
 * path foto di database (tabel 'attendances') akan diupdate menjadi NULL.
 * Command ini mendukung opsi untuk override periode retensi, ukuran batch,
 * dan mode dry-run untuk simulasi tanpa melakukan perubahan aktual.
 *
 * @package App\Console\Commands
 */
class DeleteOldSelfies extends Command
{
    /**
     * Nama dan signature (tanda tangan) dari command Artisan.
     * Mendefinisikan bagaimana command ini dipanggil dari CLI dan opsi-opsi yang tersedia:
     * - `attendance:delete-old-selfies`: Nama unik untuk memanggil command.
     * - `{--months=}`: (Opsional) Opsi untuk menentukan periode retensi foto dalam bulan. Jika diberikan, akan menggantikan nilai dari file konfigurasi.
     * - `{--chunk=}`: (Opsional) Opsi untuk menentukan ukuran batch pemrosesan data. Jika diberikan, akan menggantikan nilai dari file konfigurasi.
     * - `{--dry-run}`: (Opsional) Jika flag ini ada, command akan berjalan dalam mode simulasi. Tidak ada file yang akan dihapus atau database yang akan diubah, hanya menampilkan aksi yang akan dilakukan.
     *
     * @var string
     */
    protected $signature = 'attendance:delete-old-selfies
                            {--months= : (Opsional) Override periode retensi dalam bulan dari file konfigurasi.}
                            {--chunk= : (Opsional) Override ukuran batch dari file konfigurasi.}
                            {--dry-run : Jalankan tanpa menghapus file atau mengubah DB, hanya tampilkan apa yang akan dihapus.}';

    /**
     * Deskripsi command Artisan.
     * Deskripsi ini akan muncul saat pengguna menjalankan 'php artisan list' atau 'php artisan help attendance:delete-old-selfies'.
     *
     * @var string
     */
    protected $description = 'Menghapus foto selfie absensi yang sudah lama secara otomatis dan membersihkan path di database.';

    /**
     * Menjalankan logika utama dari command Artisan.
     * Method ini akan dipanggil ketika command `attendance:delete-old-selfies` dieksekusi.
     *
     * @return int Kode status eksekusi (Command::SUCCESS untuk berhasil, Command::FAILURE atau nilai lain untuk error).
     */
    public function handle()
    {
        // Memeriksa apakah opsi --dry-run diberikan
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('--- MENJALANKAN DALAM MODE DRY-RUN ---');
            $this->info('Tidak ada file yang akan dihapus atau database yang akan diubah.');
        }

        // Mengambil periode retensi foto dari opsi command atau dari file konfigurasi.
        // Jika tidak ada di opsi atau config, default ke 2 bulan (sesuai kode Anda, namun di contoh sebelumnya saya sarankan 3).
        // Pastikan key 'hris.selfie_retention_period_months' ada di config/hris.php atau file config lain.
        $retentionMonths = $this->option('months') ?? config('hris.selfie_retention_period_months', 2); // Default 2 bulan sesuai kode Anda
        if (!is_numeric($retentionMonths) || $retentionMonths <= 0) {
            $this->error("Periode retensi tidak valid: {$retentionMonths}. Menggunakan default 2 bulan (sesuai kode) atau 3 bulan (jika config sebelumnya).");
            Log::error("DeleteOldSelfies: Invalid retention period '{$retentionMonths}' provided. Using default (2 atau 3).");
            // Anda mungkin ingin konsisten dengan default di config jika ada.
            // Jika kode Anda menggunakan default 2, maka biarkan 2. Jika config Anda 3, maka default ke 3.
            // Untuk contoh ini, saya ikuti kode Anda yang fallback ke 3 jika error.
            $retentionMonths = 3; // Fallback jika input tidak valid, sesuaikan jika perlu
        }

        // Mengambil ukuran batch pemrosesan dari opsi command atau dari file konfigurasi.
        // Default ke 200 jika tidak ada di opsi atau config.
        // Pastikan key 'hris.selfie_deletion_batch_size' ada di config.
        $chunkSize = $this->option('chunk') ?? config('hris.selfie_deletion_batch_size', 200);
         if (!is_numeric($chunkSize) || $chunkSize <= 0) {
            $this->error("Ukuran chunk tidak valid: {$chunkSize}. Menggunakan default 200.");
            Log::error("DeleteOldSelfies: Invalid chunk size '{$chunkSize}' provided. Using default.");
            $chunkSize = 200;
        }

        // Menghitung tanggal batas (cutoff date). Foto dengan attendance_date SEBELUM tanggal ini akan diproses.
        // Menggunakan startOfDay() untuk memastikan perbandingan yang konsisten.
        $cutoffDate = Carbon::now(config('app.timezone', 'Asia/Jakarta'))->subMonths((int)$retentionMonths)->startOfDay();

        $this->info("Memulai proses penghapusan selfie absensi yang lebih lama dari {$retentionMonths} bulan (sebelum {$cutoffDate->format('Y-m-d')}).");
        Log::info("DeleteOldSelfies: Task started. Retention: {$retentionMonths} months. Cutoff date: {$cutoffDate->format('Y-m-d')}. Chunk size: {$chunkSize}. Dry-run: " . ($isDryRun ? 'Yes' : 'No'));

        // Inisialisasi counter untuk ringkasan hasil
        $totalPhotosDeleted = 0;    // Jumlah file foto yang berhasil dihapus dari storage
        $totalRecordsUpdated = 0;   // Jumlah record di database yang path fotonya berhasil di-null-kan
        $totalErrors = 0;           // Jumlah error yang terjadi selama proses

        // Memproses data absensi dalam batch (chunks) untuk efisiensi memori,
        // terutama jika tabel 'attendances' sangat besar.
        // Query hanya mengambil record yang memiliki path foto (check-in atau check-out)
        // dan tanggal absensinya lebih lama dari cutoffDate.
        Attendance::query()
            ->where(function ($query) {
                // Kondisi: salah satu path foto tidak null
                $query->whereNotNull('clock_in_photo_path')
                      ->orWhereNotNull('clock_out_photo_path');
            })
            ->where('attendance_date', '<', $cutoffDate) // Kondisi tanggal
            ->orderBy('id') // Penting untuk chunkById agar konsisten
            ->chunkById($chunkSize, function ($attendances) use (&$totalPhotosDeleted, &$totalRecordsUpdated, &$totalErrors, $isDryRun) {
                // Callback function ini akan dieksekusi untuk setiap batch data absensi.
                $this->info("Memproses batch berisi " . $attendances->count() . " record absensi...");

                foreach ($attendances as $attendance) {
                    $pathsToNullify = []; // Array untuk menampung kolom path foto yang akan di-null-kan di DB

                    // --- Proses Foto Check-in ---
                    if ($attendance->clock_in_photo_path) { // Jika ada path foto check-in di DB
                        // Cek apakah file fisik foto ada di storage 'public'
                        if (Storage::disk('public')->exists($attendance->clock_in_photo_path)) {
                            if (!$isDryRun) { // Hanya hapus jika bukan mode dry-run
                                try {
                                    Storage::disk('public')->delete($attendance->clock_in_photo_path); // Hapus file fisik
                                    $pathsToNullify['clock_in_photo_path'] = null; // Tandai untuk di-null-kan di DB
                                    $totalPhotosDeleted++;
                                    $this->line("  [DELETED] Selfie check-in: {$attendance->clock_in_photo_path} (Attendance ID: {$attendance->id})");
                                    Log::info("DeleteOldSelfies: Deleted clock_in_photo_path '{$attendance->clock_in_photo_path}' for Attendance ID {$attendance->id}.");
                                } catch (\Exception $e) {
                                    $totalErrors++;
                                    $this->error("  [ERROR] Gagal menghapus file check-in: {$attendance->clock_in_photo_path}. Error: " . $e->getMessage());
                                    Log::error("DeleteOldSelfies: Failed to delete file '{$attendance->clock_in_photo_path}' for Attendance ID {$attendance->id}. Error: " . $e->getMessage());
                                }
                            } else {
                                // Jika dry-run, hanya tampilkan pesan tanpa menghapus
                                $this->line("  [DRY-RUN] Akan menghapus selfie check-in: {$attendance->clock_in_photo_path} (Attendance ID: {$attendance->id})");
                                $totalPhotosDeleted++; // Tetap hitung untuk estimasi dry-run
                            }
                        } else {
                            // Jika file tidak ditemukan di storage, tapi path masih ada di DB,
                            // tetap tandai path tersebut untuk di-null-kan di DB untuk membersihkan data.
                            $pathsToNullify['clock_in_photo_path'] = null;
                            $this->warn("  [NOT FOUND] File check-in tidak ditemukan di storage, path akan di-null-kan: {$attendance->clock_in_photo_path} (Attendance ID: {$attendance->id})");
                            Log::warning("DeleteOldSelfies: Clock-in photo '{$attendance->clock_in_photo_path}' not found in storage for Attendance ID {$attendance->id}, path will be nullified.");
                        }
                    }

                    // --- Proses Foto Check-out (logika serupa dengan check-in) ---
                    if ($attendance->clock_out_photo_path) {
                        if (Storage::disk('public')->exists($attendance->clock_out_photo_path)) {
                            if (!$isDryRun) {
                                try {
                                    Storage::disk('public')->delete($attendance->clock_out_photo_path);
                                    $pathsToNullify['clock_out_photo_path'] = null;
                                    $totalPhotosDeleted++;
                                    $this->line("  [DELETED] Selfie check-out: {$attendance->clock_out_photo_path} (Attendance ID: {$attendance->id})");
                                    Log::info("DeleteOldSelfies: Deleted clock_out_photo_path '{$attendance->clock_out_photo_path}' for Attendance ID {$attendance->id}.");
                                } catch (\Exception $e) {
                                    $totalErrors++;
                                    $this->error("  [ERROR] Gagal menghapus file check-out: {$attendance->clock_out_photo_path}. Error: " . $e->getMessage());
                                    Log::error("DeleteOldSelfies: Failed to delete file '{$attendance->clock_out_photo_path}' for Attendance ID {$attendance->id}. Error: " . $e->getMessage());
                                }
                            } else {
                                $this->line("  [DRY-RUN] Akan menghapus selfie check-out: {$attendance->clock_out_photo_path} (Attendance ID: {$attendance->id})");
                                $totalPhotosDeleted++; // Tetap hitung untuk estimasi dry-run
                            }
                        } else {
                            $pathsToNullify['clock_out_photo_path'] = null;
                            $this->warn("  [NOT FOUND] File check-out tidak ditemukan di storage, path akan di-null-kan: {$attendance->clock_out_photo_path} (Attendance ID: {$attendance->id})");
                            Log::warning("DeleteOldSelfies: Clock-out photo '{$attendance->clock_out_photo_path}' not found in storage for Attendance ID {$attendance->id}, path will be nullified.");
                        }
                    }

                    // --- Update Record Database ---
                    // Hanya update jika ada path yang perlu di-null-kan dan bukan mode dry-run.
                    if (!empty($pathsToNullify) && !$isDryRun) {
                        try {
                            // Menggunakan DB::table() untuk update langsung ke database.
                            // Ini lebih cepat untuk operasi batch karena tidak memicu event model Eloquent.
                            // Jika Anda memerlukan event model (misalnya, untuk update 'updated_at' secara otomatis
                            // atau ada observer), gunakan: $attendance->update($pathsToNullify);
                            DB::table('attendances')->where('id', $attendance->id)->update($pathsToNullify);
                            $totalRecordsUpdated++;
                            // $recordUpdatedThisIteration = true; // Tidak digunakan di kode Anda
                            Log::info("DeleteOldSelfies: Nullified photo paths for Attendance ID {$attendance->id}. Paths: " . json_encode(array_keys($pathsToNullify)));
                        } catch (\Exception $e) {
                            $totalErrors++;
                            $this->error("  [ERROR DB] Gagal update path di DB untuk Attendance ID: {$attendance->id}. Error: " . $e->getMessage());
                            Log::error("DeleteOldSelfies: Failed to update DB for Attendance ID {$attendance->id}. Error: " . $e->getMessage());
                        }
                    }
                    // Jika dry-run dan ada path yang akan di-null-kan, tampilkan pesan
                    if ($isDryRun && !empty($pathsToNullify)) {
                         $this->line("  [DRY-RUN] Akan mengupdate DB untuk Attendance ID {$attendance->id} dengan path: " . json_encode($pathsToNullify));
                         // $totalRecordsUpdated tidak di-increment pada dry-run untuk update DB,
                         // karena $totalPhotosDeleted sudah mencakup estimasi file yang akan dihapus (yang path-nya juga akan di-null-kan).
                    }
                } // Akhir loop foreach $attendances
            }); // Akhir chunkById

        // Menampilkan ringkasan hasil akhir proses di console dan mencatatnya di log
        $this->info("-----------------------------------------");
        if ($isDryRun) {
            // Pesan khusus untuk mode dry-run
            $this->info("Mode DRY-RUN Selesai. Total foto yang AKAN dihapus (jika dijalankan aktual): {$totalPhotosDeleted}.");
            // Catatan: $totalRecordsUpdated pada dry-run akan 0 karena DB tidak diubah.
            // Pesan di atas lebih fokus pada estimasi file yang akan dihapus.
        } else {
            // Pesan untuk eksekusi aktual
            $this->info("Proses penghapusan selfie selesai.");
            $this->info("Total Foto Selfie Dihapus : {$totalPhotosDeleted}");
            $this->info("Total Record DB Diupdate  : {$totalRecordsUpdated}"); // Jumlah record DB yang path fotonya di-null-kan
            $this->info("Total Error Terjadi       : {$totalErrors}");
        }
        Log::info("DeleteOldSelfies: Task finished. Photos deleted: {$totalPhotosDeleted}. Records updated: {$totalRecordsUpdated}. Errors: {$totalErrors}.");

        return Command::SUCCESS; // Mengembalikan 0 jika command selesai (dianggap sukses secara umum)
    }
}

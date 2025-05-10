<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DeleteOldSelfies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:delete-old-selfies
                            {--months= : (Opsional) Override periode retensi dalam bulan dari file konfigurasi.}
                            {--chunk= : (Opsional) Override ukuran batch dari file konfigurasi.}
                            {--dry-run : Jalankan tanpa menghapus file atau mengubah DB, hanya tampilkan apa yang akan dihapus.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menghapus foto selfie absensi yang sudah lama secara otomatis dan membersihkan path di database.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('--- MENJALANKAN DALAM MODE DRY-RUN ---');
            $this->info('Tidak ada file yang akan dihapus atau database yang akan diubah.');
        }

        // Ambil periode retensi dari opsi command atau config
        $retentionMonths = $this->option('months') ?? config('hris.selfie_retention_period_months', 2);
        if (!is_numeric($retentionMonths) || $retentionMonths <= 0) {
            $this->error("Periode retensi tidak valid: {$retentionMonths}. Menggunakan default 2 bulan.");
            Log::error("DeleteOldSelfies: Invalid retention period '{$retentionMonths}' provided. Using default.");
            $retentionMonths = 3;
        }

        // Ambil ukuran chunk dari opsi command atau config
        $chunkSize = $this->option('chunk') ?? config('hris.selfie_deletion_batch_size', 200);
         if (!is_numeric($chunkSize) || $chunkSize <= 0) {
            $this->error("Ukuran chunk tidak valid: {$chunkSize}. Menggunakan default 200.");
            Log::error("DeleteOldSelfies: Invalid chunk size '{$chunkSize}' provided. Using default.");
            $chunkSize = 200;
        }

        $cutoffDate = Carbon::now()->subMonths((int)$retentionMonths)->startOfDay(); // Awal hari dari tanggal batas

        $this->info("Memulai proses penghapusan selfie absensi yang lebih lama dari {$retentionMonths} bulan (sebelum {$cutoffDate->format('Y-m-d')}).");
        Log::info("DeleteOldSelfies: Task started. Retention: {$retentionMonths} months. Cutoff date: {$cutoffDate->format('Y-m-d')}. Chunk size: {$chunkSize}. Dry-run: " . ($isDryRun ? 'Yes' : 'No'));

        $totalPhotosDeleted = 0;
        $totalRecordsUpdated = 0;
        $totalErrors = 0;

        // Gunakan chunkById untuk memproses dalam batch yang lebih kecil agar tidak membebani memori
        // Hanya proses record yang memiliki path foto dan lebih lama dari cutoffDate
        Attendance::query()
            ->where(function ($query) {
                $query->whereNotNull('clock_in_photo_path')
                      ->orWhereNotNull('clock_out_photo_path');
            })
            ->where('attendance_date', '<', $cutoffDate)
            ->orderBy('id') // Penting untuk chunkById
            ->chunkById($chunkSize, function ($attendances) use (&$totalPhotosDeleted, &$totalRecordsUpdated, &$totalErrors, $isDryRun) {
                $this->info("Memproses batch berisi " . $attendances->count() . " record absensi...");

                foreach ($attendances as $attendance) {
                    $pathsToNullify = [];
                    $recordUpdatedThisIteration = false;

                    // Proses foto check-in
                    if ($attendance->clock_in_photo_path) {
                        if (Storage::disk('public')->exists($attendance->clock_in_photo_path)) {
                            if (!$isDryRun) {
                                try {
                                    Storage::disk('public')->delete($attendance->clock_in_photo_path);
                                    $pathsToNullify['clock_in_photo_path'] = null;
                                    $totalPhotosDeleted++;
                                    $this->line("  [DELETED] Selfie check-in: {$attendance->clock_in_photo_path} (Attendance ID: {$attendance->id})");
                                    Log::info("DeleteOldSelfies: Deleted clock_in_photo_path '{$attendance->clock_in_photo_path}' for Attendance ID {$attendance->id}.");
                                } catch (\Exception $e) {
                                    $totalErrors++;
                                    $this->error("  [ERROR] Gagal menghapus file check-in: {$attendance->clock_in_photo_path}. Error: " . $e->getMessage());
                                    Log::error("DeleteOldSelfies: Failed to delete file '{$attendance->clock_in_photo_path}' for Attendance ID {$attendance->id}. Error: " . $e->getMessage());
                                }
                            } else {
                                $this->line("  [DRY-RUN] Akan menghapus selfie check-in: {$attendance->clock_in_photo_path} (Attendance ID: {$attendance->id})");
                            }
                        } else {
                            // File tidak ada di storage, tapi path ada di DB. Tetap null-kan path.
                            $pathsToNullify['clock_in_photo_path'] = null;
                            $this->warn("  [NOT FOUND] File check-in tidak ditemukan di storage, path akan di-null-kan: {$attendance->clock_in_photo_path} (Attendance ID: {$attendance->id})");
                            Log::warning("DeleteOldSelfies: Clock-in photo '{$attendance->clock_in_photo_path}' not found in storage for Attendance ID {$attendance->id}, path will be nullified.");
                        }
                    }

                    // Proses foto check-out
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
                            }
                        } else {
                            $pathsToNullify['clock_out_photo_path'] = null;
                            $this->warn("  [NOT FOUND] File check-out tidak ditemukan di storage, path akan di-null-kan: {$attendance->clock_out_photo_path} (Attendance ID: {$attendance->id})");
                            Log::warning("DeleteOldSelfies: Clock-out photo '{$attendance->clock_out_photo_path}' not found in storage for Attendance ID {$attendance->id}, path will be nullified.");
                        }
                    }

                    // Update record database jika ada path yang diubah
                    if (!empty($pathsToNullify) && !$isDryRun) {
                        try {
                            // Gunakan DB::table untuk update cepat tanpa trigger model event jika tidak perlu
                            DB::table('attendances')->where('id', $attendance->id)->update($pathsToNullify);
                            // Atau jika ingin trigger model event (misal updated_at otomatis):
                            // $attendance->update($pathsToNullify);
                            $totalRecordsUpdated++;
                            $recordUpdatedThisIteration = true;
                            Log::info("DeleteOldSelfies: Nullified photo paths for Attendance ID {$attendance->id}. Paths: " . json_encode(array_keys($pathsToNullify)));
                        } catch (\Exception $e) {
                            $totalErrors++;
                            $this->error("  [ERROR DB] Gagal update path di DB untuk Attendance ID: {$attendance->id}. Error: " . $e->getMessage());
                            Log::error("DeleteOldSelfies: Failed to update DB for Attendance ID {$attendance->id}. Error: " . $e->getMessage());
                        }
                    }
                    if ($isDryRun && !empty($pathsToNullify)) {
                         $this->line("  [DRY-RUN] Akan mengupdate DB untuk Attendance ID {$attendance->id} dengan path: " . json_encode($pathsToNullify));
                         // $totalRecordsUpdated++; // Jangan increment di dry-run untuk update DB
                    }
                }
            });

        $this->info("-----------------------------------------");
        if ($isDryRun) {
            $this->info("Mode DRY-RUN Selesai. Total foto yang AKAN dihapus: {$totalPhotosDeleted}. Total record DB yang AKAN diupdate: (dihitung berdasarkan file yang ada/tidak ada).");
        } else {
            $this->info("Proses penghapusan selfie selesai.");
            $this->info("Total Foto Selfie Dihapus : {$totalPhotosDeleted}");
            $this->info("Total Record DB Diupdate  : {$totalRecordsUpdated}");
            $this->info("Total Error Terjadi       : {$totalErrors}");
        }
        Log::info("DeleteOldSelfies: Task finished. Photos deleted: {$totalPhotosDeleted}. Records updated: {$totalRecordsUpdated}. Errors: {$totalErrors}.");

        return Command::SUCCESS;
    }
}

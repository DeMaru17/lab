<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\User;
use App\Models\Shift;
use App\Models\Overtime; // <-- Import jika cek overtime
use Carbon\Carbon;
use Illuminate\Database\QueryException; // <-- Import untuk catch error DB

class AttendanceSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Memulai seeding data absensi terkontrol...');

        $users = User::whereIn('role', ['personil', 'admin'])->get();
        $shifts = Shift::all();
        $shiftPagi = $shifts->firstWhere('name', 'Shift 1') ?? $shifts->first();

        if ($users->isEmpty() || $shifts->isEmpty()) {
            $this->command->warn('Tidak dapat menjalankan AttendanceSeeder: User atau Shift tidak ditemukan.');
            return;
        }

        // Hapus data lama jika Anda ingin memulai dari bersih setiap seed
        // Attendance::query()->delete();

        $endDate = Carbon::yesterday();
        $startDate = $endDate->copy()->subDays(14); // Seed untuk 15 hari terakhir
        $dateRange = Carbon::parse($startDate)->toPeriod($endDate);
        $createdCount = 0;
        $skippedCount = 0;

        foreach ($dateRange as $date) {
            foreach ($users as $user) {
                // Tentukan skenario untuk user dan tanggal ini (contoh: pakai sisa bagi ID user)
                $scenarioType = $user->id % 7; // Beri 7 skenario berbeda

                // Pilih shift (bisa dibuat lebih bervariasi jika perlu)
                $assignedShift = $shiftPagi;
                if (!$assignedShift) continue;

                // --- Logika Penentuan Skenario ---
                $factoryState = []; // Default state kosong

                // Jangan buat record jika skenario Alpha
                if ($scenarioType == 4 && !$date->isWeekend()) {
                    continue; // Lewati, command akan handle Alpha
                }

                // Jangan buat record jika skenario Cuti/Dinas (Pastikan data Cuti/Dinas ada)
                if ($scenarioType == 5 || $scenarioType == 6) {
                     // TODO: Pastikan ada data Cuti/Dinas untuk user/tanggal ini di seeder lain
                    continue; // Lewati, command akan handle Cuti/Dinas
                }

                // Tentukan state Factory berdasarkan skenario
                if ($scenarioType == 1 && !$date->isWeekend()) { // Terlambat
                    $factoryState = ['state' => 'late'];
                } elseif ($scenarioType == 2 && !$date->isWeekend()) { // Pulang Cepat
                    $factoryState = ['state' => 'earlyLeave'];
                } elseif ($scenarioType == 3 && !$date->isWeekend()) { // Tidak Lengkap
                    $factoryState = ['state' => 'incomplete'];
                } elseif (($scenarioType == 0) && $date->isWeekend()) { // Potensi Lembur Weekend
                    // Cek dulu apakah ada Overtime approved? (PENTING)
                    $hasOvertime = Overtime::where('user_id', $user->id)
                                          ->where('tanggal_lembur', $date->toDateString())
                                          ->where('status', 'approved')
                                          ->exists();
                    if ($hasOvertime) {
                        $factoryState = ['state' => 'weekendOvertime'];
                    } else {
                        continue; // Tidak ada OT, berarti Libur, command akan handle
                    }
                }
                // Jika $scenarioType == 0 dan BUKAN weekend, akan pakai state default (Hadir)

                // --- Membuat Record dengan Factory (Gagal jika sudah ada) ---
                try {
                    $factory = Attendance::factory()
                                ->state([
                                    'user_id' => $user->id, // Override user_id
                                    'attendance_date' => $date->toDateString(), // Override tanggal
                                    'shift_id' => $assignedShift->id,
                                ]);

                    // Terapkan state spesifik jika ada
                    if (isset($factoryState['state'])) {
                        $stateMethod = $factoryState['state'];
                        $factory->$stateMethod(); // Panggil method state (misal: late())
                    }

                    // Buat record
                    $factory->create();
                    $createdCount++;

                } catch (QueryException $e) {
                    // Tangani error jika mencoba insert duplikat (Kode error 1062)
                    if ($e->errorInfo[1] == 1062) {
                        // $this->command->warn("  - Skipped duplicate entry for User:{$user->id} Date:{$date->toDateString()}");
                        $skippedCount++;
                    } else {
                        // Tampilkan error lain jika bukan duplikat
                        $this->command->error("  - DB Error User:{$user->id} Date:{$date->toDateString()}: " . $e->getMessage());
                        // throw $e; // Atau lempar lagi jika ingin menghentikan seeder
                    }
                } catch (\Exception $e) {
                     $this->command->error("  - General Error User:{$user->id} Date:{$date->toDateString()}: " . $e->getMessage());
                     // throw $e;
                }
            } // end foreach user
        } // end foreach date

        $this->command->info("Seeding data absensi selesai. Dibuat: {$createdCount}, Dilewati (Duplikat): {$skippedCount}");
    }
}

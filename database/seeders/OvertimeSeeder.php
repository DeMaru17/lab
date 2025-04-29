<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Overtime;
use Carbon\Carbon; // Import Carbon
use Illuminate\Support\Facades\DB; // Untuk menghapus data lama jika perlu


class OvertimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Mengisi tabel overtimes dengan data contoh.
     */
    public function run(): void
    {
        $this->command->info('Memulai Overtime Seeder...');

        // Hapus data lembur lama (opsional, hati-hati jika data produksi)
        // DB::table('overtimes')->delete();

        // --- Cari User berdasarkan Jabatan ---
        $adminUser = User::where('jabatan', 'admin')->first();
        $analisUser = User::where('jabatan', 'analis')->first();
        $preparatorUser = User::where('jabatan', 'preparator')->first();
        $mekanikUser = User::where('jabatan', 'mekanik')->first();

        $asistenAnalis = User::where('jabatan', 'asisten manager analis')->first();
        $asistenPreparator = User::where('jabatan', 'asisten manager preparator')->first();
        $manager = User::where('jabatan', 'manager')->first();

        // Peringatan jika user penting tidak ditemukan
        if (!$asistenAnalis) $this->command->warn('User "asisten manager analis" tidak ditemukan.');
        if (!$asistenPreparator) $this->command->warn('User "asisten manager preparator" tidak ditemukan.');
        if (!$manager) $this->command->warn('User "manager" tidak ditemukan.');
        if (!$adminUser && !$analisUser && !$preparatorUser && !$mekanikUser) {
            $this->command->error('Tidak ditemukan user personil/admin untuk membuat data lembur. Seeder dibatalkan.');
            return;
        }

        $now = Carbon::now();
        $overtimesData = [];

        // --- Contoh Data Pending untuk Asisten Analis ---
        if ($adminUser) {
            $overtimesData[] = [
                'user_id' => $adminUser->id,
                'tanggal_lembur' => $now->copy()->subDays(2)->toDateString(),
                'jam_mulai' => '17:00:00',
                'jam_selesai' => '19:00:00',
                'uraian_pekerjaan' => 'Menyelesaikan laporan bulanan admin.',
                'status' => 'pending',
                'created_at' => $now->copy()->subDays(8), // Buat > 7 hari lalu
                'updated_at' => $now->copy()->subDays(8),
            ];
        }
        if ($analisUser) {
            $overtimesData[] = [
                'user_id' => $analisUser->id,
                'tanggal_lembur' => $now->copy()->subDays(3)->toDateString(),
                'jam_mulai' => '16:45:00',
                'jam_selesai' => '20:15:00',
                'uraian_pekerjaan' => 'Analisa sampel urgent batch XYZ.',
                'status' => 'pending',
                'created_at' => $now->copy()->subDays(9), // Buat > 7 hari lalu
                'updated_at' => $now->copy()->subDays(9),
            ];
            $overtimesData[] = [
                'user_id' => $analisUser->id,
                'tanggal_lembur' => $now->copy()->subDays(1)->toDateString(), // Belum overdue
                'jam_mulai' => '17:30:00',
                'jam_selesai' => '18:30:00',
                'uraian_pekerjaan' => 'Kalibrasi alat spektrometer.',
                'status' => 'pending',
                'created_at' => $now->copy()->subDays(1),
                'updated_at' => $now->copy()->subDays(1),
            ];
        }

        // --- Contoh Data Pending untuk Asisten Preparator ---
        if ($preparatorUser) {
            $overtimesData[] = [
                'user_id' => $preparatorUser->id,
                'tanggal_lembur' => $now->copy()->subDays(4)->toDateString(),
                'jam_mulai' => '17:00:00',
                'jam_selesai' => '21:00:00',
                'uraian_pekerjaan' => 'Preparasi sampel untuk analisa besok.',
                'status' => 'pending',
                'created_at' => $now->copy()->subDays(10), // Buat > 7 hari lalu
                'updated_at' => $now->copy()->subDays(10),
            ];
        }
        if ($mekanikUser) {
            $overtimesData[] = [
                'user_id' => $mekanikUser->id,
                'tanggal_lembur' => $now->copy()->subDays(5)->toDateString(),
                'jam_mulai' => '18:00:00',
                'jam_selesai' => '19:30:00',
                'uraian_pekerjaan' => 'Perbaikan mesin crusher.',
                'status' => 'pending',
                'created_at' => $now->copy()->subDays(11), // Buat > 7 hari lalu
                'updated_at' => $now->copy()->subDays(11),
            ];
        }

        // --- Contoh Data Pending untuk Manager (Sudah diapprove Asisten) ---
        if ($analisUser && $asistenAnalis) {
            $overtimesData[] = [
                'user_id' => $analisUser->id,
                'tanggal_lembur' => $now->copy()->subDays(10)->toDateString(),
                'jam_mulai' => '08:00:00', // Contoh lembur weekend
                'jam_selesai' => '12:00:00',
                'uraian_pekerjaan' => 'Lembur weekend analisa khusus.',
                'status' => 'pending_manager_approval', // Status menunggu manager
                'approved_by_asisten_id' => $asistenAnalis->id,
                'approved_at_asisten' => $now->copy()->subDays(8), // Approve L1 > 7 hari lalu
                'created_at' => $now->copy()->subDays(15),
                'updated_at' => $now->copy()->subDays(8), // Updated saat approve L1
            ];
        }
        if ($preparatorUser && $asistenPreparator) {
            $overtimesData[] = [
                'user_id' => $preparatorUser->id,
                'tanggal_lembur' => $now->copy()->subDays(12)->toDateString(),
                'jam_mulai' => '19:00:00',
                'jam_selesai' => '22:00:00',
                'uraian_pekerjaan' => 'Lembur malam persiapan sampel prioritas.',
                'status' => 'pending_manager_approval',
                'approved_by_asisten_id' => $asistenPreparator->id,
                'approved_at_asisten' => $now->copy()->subDays(9), // Approve L1 > 7 hari lalu
                'created_at' => $now->copy()->subDays(16),
                'updated_at' => $now->copy()->subDays(9),
            ];
            $overtimesData[] = [
                'user_id' => $preparatorUser->id,
                'tanggal_lembur' => $now->copy()->subDays(2)->toDateString(), // Belum overdue L2
                'jam_mulai' => '17:00:00',
                'jam_selesai' => '18:00:00',
                'uraian_pekerjaan' => 'Menyelesaikan sisa preparasi.',
                'status' => 'pending_manager_approval',
                'approved_by_asisten_id' => $asistenPreparator->id,
                'approved_at_asisten' => $now->copy()->subDays(1), // Approve L1 baru kemarin
                'created_at' => $now->copy()->subDays(5),
                'updated_at' => $now->copy()->subDays(1),
            ];
        }

        // --- Contoh Data Lain (Approved, Rejected, Cancelled) ---
        if ($adminUser && $asistenAnalis && $manager) {
            $overtimesData[] = [
                'user_id' => $adminUser->id,
                'tanggal_lembur' => $now->copy()->subDays(20)->toDateString(),
                'jam_mulai' => '17:00:00',
                'jam_selesai' => '18:30:00',
                'uraian_pekerjaan' => 'Lembur input data lama.',
                'status' => 'approved',
                'approved_by_asisten_id' => $asistenAnalis->id,
                'approved_at_asisten' => $now->copy()->subDays(18),
                'approved_by_manager_id' => $manager->id,
                'approved_at_manager' => $now->copy()->subDays(17),
                'created_at' => $now->copy()->subDays(20),
                'updated_at' => $now->copy()->subDays(17),
            ];
        }
        if ($analisUser && $asistenAnalis) {
            $overtimesData[] = [
                'user_id' => $analisUser->id,
                'tanggal_lembur' => $now->copy()->subDays(25)->toDateString(),
                'jam_mulai' => '17:00:00',
                'jam_selesai' => '18:00:00',
                'uraian_pekerjaan' => 'Lembur revisi laporan.',
                'status' => 'rejected',
                'rejected_by_id' => $asistenAnalis->id,
                'rejected_at' => $now->copy()->subDays(24),
                'notes' => 'Revisi bisa dilanjutkan besok pagi.',
                'created_at' => $now->copy()->subDays(25),
                'updated_at' => $now->copy()->subDays(24),
            ];
        }


        // Masukkan data ke database
        if (!empty($overtimesData)) {
            $this->command->info('Menyimpan ' . count($overtimesData) . ' data lembur contoh...');
            // Gunakan insert agar lebih cepat jika data banyak, tapi tidak menjalankan model event (durasi tidak terhitung otomatis)
            // Jika ingin durasi terhitung, gunakan create dalam loop
            // Overtime::insert($overtimesData); // Cepat tapi durasi null

            // Gunakan create agar model event berjalan (lebih lambat jika data sangat banyak)
            foreach ($overtimesData as $data) {
                try {
                    Overtime::create($data);
                } catch (\Exception $e) {
                    $this->command->error("Gagal menyimpan data untuk user {$data['user_id']}: " . $e->getMessage());
                }
            }
            $this->command->info('Data lembur contoh berhasil disimpan.');
        } else {
            $this->command->warn('Tidak ada data lembur contoh yang bisa dibuat karena user tidak ditemukan.');
        }

        $this->command->info('Overtime Seeder selesai.');
    }
}

<?php 

namespace App\Helpers; // Sesuaikan namespace

class StatusHelper
{
    /**
     * Mendapatkan kelas warna Bootstrap berdasarkan status timesheet bulanan.
     *
     * @param string|null $status Status timesheet ('generated', 'pending_asisten', dll.)
     * @return string Nama kelas Bootstrap (misal: 'success', 'warning', 'danger')
     */
    public static function timesheetStatusColor(?string $status): string
    {
        return match (strtolower($status ?? '')) {
            'approved' => 'success',
            'pending_manager_approval' => 'info',
            'pending_asisten' => 'warning', // Atau 'primary' / 'secondary'
            'generated' => 'secondary', // Atau 'light text-dark'
            'rejected' => 'danger',
            default => 'dark', // Status tidak dikenal
        };
    }

    /**
     * Mendapatkan kelas warna Bootstrap berdasarkan status absensi harian.
     *
     * @param string|null $status Status absensi ('Hadir', 'Terlambat', dll.)
     * @return string Nama kelas Bootstrap
     */
    public static function attendanceStatusColor(?string $status): string
    {
         // Gunakan strtolower untuk case-insensitive matching
        return match (strtolower($status ?? '')) {
            'hadir' => 'success',
            'terlambat' => 'warning',
            'pulang cepat' => 'warning', // Mungkin warna sama dengan terlambat?
            'terlambat & pulang cepat' => 'warning',
            'alpha' => 'danger',
            'sakit' => 'primary', // Warna berbeda untuk Sakit
            'cuti' => 'info',    // Warna berbeda untuk Cuti
            'dinas luar' => 'secondary',
            'lembur' => 'primary', // Warna untuk Lembur di hari libur
            'libur' => 'light text-dark', // Warna untuk Libur
            default => 'dark', // Status tidak dikenal atau null
        };
    }

     /**
     * Mendapatkan kelas warna Bootstrap berdasarkan status pengajuan umum (Cuti/Lembur).
     * Anda bisa menambahkan ini jika diperlukan di view lain.
     *
     * @param string|null $status
     * @return string
     */
    public static function submissionStatusColor(?string $status): string
    {
        return match (strtolower($status ?? '')) {
            'approved' => 'success',
            'pending_manager_approval' => 'info',
            'pending' => 'warning', // Status awal Cuti/Lembur
            'rejected' => 'danger',
            'cancelled' => 'secondary',
            default => 'dark',
        };
    }
}

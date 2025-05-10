<?php

namespace App\Policies;

use App\Models\MonthlyTimesheet;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization; // Laravel 10+
// use Illuminate\Auth\Access\Response; // Jika Anda ingin response kustom

class MonthlyTimesheetPolicy
{
    use HandlesAuthorization; // Laravel 10+

    /**
     * Gate 'before' untuk Admin agar selalu bisa melakukan semua aksi.
     * Metode ini akan dijalankan sebelum metode policy lainnya.
     * Jika mengembalikan non-null, hasil itu akan dianggap sebagai hasil otorisasi.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->role === 'admin') {
            return true; // Admin bisa melakukan semuanya
        }
        return null; // Lanjutkan ke metode policy spesifik jika bukan admin
    }

    /**
     * Determine whether the user can view any monthly timesheets.
     * (Digunakan untuk halaman index umum)
     */
    public function viewAny(User $user): bool
    {
        // Admin sudah di-handle oleh 'before'.
        // Manajemen (Manager & Asisten) boleh lihat daftar umum.
        // Personil mungkin tidak boleh lihat daftar umum, atau hanya lihat miliknya (yang biasanya ditangani di controller).
        // Untuk 'viewAny', kita tentukan siapa yang boleh akses halaman index.
        return $user->role === 'manajemen';
    }

    /**
     * Determine whether the user can view the specific monthly timesheet.
     * (Digunakan untuk halaman show detail)
     */
    public function view(User $user, MonthlyTimesheet $monthlyTimesheet): bool
    {
        // Admin sudah di-handle.
        // Manajemen (Manager & Asisten) boleh lihat.
        // Personil hanya boleh lihat timesheet miliknya sendiri.
        if ($user->role === 'manajemen') {
            return true;
        }
        if ($user->role === 'personil') {
            return $user->id === $monthlyTimesheet->user_id;
        }
        return false;
    }

    /**
     * Determine whether the user can view the Asisten Manager approval list.
     */
    public function viewAsistenApprovalList(User $user): bool
    {
        // Hanya Asisten Manager yang relevan
        return $user->role === 'manajemen' &&
               in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator']);
    }

    /**
     * Determine whether the user can view the Manager approval list.
     */
    public function viewManagerApprovalList(User $user): bool
    {
        // Hanya Manager
        return $user->role === 'manajemen' && $user->jabatan === 'manager';
    }


    /**
     * Determine whether the user (Asisten Manager) can approve the monthly timesheet.
     */
    public function approveAsisten(User $user, MonthlyTimesheet $monthlyTimesheet): bool
    {
        // 1. User harus Asisten Manager
        if (!($user->role === 'manajemen' && in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator']))) {
            return false;
        }

        // 2. Timesheet harus berstatus 'generated' atau 'rejected'
        if (!in_array($monthlyTimesheet->status, ['generated', 'rejected'])) {
            return false;
        }

        // 3. Asisten Manager hanya boleh approve timesheet bawahannya
        $pengajuJabatan = $monthlyTimesheet->user?->jabatan;
        if (!$pengajuJabatan) return false; // Jika pengaju tidak punya jabatan

        if ($user->jabatan === 'asisten manager analis' && !in_array($pengajuJabatan, ['analis', 'admin'])) {
            return false;
        }
        if ($user->jabatan === 'asisten manager preparator' && !in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin'])) {
            return false;
        }
        // Jika ada Asisten lain, tambahkan logikanya

        return true;
    }

    /**
     * Determine whether the user (Manager) can approve the monthly timesheet.
     */
    public function approveManager(User $user, MonthlyTimesheet $monthlyTimesheet): bool
    {
        // 1. User harus Manager
        if (!($user->role === 'manajemen' && $user->jabatan === 'manager')) {
            return false;
        }

        // 2. Timesheet harus berstatus 'pending_manager_approval' atau 'rejected'
        //    (Jika manager bisa approve ulang yg direject)
        if (!in_array($monthlyTimesheet->status, ['pending_manager_approval', 'rejected'])) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can perform bulk approval.
     * Kita bisa buat satu method dengan parameter level, atau dua method terpisah.
     */
    public function bulkApprove(User $user, string $approvalLevel): bool
    {
        if ($approvalLevel === 'asisten') {
            return $user->role === 'manajemen' &&
                   in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator']);
        } elseif ($approvalLevel === 'manager') {
            return $user->role === 'manajemen' && $user->jabatan === 'manager';
        }
        return false;
    }

    // Alternatif jika ingin method terpisah untuk bulk approve:
    // public function bulkApproveAsisten(User $user): bool
    // {
    //     return $user->role === 'manajemen' &&
    //            in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator']);
    // }
    // public function bulkApproveManager(User $user): bool
    // {
    //     return $user->role === 'manajemen' && $user->jabatan === 'manager';
    // }


    /**
     * Determine whether the user can reject the monthly timesheet.
     */
    public function reject(User $user, MonthlyTimesheet $monthlyTimesheet): bool
    {
        // Asisten bisa reject yang 'generated' (sesuai scope bawahannya)
        if ($user->role === 'manajemen' && in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator'])) {
            if ($monthlyTimesheet->status === 'generated') {
                $pengajuJabatan = $monthlyTimesheet->user?->jabatan;
                if (!$pengajuJabatan) return false;

                if ($user->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) {
                    return true;
                }
                if ($user->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin'])) {
                    return true;
                }
            }
        }

        // Manager bisa reject yang 'pending_manager_approval'
        if ($user->role === 'manajemen' && $user->jabatan === 'manager') {
            if ($monthlyTimesheet->status === 'pending_manager_approval') {
                return true;
            }
        }
        // Mungkin Manager juga bisa reject yang 'generated' (jika ingin skip Asisten)
        // if ($user->role === 'manajemen' && $user->jabatan === 'manager' && $monthlyTimesheet->status === 'generated') {
        //     return true;
        // }

        return false;
    }

    /**
     * Determine whether the user can export the monthly timesheet.
     * Asumsi: Admin dan Manajemen boleh export yang sudah approved. Personil boleh export miliknya sendiri jika approved.
     */
    public function export(User $user, MonthlyTimesheet $monthlyTimesheet): bool // Nama method disesuaikan dengan route
    {
        // Admin sudah di-handle
        if ($user->role === 'manajemen') {
            return $monthlyTimesheet->status === 'approved';
        }
        if ($user->role === 'personil') {
            return $user->id === $monthlyTimesheet->user_id && $monthlyTimesheet->status === 'approved';
        }
        return false;
    }

    // Metode create, update, delete, restore, forceDelete mungkin tidak relevan jika timesheet
    // hanya di-generate oleh sistem dan dikelola via approval.
    // Laravel akan membuatkan placeholder-nya, Anda bisa biarkan return false atau hapus jika tidak dipakai.

    /**
     * Determine whether the user can create monthly timesheets.
     * (Biasanya ini dilakukan oleh command, bukan user langsung)
     */
    // public function create(User $user): bool
    // {
    //     return false; // Atau $user->role === 'admin' jika Admin bisa trigger manual
    // }

    /**
     * Determine whether the user can update the monthly timesheet.
     * (Biasanya tidak diupdate manual setelah generate, tapi via approval)
     */
    // public function update(User $user, MonthlyTimesheet $monthlyTimesheet): bool
    // {
    //     return false; // Atau kondisi tertentu jika Admin boleh edit
    // }

    /**
     * Determine whether the user can delete the monthly timesheet.
     */
    // public function delete(User $user, MonthlyTimesheet $monthlyTimesheet): bool
    // {
    //     return $user->role === 'admin'; // Hanya Admin misalnya
    // }

    /**
     * Determine whether the user can restore the monthly timesheet.
     */
    // public function restore(User $user, MonthlyTimesheet $monthlyTimesheet): bool
    // {
    //     return $user->role === 'admin';
    // }

    /**
     * Determine whether the user can permanently delete the monthly timesheet.
     */
    // public function forceDelete(User $user, MonthlyTimesheet $monthlyTimesheet): bool
    // {
    //     return $user->role === 'admin';
    // }
}

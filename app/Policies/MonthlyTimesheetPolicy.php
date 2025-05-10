<?php

namespace App\Policies;

use App\Models\MonthlyTimesheet;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MonthlyTimesheetPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): bool|null
    {
        $restrictedAbilities = ['approveAsisten', 'approveManager', 'reject', 'viewAsistenApprovalList', 'viewManagerApprovalList', 'bulkApprove'];

        if ($user->role === 'admin' && !in_array($ability, $restrictedAbilities)) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        // Semua role yang terotentikasi boleh mengakses halaman index,
        // controller akan memfilter data yang ditampilkan.
        return in_array($user->role, ['admin', 'manajemen', 'personil']);
    }

    public function view(User $user, MonthlyTimesheet $monthlyTimesheet): bool
    {
        if ($user->role === 'manajemen') {
            return true;
        }
        if ($user->role === 'personil') {
            return $user->id === $monthlyTimesheet->user_id;
        }
        return false;
    }

    public function viewAsistenApprovalList(User $user): bool
    {
        return $user->role === 'manajemen' &&
            in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator']);
    }

    public function viewManagerApprovalList(User $user): bool
    {
        return $user->role === 'manajemen' && $user->jabatan === 'manager';
    }

    public function approveAsisten(User $user, MonthlyTimesheet $monthlyTimesheet): bool
    {
        if (!($user->role === 'manajemen' && in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator']))) {
            return false;
        }
        if (!in_array($monthlyTimesheet->status, ['generated', 'rejected'])) {
            return false;
        }
        $pengajuJabatan = $monthlyTimesheet->user?->jabatan;
        if (!$pengajuJabatan) return false;

        if ($user->jabatan === 'asisten manager analis' && !in_array($pengajuJabatan, ['analis', 'admin'])) {
            return false;
        }
        if ($user->jabatan === 'asisten manager preparator' && !in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin'])) {
            return false;
        }
        return true;
    }

    public function approveManager(User $user, MonthlyTimesheet $monthlyTimesheet): bool
    {
        if (!($user->role === 'manajemen' && $user->jabatan === 'manager')) {
            return false;
        }
        // Manager hanya bisa approve yang statusnya 'pending_manager' atau 'rejected' (jika flow memperbolehkan)
        if (!in_array($monthlyTimesheet->status, ['pending_manager', 'rejected'])) { // DISESUAIKAN
            return false;
        }
        return true;
    }

    public function bulkApprove(User $user, string $approvalLevel = null): bool // Parameter $approvalLevel dibuat nullable atau diberi default jika dipanggil tanpa itu dari tempat lain
    {
        if (is_null($approvalLevel)) { // Tambahkan pengecekan jika $approvalLevel tidak disediakan, misalnya dari authorizeResource
            if ($user->role === 'manajemen' && in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator', 'manager'])) {
                return true; // Beri akses umum jika level tidak spesifik, controller akan handle detailnya
            }
            return false;
        }

        if ($approvalLevel === 'asisten') {
            return $user->role === 'manajemen' &&
                in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator']);
        } elseif ($approvalLevel === 'manager') {
            return $user->role === 'manajemen' && $user->jabatan === 'manager';
        }
        return false;
    }

    public function reject(User $user, MonthlyTimesheet $monthlyTimesheet): bool
    {
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

        if ($user->role === 'manajemen' && $user->jabatan === 'manager') {
            // Manager bisa reject yang statusnya 'pending_manager'
            if ($monthlyTimesheet->status === 'pending_manager') { // DISESUAIKAN
                return true;
            }
        }
        return false;
    }

    public function export(User $user, MonthlyTimesheet $monthlyTimesheet): bool
    {
        if ($user->role === 'manajemen') {
            return $monthlyTimesheet->status === 'approved';
        }
        if ($user->role === 'personil') {
            return $user->id === $monthlyTimesheet->user_id && $monthlyTimesheet->status === 'approved';
        }
        return false;
    }

    public function forceReprocess(User $user, MonthlyTimesheet $timesheet): bool
    {
        return $user->role === 'personil';
    }
}

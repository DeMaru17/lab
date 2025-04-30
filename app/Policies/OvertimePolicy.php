<?php

namespace App\Policies;

use App\Models\Overtime;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Carbon\Carbon; // Import Carbon jika perlu cek tanggal

class OvertimePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     * Siapa yang boleh melihat daftar lembur (halaman index)?
     * Semua user terotentikasi boleh (controller filter datanya).
     */
    public function viewAny(User $user): bool
    {
        return true; // Izinkan semua user yg login akses halaman index
    }

    /**
     * Determine whether the user can view the model.
     * Siapa yang boleh melihat detail lembur spesifik? (Jika ada halaman show)
     */
    public function view(User $user, Overtime $overtime): bool
    {
        // Contoh: Pemilik, Admin, Manajemen
        return $user->id === $overtime->user_id
            || $user->role === 'admin'
            || $user->role === 'manajemen';
    }

    /**
     * Determine whether the user can create models.
     * Siapa yang boleh mengajukan lembur?
     */
    public function create(User $user): bool
    {
        // Hanya personil dan admin
        return in_array($user->role, ['personil', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     * Siapa yang boleh mengedit pengajuan lembur?
     */
    public function update(User $user, Overtime $overtime): bool
    {
        // Admin bisa edit semua ATAU user pemilik jika status pending/rejected
        return $user->role === 'admin'
            || ($user->id === $overtime->user_id && in_array($overtime->status, ['pending', 'rejected']));
    }

    /**
     * Determine whether the user can delete the model.
     * Siapa yang boleh menghapus lembur?
     */
    public function delete(User $user, Overtime $overtime): bool
    {
        // Hanya Admin
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can cancel the model.
     * Siapa yang boleh membatalkan pengajuan lembur?
     */
    public function cancel(User $user, Overtime $overtime): bool
    {
        // Hanya pemilik DAN status pending/approved
        // Tambahkan cek tanggal jika perlu (misal: !Carbon::today()->gte($overtime->tanggal_lembur))
        return $user->id === $overtime->user_id
            && in_array($overtime->status, ['pending', 'approved']);
        // && Carbon::today()->lt($overtime->tanggal_lembur); // Uncomment jika perlu cek tanggal
    }

    // === Metode Custom untuk Approval ===

    /**
     * Determine whether the user can approve as Asisten Manager.
     */
    public function approveAsisten(User $approver, Overtime $overtime): bool
    {
        // 1. Approver harus manajemen
        if ($approver->role !== 'manajemen') return false;
        // 2. Status lembur harus pending
        if ($overtime->status !== 'pending') return false;

        // 3. Cek jabatan approver vs jabatan pengaju
        $pengajuJabatan = $overtime->user->jabatan; // Asumsi relasi user sudah dimuat
        if ($approver->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) {
            return true;
        }
        if ($approver->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can approve as Manager.
     */
    public function approveManager(User $approver, Overtime $overtime): bool
    {
        // 1. Approver harus manager
        if ($approver->role !== 'manajemen' || $approver->jabatan !== 'manager') return false;
        // 2. Status lembur harus pending_manager_approval
        if ($overtime->status !== 'pending_manager_approval') return false;

        return true;
    }

    /**
     * Determine whether the user can reject the request.
     */
    public function reject(User $rejecter, Overtime $overtime): bool
    {
        // 1. Rejecter harus manajemen
        if ($rejecter->role !== 'manajemen') return false;

        // 2. Cek apakah rejecter bisa reject pada status saat ini
        if ($overtime->status === 'pending') {
            $pengajuJabatan = $overtime->user->jabatan; // Asumsi relasi user sudah dimuat
            if (($rejecter->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) ||
                ($rejecter->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin']))
            ) {
                return true;
            }
        } elseif ($overtime->status === 'pending_manager_approval') {
            if ($rejecter->jabatan === 'manager') {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can download the PDF.
     */
    public function downloadPdf(User $user, Overtime $overtime): bool
    {
        // Hanya Admin yang boleh download PDF lembur
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can perform bulk approve.
     * Metode ini tidak menerima model spesifik, hanya kelasnya.
     * Kita cek berdasarkan role saja.
     */
    public function bulkApprove(User $user): bool
    {
        // Hanya manajemen yang bisa bulk approve
        return $user->role === 'manajemen';
    }

    /**
     * Determine whether the user can perform bulk PDF download.
     */
    public function bulkDownloadPdf(User $user): bool
    {
        // Hanya admin yang bisa bulk download PDF
        return $user->role === 'admin';
    }
}

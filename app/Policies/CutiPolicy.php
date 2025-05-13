<?php

namespace App\Policies;

use App\Models\Cuti;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization; // Pastikan ini di-import
use Illuminate\Auth\Access\Response;
use Carbon\Carbon; // Import Carbon untuk cek tanggal

class CutiPolicy
{
    use HandlesAuthorization; // Gunakan trait ini

    /**
     * Determine whether the user can view any models.
     * Siapa yang boleh melihat daftar cuti (halaman index)?
     * Asumsi: Semua user terotentikasi boleh lihat (controller akan filter datanya).
     */
    public function viewAny(User $user): bool
    {
        return true; // Izinkan semua user yg login akses halaman index
    }

    /**
     * Determine whether the user can view the model.
     * Siapa yang boleh melihat detail satu cuti spesifik? (Jika ada halaman show)
     * Contoh: Pemilik, Approver terkait, Admin, Manajemen.
     */
    public function view(User $user, Cuti $cuti): bool
    {
        return $user->id === $cuti->user_id // Pemilik
            || $user->role === 'admin'
            || ($user->role === 'manajemen') // Atau manajemen (bisa diperketat lagi jika perlu)
            || $user->id === $cuti->approved_by_asisten_id
            || $user->id === $cuti->approved_by_manager_id
            || $user->id === $cuti->rejected_by_id;
    }

    /**
     * Determine whether the user can create models.
     * Siapa yang boleh mengajukan cuti baru?
     */
    public function create(User $user): bool
    {
        // Hanya personil dan admin yang boleh mengajukan
        return in_array($user->role, ['personil', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     * Siapa yang boleh mengedit pengajuan cuti?
     */
    public function update(User $user, Cuti $cuti): bool
    {
        // Hanya pemilik DAN statusnya pending atau rejected
        return $user->id === $cuti->user_id && in_array($cuti->status, ['pending', 'rejected']);
    }

    /**
     * Determine whether the user can delete the model.
     * (Jika pakai Opsi 1: Delete untuk cancel)
     * Siapa yang boleh menghapus/membatalkan pengajuan cuti?
     */
    public function delete(User $user, Cuti $cuti): bool
    {
        // Hanya pemilik DAN statusnya pending DAN belum mulai
        // return $user->id === $cuti->user_id
        //     && $cuti->status === 'pending'
        //     && Carbon::today()->lt($cuti->mulai_cuti);
        return false; // Kita pakai cancel, jadi delete tidak diizinkan via policy ini
    }

    /**
     * Determine whether the user can cancel the model.
     * (Metode custom untuk Opsi 2: Ubah status ke cancelled)
     * Siapa yang boleh membatalkan pengajuan cuti?
     */
    public function cancel(User $user, Cuti $cuti): bool
    {
        // Hanya pemilik DAN status pending/approved DAN belum mulai
        return $user->id === $cuti->user_id
            && in_array($cuti->status, ['pending', 'approved'])
            && Carbon::today()->lt($cuti->mulai_cuti);
    }


    /**
     * Determine whether the user can restore the model.
     * (Biasanya tidak relevan untuk cuti)
     */
    // public function restore(User $user, Cuti $cuti): bool
    // {
    //     return false;
    // }

    /**
     * Determine whether the user can permanently delete the model.
     * (Biasanya tidak relevan untuk cuti)
     */
    // public function forceDelete(User $user, Cuti $cuti): bool
    // {
    //     return false;
    // }

    /**
     * Determine whether the user can view the approval list.
     * Siapa yang boleh melihat daftar cuti yang menunggu persetujuan?
     */
    public function viewAsistenApprovalList(User $user): bool
    {
        return $user->role === 'manajemen' &&
            in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator']);
    }

    /**
     * Determine whether the user can view the manager approval list.
     * Siapa yang boleh melihat daftar cuti yang menunggu persetujuan manager?
     */
    public function viewManagerApprovalList(User $user): bool
    {
        return $user->role === 'manajemen' && $user->jabatan === 'manager';
    }


    // === Metode Custom untuk Approval ===

    /**
     * Determine whether the user can approve as Asisten Manager.
     */
    public function approveAsisten(User $approver, Cuti $cuti): bool
    {
        // 1. Approver harus manajemen
        if ($approver->role !== 'manajemen') return false;
        // 2. Status cuti harus pending
        if ($cuti->status !== 'pending') return false;

        // 3. Cek jabatan approver vs jabatan pengaju
        $pengajuJabatan = $cuti->user->jabatan;
        if ($approver->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) {
            return true;
        }
        if ($approver->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin'])) {
            return true;
        }

        return false; // Jika tidak cocok
    }

    /**
     * Determine whether the user can approve as Manager.
     */
    public function approveManager(User $approver, Cuti $cuti): bool
    {
        // 1. Approver harus manager
        if ($approver->role !== 'manajemen' || $approver->jabatan !== 'manager') return false;
        // 2. Status cuti harus pending_manager_approval
        if ($cuti->status !== 'pending_manager_approval') return false;

        return true;
    }

    /**
     * Determine whether the user can reject the request.
     */
    public function reject(User $rejecter, Cuti $cuti): bool
    {
        // 1. Rejecter harus manajemen
        if ($rejecter->role !== 'manajemen') return false;

        // 2. Cek apakah rejecter bisa reject pada status saat ini
        if ($cuti->status === 'pending') {
            // Jika pending, cek apakah dia Asisten yang tepat
            $pengajuJabatan = $cuti->user->jabatan;
            if (($rejecter->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) ||
                ($rejecter->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin']))
            ) {
                return true;
            }
        } elseif ($cuti->status === 'pending_manager_approval') {
            // Jika menunggu L2, cek apakah dia Manager
            if ($rejecter->jabatan === 'manager') {
                return true;
            }
        }

        return false; // Jika tidak memenuhi syarat reject
    }

    /**
     * Determine whether the user can download the PDF.
     */
    public function downloadPdf(User $user, Cuti $cuti): bool
    {
        // Sesuaikan dengan aturan otorisasi download Anda
        return $user->role === 'admin';
    }
}

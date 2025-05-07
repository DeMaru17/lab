<?php

namespace App\Policies;

use App\Models\AttendanceCorrection;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendanceCorrectionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     * Siapa yang boleh melihat daftar SEMUA pengajuan koreksi? (Misal: Admin)
     * Atau daftar pengajuan milik sendiri? (Akan dibuat route/method terpisah)
     * Untuk halaman approval, kita akan pakai metode custom.
     */
    public function viewAny(User $user): bool
    {
        // Izinkan admin melihat semua, atau manajemen untuk monitoring
        return in_array($user->role, ['admin', 'manajemen']);
    }

    /**
     * Determine whether the user can view the model.
     * Siapa yang boleh melihat detail satu pengajuan koreksi?
     */
    public function view(User $user, AttendanceCorrection $correction): bool
    {
        // Pemilik pengajuan, Admin, Manajemen (termasuk approver)
        return $user->id === $correction->user_id
            || in_array($user->role, ['admin', 'manajemen']);
    }

    /**
     * Determine whether the user can create models.
     * Siapa yang boleh MEMBUAT pengajuan koreksi?
     */
    public function create(User $user): bool
    {
        // Personil dan Admin boleh mengajukan koreksi untuk diri sendiri
        // (Controller akan memastikan user_id adalah milik sendiri jika role personil)
        return in_array($user->role, ['personil', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     * Pengajuan koreksi biasanya tidak diedit, tapi diapprove/reject.
     */
    public function update(User $user, AttendanceCorrection $correction): bool
    {
        // Secara umum, tidak boleh diedit setelah diajukan
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * Pengajuan koreksi biasanya tidak dihapus, tapi dibatalkan atau ditolak.
     */
    public function delete(User $user, AttendanceCorrection $correction): bool
    {
        // Mungkin admin boleh hapus? Atau pemilik jika masih pending?
        // Untuk sekarang, kita disable dulu.
        return false;
        // Contoh jika pemilik boleh cancel saat pending:
        // return $user->id === $correction->user_id && $correction->status === 'pending';
    }

    /**
     * Determine whether the user can restore the model.
     */
    // public function restore(User $user, AttendanceCorrection $correction): bool
    // {
    //     return $user->role === 'admin';
    // }

    /**
     * Determine whether the user can permanently delete the model.
     */
    // public function forceDelete(User $user, AttendanceCorrection $correction): bool
    // {
    //     return $user->role === 'admin';
    // }


    // === METODE CUSTOM UNTUK APPROVAL ===

    /**
     * Determine whether the user (Asisten Manager) can approve a correction request.
     *
     * @param  \App\Models\User  $approver User yang mencoba melakukan approval
     * @param  \App\Models\AttendanceCorrection  $correction Pengajuan koreksi yang akan diapprove
     * @return bool
     */
    public function approve(User $approver, AttendanceCorrection $correction): bool
    {
        // 1. Hanya role 'manajemen' yang bisa approve
        if ($approver->role !== 'manajemen') {
            return false;
        }

        // 2. Hanya bisa approve jika statusnya 'pending'
        if ($correction->status !== 'pending') {
            return false;
        }

        // 3. Cek jabatan approver vs jabatan pengaju (requester)
        // Pastikan relasi requester sudah di-load atau load di sini
        $requester = $correction->requester()->first(); // Ambil user pengaju
        if (!$requester || !$requester->jabatan) {
            return false; // Tidak bisa menentukan jabatan pengaju
        }
        $requesterJabatan = $requester->jabatan;

        // Asisten Manager Analis hanya approve Analis & Admin
        if ($approver->jabatan === 'asisten manager analis' && in_array($requesterJabatan, ['analis', 'admin'])) {
            return true;
        }

        // Asisten Manager Preparator hanya approve Preparator, Mekanik & Admin
        if ($approver->jabatan === 'asisten manager preparator' && in_array($requesterJabatan, ['preparator', 'mekanik', 'admin'])) {
            return true;
        }

        // Jika bukan kondisi di atas, tidak boleh approve
        return false;
    }

    /**
     * Determine whether the user (Asisten Manager) can reject a correction request.
     * Logikanya sama dengan approve untuk saat ini.
     *
     * @param  \App\Models\User  $rejecter User yang mencoba melakukan rejection
     * @param  \App\Models\AttendanceCorrection  $correction Pengajuan koreksi yang akan direject
     * @return bool
     */
    public function reject(User $rejecter, AttendanceCorrection $correction): bool
    {
        // Untuk saat ini, aturan reject sama dengan approve
        return $this->approve($rejecter, $correction);
    }

    /**
     * Determine whether the user (Manajemen) can view the approval list page.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewApprovalList(User $user): bool
    {
        // Hanya Asisten Manager yang perlu melihat halaman approval
        return $user->role === 'manajemen' && in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator']);
    }
}

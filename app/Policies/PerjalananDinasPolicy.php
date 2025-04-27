<?php

namespace App\Policies;

use App\Models\PerjalananDinas;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization; // Pastikan diimport

class PerjalananDinasPolicy
{
    use HandlesAuthorization; // Gunakan trait

    /**
     * Determine whether the user can view any models.
     * Siapa yang boleh melihat halaman index?
     * Semua user terotentikasi boleh (controller akan filter datanya).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Siapa yang boleh melihat detail spesifik? (User bilang tidak ada halaman detail)
     * Jika ada, izinkan pemilik, admin, atau manajemen.
     */
    public function view(User $user, PerjalananDinas $perjalananDinas): bool
    {
        // return $user->id === $perjalananDinas->user_id
        //     || $user->role === 'admin'
        //     || $user->role === 'manajemen';
        return false; // Karena user bilang tidak ada halaman detail
    }

    /**
     * Determine whether the user can create models.
     * Siapa yang boleh mengakses form create atau mengirim data store?
     */
    public function create(User $user): bool
    {
        // Admin dan Personil boleh (controller akan handle siapa yg bisa dibuatkan)
        return in_array($user->role, ['admin', 'personil']);
    }

    /**
     * Determine whether the user can update the model.
     * Siapa yang boleh mengedit/memperbarui data?
     */
    public function update(User $user, PerjalananDinas $perjalananDinas): bool
    {
        // Admin boleh edit semua
        if ($user->role === 'admin') {
            return true;
        }
        // Personil hanya boleh edit miliknya sendiri
        // (Asumsi bisa edit kapan saja sesuai instruksi "edit berfungsi hanya utk data final")
        if ($user->role === 'personil' && $user->id === $perjalananDinas->user_id) {
            return true;
        }
        // Manajemen tidak bisa edit (hanya monitoring)
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * Siapa yang boleh menghapus?
     */
    public function delete(User $user, PerjalananDinas $perjalananDinas): bool
    {
        // Hanya Admin yang boleh hapus
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can restore the model.
     */
    // public function restore(User $user, PerjalananDinas $perjalananDinas): bool
    // {
    //     // Sesuaikan jika ada fitur soft delete
    //     return $user->role === 'admin';
    // }

    /**
     * Determine whether the user can permanently delete the model.
     */
    // public function forceDelete(User $user, PerjalananDinas $perjalananDinas): bool
    // {
    //     // Sesuaikan jika ada fitur soft delete
    //     return $user->role === 'admin';
    // }

    // --- Metode Custom (jika diperlukan) ---

    /**
     * Determine whether the user can update the 'tanggal_pulang' field.
     * (Ini bisa jadi bagian dari policy 'update' atau dibuat terpisah jika ada aksi khusus)
     * Siapa yang boleh menandai perjalanan selesai / mengisi tanggal pulang?
     */
    public function markComplete(User $user, PerjalananDinas $perjalananDinas): bool
    {
        // Admin boleh untuk semua
        if ($user->role === 'admin') {
            return true;
        }
        // Personil hanya boleh untuk miliknya sendiri
        if ($user->role === 'personil' && $user->id === $perjalananDinas->user_id) {
            return true;
        }
        return false;
    }
}

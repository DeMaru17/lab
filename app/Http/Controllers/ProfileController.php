<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Untuk mendapatkan pengguna yang sedang login
use Illuminate\Support\Facades\Hash; // Untuk hashing password baru
use Illuminate\Support\Facades\Storage; // Untuk menghapus file lama (tanda tangan)
use Illuminate\Support\Facades\Log;     // Untuk logging error
use Illuminate\Validation\Rule; // Untuk validasi unique pada email, mengabaikan user saat ini
use Illuminate\Validation\Rules\Password; // Untuk aturan validasi kekuatan password (Laravel 8+)
use RealRashid\SweetAlert\Facades\Alert; // Untuk menampilkan notifikasi SweetAlert
use App\Models\User; // Ditambahkan untuk type hinting pada $user

/**
 * Class ProfileController
 *
 * Mengelola operasi yang berkaitan dengan profil pengguna yang sedang login.
 * Pengguna dapat melihat dan memperbarui informasi profil mereka, termasuk nama,
 * email, jenis kelamin, password, dan tanda tangan digital.
 *
 * @package App\Http\Controllers
 */
class ProfileController extends Controller
{
    /**
     * Menampilkan form untuk mengedit profil pengguna yang sedang login.
     *
     * @return \Illuminate\View\View Mengembalikan view 'profile.edit' dengan data pengguna yang login.
     */
    public function edit()
    {
        /** @var \App\Models\User $user Pengguna yang sedang login. */
        $user = Auth::user(); // Mengambil data pengguna yang sedang terotentikasi
        return view('profile.edit', compact('user')); // Mengirim data pengguna ke view
    }

    /**
     * Memperbarui data profil pengguna yang sedang login di database.
     * Termasuk validasi input, pembaruan password (jika diisi),
     * dan pengelolaan file tanda tangan digital (upload baru dan hapus lama).
     *
     * @param  \Illuminate\Http\Request  $request Data dari form edit profil.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke halaman edit profil dengan pesan status.
     */
    public function update(Request $request)
    {
        /** @var \App\Models\User $user Pengguna yang sedang login. */
        $user = Auth::user();

        // Validasi data input dari form
        $validatedData = $request->validate([
            'name' => 'required|string|max:255', // Nama wajib diisi
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id), // Email harus unik, kecuali untuk user saat ini
            ],
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan', // Jenis kelamin wajib dipilih salah satu
            // Password opsional, jika diisi harus dikonfirmasi dan memenuhi syarat minimal (misal 8 karakter)
            'password' => ['nullable', 'confirmed', Password::min(8)->sometimes()], // sometimes() agar aturan min(8) hanya berlaku jika password diisi
            'signature_image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024', // Tanda tangan opsional, format gambar, maks 1MB
        ], [
            // Pesan error kustom untuk validasi
            'email.unique' => 'Alamat email ini sudah digunakan oleh pengguna lain.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok dengan password yang dimasukkan.',
            'password.min' => 'Password baru minimal harus 8 karakter.',
        ]);

        // Menyiapkan data dasar untuk diupdate
        $updateData = [
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'jenis_kelamin' => $validatedData['jenis_kelamin'],
        ];

        // Update password HANYA jika field password baru diisi di form
        if (!empty($validatedData['password'])) {
            $updateData['password'] = Hash::make($validatedData['password']); // Hash password baru sebelum disimpan
            Log::info("Password diperbarui untuk User ID {$user->id}."); // Logging perubahan password
        }

        // Proses upload dan update file tanda tangan jika ada file baru yang diunggah
        if ($request->hasFile('signature_image')) {
            try {
                // Hapus file tanda tangan lama jika ada sebelum menyimpan yang baru
                if ($user->signature_path && Storage::disk('public')->exists($user->signature_path)) {
                    Storage::disk('public')->delete($user->signature_path);
                    Log::info("Tanda tangan lama ('{$user->signature_path}') untuk User ID {$user->id} telah dihapus.");
                }
                // Simpan file tanda tangan baru ke direktori 'signatures' di disk 'public'
                $path = $request->file('signature_image')->store('signatures', 'public');
                $updateData['signature_path'] = $path; // Simpan path baru ke data update
                Log::info("Tanda tangan baru ('{$path}') untuk User ID {$user->id} telah diunggah.");
            } catch (\Exception $e) {
                Log::error("Gagal mengunggah file tanda tangan baru untuk User ID {$user->id}: " . $e->getMessage());
                // Beri peringatan bahwa upload gagal, tapi data profil lain tetap disimpan
                Alert::warning('Info Tambahan', 'Data profil berhasil disimpan, tetapi gagal mengunggah file tanda tangan baru.');
                // Proses update data profil lain tetap dilanjutkan.
            }
        } elseif ($request->input('remove_signature_image') == '1' && $user->signature_path) {
            // Jika ada input untuk menghapus signature (misal dari checkbox) dan ada signature lama
             if (Storage::disk('public')->exists($user->signature_path)) {
                Storage::disk('public')->delete($user->signature_path);
                Log::info("Tanda tangan ('{$user->signature_path}') untuk User ID {$user->id} telah dihapus berdasarkan permintaan.");
            }
            $updateData['signature_path'] = null; // Set path menjadi null di database
        }


        // Lakukan update pada record pengguna yang sedang login
        try {
            $user->update($updateData); // Memperbarui data pengguna
            Alert::success('Sukses Diperbarui', 'Profil Anda berhasil diperbarui.');
            Log::info("Profil untuk User ID {$user->id} berhasil diperbarui.");
        } catch (\Exception $e) {
            Log::error("Error saat memperbarui profil untuk User ID {$user->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Update', 'Terjadi kesalahan saat memperbarui profil Anda. Silakan coba lagi.');
        }

        return redirect()->route('profile.edit'); // Kembali ke halaman edit profil
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Untuk mendapatkan user login
use Illuminate\Support\Facades\Hash; // Untuk hash password baru
use Illuminate\Support\Facades\Storage; // Untuk hapus file lama
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule; // Untuk validasi unique email
use Illuminate\Validation\Rules\Password; // Untuk aturan password kuat (opsional)
use RealRashid\SweetAlert\Facades\Alert;

class ProfileController extends Controller
{
    /**
     * Menampilkan form edit profil untuk pengguna yang sedang login.
     */
    public function edit()
    {
        $user = Auth::user(); // Ambil data user yang login
        return view('profile.edit', compact('user')); // Kirim data ke view
    }

    /**
     * Memperbarui data profil pengguna yang sedang login.
     */
    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user(); // Ambil user yang login

        // Validasi input
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'signature_image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024',
        ], [
            'email.unique' => 'Alamat email ini sudah digunakan oleh pengguna lain.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        // Siapkan data untuk diupdate
        $updateData = [
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'jenis_kelamin' => $validatedData['jenis_kelamin'],
        ];

        // Update password HANYA jika field password baru diisi
        if (!empty($validatedData['password'])) {
            $updateData['password'] = Hash::make($validatedData['password']);
            // Baris $this->command->info() dihapus dari sini
        }

        // Proses upload signature jika ada file baru
        if ($request->hasFile('signature_image')) {
            try {
                if ($user->signature_path && Storage::disk('public')->exists($user->signature_path)) {
                    Storage::disk('public')->delete($user->signature_path);
                }
                $path = $request->file('signature_image')->store('signatures', 'public');
                $updateData['signature_path'] = $path;
            } catch (\Exception $e) {
                Log::error("Signature upload failed for user {$user->id}: " . $e->getMessage());
                Alert::warning('Info', 'Data profil disimpan, tetapi gagal mengunggah tanda tangan baru.');
                // Lanjutkan simpan data lain
            }
        }

        // Lakukan update pada user yang login
        try {
            // dd(get_class($user), $user instanceof \Illuminate\Database\Eloquent\Model);
            $user->update($updateData);
            Alert::success('Sukses', 'Profil Anda berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error("Error updating profile for user {$user->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan saat memperbarui profil.');
        }

        return redirect()->route('profile.edit'); // Kembali ke halaman edit profil
    }
}

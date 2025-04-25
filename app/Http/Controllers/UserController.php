<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RealRashid\SweetAlert\Facades\Alert;
use App\Models\Vendor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class UserController extends Controller
{
    // Menampilkan daftar pengguna
    public function index()
    {
        $users = User::all(); // Ambil semua data pengguna
        $title = 'Hapus Pengguna';
        $text = "Kamu yakin ingin menghapus pengguna?";
        confirmDelete($title, $text);
        return view('Personil.index', compact('users')); // Kirim data ke view
    }

    // Menampilkan form untuk menambahkan pengguna baru
    public function create()
    {
        $vendors = Vendor::orderBy('name')->get(); // <-- Ambil daftar vendor
        return view('Personil.create', compact('vendors')); // <-- Kirim vendors ke view
    }

    // Menyimpan pengguna baru ke database
    public function store(Request $request)
    {
        try {
            // Tambahkan signature_image ke validasi
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
                'password' => 'required|min:8|confirmed',
                'password_confirmation' => 'required|same:password',
                'jabatan' => 'required|in:manager,asisten manager analis,asisten manager preparator,preparator,analis,mekanik,admin',
                'tanggal_mulai_bekerja' => 'required|date',
                'vendor_id' => 'nullable|exists:vendors,id',
                'signature_image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024', // Validasi signature
            ], [
                // ... (custom messages lain) ...
                'signature_image.image' => 'File tanda tangan harus berupa gambar.',
                'signature_image.mimes' => 'Format tanda tangan harus PNG, JPG, atau JPEG.',
                'signature_image.max' => 'Ukuran tanda tangan maksimal 1MB.',
            ]);

            $role = match ($validatedData['jabatan']) {
                'manager', 'asisten manager analis', 'asisten manager preparator' => 'manajemen',
                'admin' => 'admin',
                default => 'personil',
            };

            // Siapkan data dasar untuk create
            $createData = [
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'jenis_kelamin' => $validatedData['jenis_kelamin'],
                'password' => Hash::make($validatedData['password']),
                'jabatan' => $validatedData['jabatan'],
                'role' => $role,
                'tanggal_mulai_bekerja' => $validatedData['tanggal_mulai_bekerja'],
                'vendor_id' => $validatedData['vendor_id'] ?? null,
            ];

            // === TAMBAHKAN LOGIKA SIMPAN SIGNATURE ===
            if ($request->hasFile('signature_image')) {
                try {
                    $path = $request->file('signature_image')->store('signatures', 'public');
                    $createData['signature_path'] = $path; // Tambahkan path ke data create
                } catch (\Exception $e) {
                    // Jika upload gagal, sebaiknya batalkan proses atau beri warning
                    Log::error("Signature upload failed during user creation: " . $e->getMessage());
                    // Anda bisa memilih:
                    // 1. Tetap buat user tanpa signature + beri warning
                    // Alert::warning('Warning', 'User berhasil dibuat, tetapi gagal mengunggah tanda tangan.');
                    // 2. Batalkan pembuatan user dan beri error (lebih aman)
                    Alert::error('Gagal Upload', 'Gagal mengunggah file tanda tangan. Pengguna tidak dibuat.');
                    return redirect()->back()->withInput();
                }
            }
            // === AKHIR LOGIKA SIMPAN SIGNATURE ===

            // Buat user baru dengan data yang sudah disiapkan
            User::create($createData);

            Alert::success('Sukses', 'Akun Telah dibuat');
            return redirect()->route('personil.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errorMessage = $e->validator->errors()->first();
            Alert::error('Gagal Validasi', $errorMessage);
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error("Error creating user: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan saat membuat pengguna baru.');
            return redirect()->back()->withInput();
        }
    }

    // Menampilkan form untuk mengedit pengguna
    // public function edit(String $id)
    // {
    //     $user = User::findOrFail($id); // Ambil data pengguna berdasarkan ID
    //     return view('personil.edit', compact('user')); // Kirim data pengguna ke view
    // }

    public function edit(User $personil)
    {
        $vendors = Vendor::orderBy('name')->get(); // <-- Ambil daftar vendor
        return view('personil.edit', [
            'user' => $personil,
            'vendors' => $vendors // <-- Kirim vendors ke view
        ]);
    }


    // Memperbarui data pengguna di database
    public function update(Request $request, User $personil)
    {
        try {
            $validatedData = $request->validate([ // Masukkan hasil validate ke variabel
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $personil->id,
                'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
                'jabatan' => 'required|in:manager,asisten manager analis,asisten manager preparator,preparator,analis,mekanik,admin',
                'password' => 'nullable|min:8|confirmed',
                'password_confirmation' => 'nullable|required_with:password|same:password', // Hanya wajib jika password diisi
                'tanggal_mulai_bekerja' => 'required|date',
                'vendor_id' => 'nullable|exists:vendors,id', // <-- Validasi Vendor ID
                'signature_image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024', // <-- Validasi Signature
            ], [
                'password.confirmed' => 'Password dan konfirmasi password tidak cocok.',
                'password_confirmation.same' => 'Konfirmasi password tidak sesuai.',
                'password_confirmation.required_with' => 'Konfirmasi password wajib diisi jika password baru dimasukkan.',
                'vendor_id.exists' => 'Vendor yang dipilih tidak valid.',
            ]);

            $role = match ($validatedData['jabatan']) { // Ambil dari validated data
                'manager', 'asisten manager analis', 'asisten manager preparator' => 'manajemen',
                'admin' => 'admin',
                default => 'personil',
            };

            // Siapkan data untuk update
            $updateData = [
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'jenis_kelamin' => $validatedData['jenis_kelamin'],
                'jabatan' => $validatedData['jabatan'],
                'role' => $role,
                'tanggal_mulai_bekerja' => $validatedData['tanggal_mulai_bekerja'],
                'vendor_id' => $validatedData['vendor_id'] ?? null, // <-- Simpan vendor_id
            ];

            // Update password jika diisi
            if (!empty($validatedData['password'])) {
                $updateData['password'] = Hash::make($validatedData['password']);
            }

            // Proses upload signature jika ada
            if ($request->hasFile('signature_image')) {
                if ($personil->signature_path) {
                    Storage::disk('public')->delete($personil->signature_path);
                }
                $path = $request->file('signature_image')->store('signatures', 'public');
                $updateData['signature_path'] = $path; // <-- Simpan path signature
            }

            // Lakukan update
            $personil->update($updateData);

            Alert::success('Sukses', 'Akun Telah diperbarui');
            return redirect()->route('personil.index'); // Hapus with() jika pakai Alert facade

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Tangani validation exception dengan SweetAlert
            // Ambil pesan error pertama saja untuk ditampilkan
            $errorMessage = $e->validator->errors()->first();
            Alert::error('Gagal Validasi', $errorMessage);
            return redirect()->back()->withErrors($e->errors())->withInput(); // Tetap kirim errors ke view
        } catch (\Exception $e) {
            // Tangani error umum lainnya
            Log::error("Error updating user ID {$personil->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan saat memperbarui data pengguna.');
            return redirect()->back()->withInput();
        }
    }

    // Menghapus pengguna dari database
    public function destroy(User $personil)
    {
        User::destroy($personil->id); // Hapus pengguna berdasarkan ID
        Alert::success('Sukses', 'Akun Telah dihapus');
        return redirect()->route('personil.index')->with('success', 'Pengguna berhasil dihapus.');
    }
}

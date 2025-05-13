<?php

namespace App\Http\Controllers;

use App\Models\User; // Model untuk data pengguna
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash; // Untuk hashing password
use RealRashid\SweetAlert\Facades\Alert; // Untuk notifikasi SweetAlert
use App\Models\Vendor; // Model untuk data vendor, digunakan saat membuat/mengedit user
use Illuminate\Support\Facades\Storage; // Untuk operasi file (signature)
use Illuminate\Support\Facades\Log;     // Untuk logging error dan informasi
use Illuminate\Support\Facades\DB;      // Ditambahkan untuk konsistensi jika ada transaksi DB
use Illuminate\Support\Facades\Auth;    // Untuk otentikasi pengguna

/**
 * Class UserController
 *
 * Mengelola semua operasi CRUD (Create, Read, Update, Delete) yang berkaitan
 * dengan data pengguna (karyawan/personil). Controller ini biasanya diakses
 * oleh Admin untuk mengelola akun pengguna dalam sistem.
 *
 * @package App\Http\Controllers
 */
class UserController extends Controller
{
    /**
     * Menampilkan daftar semua pengguna.
     * Method ini juga menyiapkan data untuk konfirmasi penghapusan menggunakan SweetAlert.
     *
     * @return \Illuminate\View\View Mengembalikan view 'Personil.index' dengan data semua pengguna.
     */
    public function index()
    {
        // Otorisasi: Biasanya hanya Admin yang boleh melihat semua pengguna.
        // Anda bisa menambahkan middleware 'role:admin' pada route atau menggunakan Policy.
        // Contoh dengan Policy (jika ada UserPolicy@viewAny):
        // $this->authorize('viewAny', User::class);

        $users = User::with('vendor:id,name')->orderBy('name', 'asc')->get(); // Ambil semua data pengguna, eager load vendor, urutkan berdasarkan nama
        // Menyiapkan judul dan teks untuk SweetAlert konfirmasi penghapusan.
        // Fungsi confirmDelete() adalah helper global dari package SweetAlert.
        $title = 'Hapus Pengguna';
        $text = "Anda yakin ingin menghapus pengguna ini?";
        confirmDelete($title, $text); // Ini akan menyiapkan JavaScript untuk SweetAlert di view

        return view('Personil.index', compact('users')); // Kirim data ke view
    }

    /**
     * Menampilkan form untuk menambahkan pengguna (personil) baru.
     * Menyertakan daftar vendor untuk dipilih jika pengguna terkait dengan vendor.
     *
     * @return \Illuminate\View\View Mengembalikan view 'Personil.create' dengan daftar vendor.
     */
    public function create()
    {
        // Otorisasi: Hanya Admin yang boleh membuat pengguna baru.
        // $this->authorize('create', User::class);

        // Mengambil semua data vendor, diurutkan berdasarkan nama, untuk ditampilkan di dropdown
        $vendors = Vendor::orderBy('name')->get();
        return view('Personil.create', compact('vendors'));
    }

    /**
     * Menyimpan data pengguna (personil) baru ke database setelah validasi.
     * Termasuk hashing password dan penyimpanan file tanda tangan (signature) jika diunggah.
     * Peran (role) pengguna ditentukan secara otomatis berdasarkan jabatan yang dipilih.
     *
     * @param  \Illuminate\Http\Request  $request Data dari form pembuatan pengguna.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke halaman daftar pengguna dengan pesan status.
     */
    public function store(Request $request)
    {
        // Otorisasi:
        // $this->authorize('create', User::class);

        DB::beginTransaction(); // Memulai transaksi database
        try {
            // Validasi data input dari form
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email', // Email harus unik di tabel users
                'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
                'password' => 'required|min:8|confirmed', // Password minimal 8 karakter dan harus ada konfirmasi
                'password_confirmation' => 'required|same:password', // Konfirmasi password harus sama dengan password
                'jabatan' => 'required|in:manager,asisten manager analis,asisten manager preparator,preparator,analis,mekanik,admin', // Jabatan harus salah satu dari daftar
                'tanggal_mulai_bekerja' => 'required|date',
                'vendor_id' => 'nullable|exists:vendors,id', // Vendor opsional, jika diisi harus ada di tabel vendors
                'signature_image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024', // Tanda tangan opsional, format gambar, maks 1MB
            ], [
                // Pesan error kustom untuk validasi (opsional)
                'signature_image.image' => 'File tanda tangan harus berupa gambar.',
                'signature_image.mimes' => 'Format tanda tangan harus PNG, JPG, atau JPEG.',
                'signature_image.max' => 'Ukuran file tanda tangan maksimal 1MB.',
                'password.confirmed' => 'Konfirmasi password tidak cocok dengan password yang dimasukkan.',
                'password_confirmation.same' => 'Konfirmasi password harus sama dengan password.',
            ]);

            // Menentukan peran (role) pengguna secara otomatis berdasarkan jabatan yang dipilih
            $role = match ($validatedData['jabatan']) {
                'manager', 'asisten manager analis', 'asisten manager preparator' => 'manajemen',
                'admin' => 'admin',
                default => 'personil', // Jabatan lain seperti preparator, analis, mekanik akan menjadi 'personil'
            };

            // Menyiapkan data dasar untuk membuat record User baru
            $createData = [
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'jenis_kelamin' => $validatedData['jenis_kelamin'],
                'password' => Hash::make($validatedData['password']), // Password di-hash sebelum disimpan
                'jabatan' => $validatedData['jabatan'],
                'role' => $role,
                'tanggal_mulai_bekerja' => $validatedData['tanggal_mulai_bekerja'],
                'vendor_id' => $validatedData['vendor_id'] ?? null, // Jika tidak ada, set null
            ];

            // === LOGIKA PENYIMPANAN FILE TANDA TANGAN (SIGNATURE) ===
            if ($request->hasFile('signature_image')) {
                try {
                    // Simpan file ke direktori 'signatures' di dalam disk 'public'
                    $path = $request->file('signature_image')->store('signatures', 'public');
                    $createData['signature_path'] = $path; // Tambahkan path file ke data yang akan disimpan
                } catch (\Exception $e) {
                    // Jika unggah file gagal, batalkan transaksi dan kembalikan error
                    DB::rollBack(); // Penting untuk rollback jika file gagal diupload setelah validasi
                    Log::error("Gagal mengunggah file tanda tangan saat pembuatan user: " . $e->getMessage());
                    Alert::error('Gagal Upload', 'Gagal mengunggah file tanda tangan. Pengguna tidak dibuat.');
                    return redirect()->back()->withInput();
                }
            }
            // === AKHIR LOGIKA PENYIMPANAN SIGNATURE ===

            // Buat user baru dengan data yang sudah disiapkan
            User::create($createData);
            DB::commit(); // Simpan semua perubahan ke database

            Alert::success('Sukses Dibuat', 'Akun pengguna baru telah berhasil dibuat.');
            return redirect()->route('personil.index'); // Mengarahkan ke halaman daftar pengguna (sesuaikan nama route jika berbeda)
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack(); // Batalkan transaksi jika validasi gagal setelah try-catch (meskipun biasanya validasi Laravel menghentikan sebelum try)
            // Menangani exception validasi dan menampilkan pesan error pertama
            $errorMessage = $e->validator->errors()->first();
            Alert::error('Gagal Validasi', $errorMessage);
            return redirect()->back()->withErrors($e->errors())->withInput(); // Kembali dengan error dan input sebelumnya
        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan transaksi jika terjadi error umum lainnya
            Log::error("Error saat membuat pengguna baru: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Sistem', 'Terjadi kesalahan saat membuat pengguna baru. Silakan coba lagi.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Menampilkan form untuk mengedit data pengguna yang sudah ada.
     * Menggunakan Route Model Binding untuk mengambil instance User.
     *
     * @param  \App\Models\User  $personil Instance User yang akan diedit (nama parameter '$personil' sesuai dengan definisi di route).
     * @return \Illuminate\View\View Mengembalikan view 'Personil.edit' dengan data pengguna dan daftar vendor.
     */
    public function edit(User $personil) // Route Model Binding untuk $personil
    {
        // Otorisasi: Hanya Admin yang boleh mengedit pengguna.
        // $this->authorize('update', $personil); // Jika menggunakan UserPolicy

        // Mengambil semua data vendor untuk ditampilkan di dropdown
        $vendors = Vendor::orderBy('name')->get();
        return view('Personil.edit', [
            'user' => $personil, // Mengirim data pengguna dengan key 'user' agar konsisten
            'vendors' => $vendors
        ]);
    }


    /**
     * Memperbarui data pengguna yang sudah ada di database.
     * Termasuk pembaruan password (jika diisi) dan file tanda tangan (jika diunggah baru).
     *
     * @param  \Illuminate\Http\Request  $request Data dari form edit pengguna.
     * @param  \App\Models\User  $personil Instance User yang akan diupdate.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali dengan pesan status.
     */
    public function update(Request $request, User $personil)
    {
        // Otorisasi:
        // $this->authorize('update', $personil);
        DB::beginTransaction();
        try {
            // Validasi data input, email unik kecuali untuk user saat ini
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $personil->id, // Email unik, abaikan ID user saat ini
                'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
                'jabatan' => 'required|in:manager,asisten manager analis,asisten manager preparator,preparator,analis,mekanik,admin',
                'password' => 'nullable|min:8|confirmed', // Password opsional, jika diisi minimal 8 karakter dan ada konfirmasi
                'password_confirmation' => 'nullable|required_with:password|same:password', // Wajib jika password diisi, dan harus sama
                'tanggal_mulai_bekerja' => 'required|date',
                'vendor_id' => 'nullable|exists:vendors,id', // Vendor opsional
                'signature_image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024', // Tanda tangan opsional
            ], [
                // Pesan error kustom
                'password.confirmed' => 'Password dan konfirmasi password tidak cocok.',
                'password_confirmation.same' => 'Konfirmasi password tidak sesuai dengan password baru.',
                'password_confirmation.required_with' => 'Konfirmasi password wajib diisi jika Anda memasukkan password baru.',
                'vendor_id.exists' => 'Vendor yang dipilih tidak valid.',
            ]);

            // Menentukan peran (role) berdasarkan jabatan yang baru (jika jabatan diubah)
            $role = match ($validatedData['jabatan']) {
                'manager', 'asisten manager analis', 'asisten manager preparator' => 'manajemen',
                'admin' => 'admin',
                default => 'personil',
            };

            // Menyiapkan data dasar untuk diupdate
            $updateData = [
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'jenis_kelamin' => $validatedData['jenis_kelamin'],
                'jabatan' => $validatedData['jabatan'],
                'role' => $role,
                'tanggal_mulai_bekerja' => $validatedData['tanggal_mulai_bekerja'],
                'vendor_id' => $validatedData['vendor_id'] ?? null,
            ];

            // Update password HANYA jika field password baru diisi
            if (!empty($validatedData['password'])) {
                $updateData['password'] = Hash::make($validatedData['password']);
            }

            // Proses upload dan update file tanda tangan jika ada file baru yang diunggah
            if ($request->hasFile('signature_image')) {
                // Hapus file tanda tangan lama jika ada
                if ($personil->signature_path && Storage::disk('public')->exists($personil->signature_path)) {
                    Storage::disk('public')->delete($personil->signature_path);
                }
                // Simpan file tanda tangan baru
                $path = $request->file('signature_image')->store('signatures', 'public');
                $updateData['signature_path'] = $path; // Simpan path baru ke data update
            } elseif ($request->input('remove_signature_image') == '1' && $personil->signature_path) {
                // Jika ada input untuk menghapus signature (misal dari checkbox) dan ada signature lama
                Storage::disk('public')->delete($personil->signature_path);
                $updateData['signature_path'] = null; // Set path menjadi null
            }


            // Lakukan update pada record pengguna
            $personil->update($updateData);
            DB::commit();

            Alert::success('Sukses Diperbarui', 'Data akun pengguna telah berhasil diperbarui.');
            return redirect()->route('personil.index');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            // Menangani exception validasi dan menampilkan pesan error pertama dari validator
            $errorMessage = $e->validator->errors()->first();
            Alert::error('Gagal Validasi', $errorMessage);
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            // Menangani error umum lainnya
            Log::error("Error saat memperbarui User ID {$personil->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Sistem', 'Terjadi kesalahan saat memperbarui data pengguna. Silakan coba lagi.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Menghapus data pengguna dari database.
     *
     * @param  \App\Models\User  $personil Instance User yang akan dihapus.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke halaman daftar pengguna dengan pesan status.
     */
    public function destroy(User $personil)
    {
        // Otorisasi: Hanya Admin yang boleh menghapus pengguna.
        // $this->authorize('delete', $personil);
        // Atau cek role langsung jika tidak ada policy spesifik:
        if (Auth::user()->role !== 'admin') {
            Alert::error('Akses Ditolak', 'Anda tidak memiliki izin untuk menghapus pengguna.');
            return redirect()->route('personil.index');
        }
        // Tambahan: Jangan biarkan admin menghapus dirinya sendiri
        if (Auth::id() === $personil->id) {
            Alert::error('Aksi Tidak Diizinkan', 'Anda tidak dapat menghapus akun Anda sendiri.');
            return redirect()->route('personil.index');
        }


        DB::beginTransaction();
        try {
            $userName = $personil->name; // Simpan nama untuk pesan
            // Hapus file tanda tangan jika ada sebelum menghapus record user
            if ($personil->signature_path && Storage::disk('public')->exists($personil->signature_path)) {
                Storage::disk('public')->delete($personil->signature_path);
            }
            // Hapus foto profil jika ada
            if ($personil->photo_path && Storage::disk('public')->exists($personil->photo_path)) {
                Storage::disk('public')->delete($personil->photo_path);
            }

            User::destroy($personil->id); // Hapus pengguna berdasarkan ID
            // Atau $personil->delete();

            DB::commit();
            Alert::success('Sukses Dihapus', 'Akun pengguna "' . $userName . '" telah berhasil dihapus.');
            // Menggunakan with() untuk flash message standar Laravel jika diperlukan oleh view,
            // namun Alert::success() dari SweetAlert biasanya sudah cukup.
            // return redirect()->route('personil.index')->with('success', 'Pengguna berhasil dihapus.');
            return redirect()->route('personil.index');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat menghapus User ID {$personil->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Menghapus', 'Terjadi kesalahan saat menghapus pengguna. Pastikan pengguna tidak memiliki data terkait yang penting.');
            return redirect()->route('personil.index');
        }
    }
}

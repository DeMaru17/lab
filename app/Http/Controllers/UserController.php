<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RealRashid\SweetAlert\Facades\Alert;

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
        return view('Personil.create'); // Tampilkan form tambah pengguna
    }

    // Menyimpan pengguna baru ke database
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8|confirmed',
                'password_confirmation' => 'required|same:password',
                'jabatan' => 'required|in:manager,asisten manager,preparator,analis,mekanik,admin',
            ], [
                'password.confirmed' => 'Password dan konfirmasi password tidak cocok.',
                'password_confirmation.same' => 'Konfirmasi password tidak sesuai.',
            ]);

            $role = match ($request->jabatan) {
                'manager', 'asisten manager' => 'manajemen',
                'admin' => 'admin',
                default => 'personil',
            };

            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'jabatan' => $request->jabatan,
                'role' => $role,
            ]);

            Alert::success('Sukses', 'Akun Telah dibuat');
            return redirect()->route('personil.index')->with('success', 'Pengguna berhasil ditambahkan.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Alert::error('Gagal', $e->validator->errors()->first());
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
        return view('personil.edit', ['user' => $personil]); // Mengirim data ke view. Di dalam view nanti, kamu bisa akses $user untuk menampilkan data user/personil tersebut
    }


    // Memperbarui data pengguna di database
    public function update(Request $request, User $personil)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $personil->id,
                'jabatan' => 'required|in:manager,asisten manager,preparator,analis,mekanik,admin',
                'password' => 'nullable|min:8|confirmed', // Validasi untuk password opsional
                'password_confirmation' => 'nullable|same:password', // Validasi konfirmasi password
            ], [
                'password.confirmed' => 'Password dan konfirmasi password tidak cocok.',
                'password_confirmation.same' => 'Konfirmasi password tidak sesuai.',
            ]);

            $role = match ($request->jabatan) {
                'manager', 'asisten manager' => 'manajemen',
                'admin' => 'admin',
                default => 'personil',
            };

            // Update data pengguna
            $personil->update([
                'name' => $request->name,
                'email' => $request->email,
                'jabatan' => $request->jabatan,
                'role' => $role,
                // Update password jika diisi
                'password' => $request->password ? Hash::make($request->password) : $personil->password,
            ]);

            Alert::success('Sukses', 'Akun Telah diperbarui');
            return redirect()->route('personil.index')->with('success', 'Pengguna berhasil diperbarui.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Alert::error('Gagal', $e->validator->errors()->first());
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

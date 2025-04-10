<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use RealRashid\SweetAlert\Facades\Alert;


class RegisterController extends Controller
{
    /**
     * Menampilkan form registrasi.
     *
     * @return \Illuminate\View\View
     *
     * 1. Mengarahkan user ke halaman form registrasi.
     */
    public function index()
    {
        return view('auth.register'); // Tampilkan view 'register' yang berisi form registrasi
    }

    /**
     * Menyimpan data registrasi user baru ke dalam database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * 1. Melakukan validasi terhadap input yang diberikan user, seperti nama, email, dan password.
     * 2. Membuat user baru di database dengan data yang sudah divalidasi.
     * 3. Mengenkripsi password menggunakan `Hash` sebelum menyimpannya.
     * 4. Menampilkan notifikasi sukses menggunakan SweetAlert.
     * 5. Mengarahkan user ke halaman login dengan pesan sukses setelah registrasi berhasil.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required',
                'email' => 'required|email',
                'password' => 'required|min:8',
                'password_confirmation' => 'required|same:password',
            ], [
                'password_confirmation.same' => 'Konfirmasi password tidak sesuai.'
            ]);

            // Simpan user
            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            Alert::success('Sukses', 'Akun Telah dibuat');
            return redirect()->route('login')->with('success', 'Akun berhasil dibuat!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Jika validasi gagal, misalnya password tidak sama
            Alert::error('Gagal Register', 'Periksa kembali isian Anda');

            return redirect()->back();
        }
    }
}

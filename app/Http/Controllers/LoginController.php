<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Facade untuk layanan autentikasi Laravel
use RealRashid\SweetAlert\Facades\Alert; // Untuk menampilkan notifikasi SweetAlert
use Illuminate\Support\Facades\Log; // Ditambahkan untuk logging (opsional)

/**
 * Class LoginController
 *
 * Mengelola proses autentikasi pengguna, termasuk menampilkan halaman login,
 * memvalidasi kredensial, melakukan upaya login, dan menangani logout.
 *
 * @package App\Http\Controllers
 */
class LoginController extends Controller
{
    /**
     * Menampilkan halaman form login.
     * Jika pengguna sudah login, mereka akan diarahkan ke dashboard.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        // Jika pengguna sudah terotentikasi, arahkan ke dashboard
        if (Auth::check()) {
            return redirect()->route('dashboard.index'); // Asumsi nama route dashboard adalah 'dashboard.index'
        }
        // Jika belum, tampilkan halaman login
        return view('auth.login'); // Pastikan path view ini benar
    }

    /**
     * Menangani upaya login dari pengguna.
     * Method ini akan memvalidasi input email dan password,
     * mencoba melakukan autentikasi, dan mengarahkan pengguna
     * ke dashboard jika berhasil, atau kembali ke halaman login dengan pesan error jika gagal.
     *
     * @param  \Illuminate\Http\Request  $request Data request dari form login.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan ke dashboard atau kembali ke form login.
     */
    public function actionLogin(Request $request)
    {
        // Validasi input email dan password
        $request->validate([
            'email' => 'required|email', // Email wajib diisi dan harus format email yang valid
            'password' => 'required|min:8', // Password wajib diisi dan minimal 8 karakter
        ], [
            // Pesan error kustom (opsional)
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal harus 8 karakter.',
        ]);

        // Mengambil hanya kredensial 'email' dan 'password' dari request
        $credentials = $request->only(['email', 'password']);

        // Mencoba melakukan autentikasi pengguna dengan kredensial yang diberikan
        if (Auth::attempt($credentials)) {
            // Jika autentikasi berhasil:
            $request->session()->regenerate(); // Regenerasi session ID untuk keamanan

            // Menggunakan helper toast dari SweetAlert untuk notifikasi singkat
            toast('Berhasil Masuk', 'success')->position('top-end')->timerProgressBar();
            Log::info("User {$credentials['email']} logged in successfully.");

            // Mengarahkan pengguna ke halaman yang dituju sebelumnya (jika ada) atau ke dashboard
            return redirect()->intended('dashboard'); // 'dashboard' adalah nama route atau URI
        }

        // Jika autentikasi gagal:
        Log::warning("Failed login attempt for email: {$credentials['email']}.");
        // Menggunakan Alert::error dari SweetAlert untuk notifikasi error yang lebih menonjol
        Alert::error('Gagal Masuk', 'Periksa kembali isian Anda. Email atau password salah.');
        // Kembali ke halaman login dengan pesan error spesifik untuk ditampilkan di form
        return redirect()->back()->withErrors(['login_error' => 'Login gagal. Mohon periksa kembali email dan password Anda!'])->withInput($request->except('password'));
        // withInput($request->except('password')) agar email tetap terisi tapi password dikosongkan
    }

    /**
     * Menangani proses logout pengguna.
     * Session pengguna akan diakhiri dan pengguna akan diarahkan ke halaman login.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse Mengarahkan ke halaman login.
     */
    public  function logout(Request $request)
    {
        $userName = Auth::user() ? Auth::user()->name : 'User'; // Ambil nama user sebelum logout untuk log

        Auth::logout(); // Melakukan logout pengguna

        $request->session()->invalidate(); // Membatalkan session saat ini
        $request->session()->regenerateToken(); // Membuat token CSRF baru

        Log::info("User {$userName} logged out.");
        // Mengarahkan ke halaman login dengan pesan sukses (bisa menggunakan Alert atau session flash standar)
        // Alert::success('Berhasil Keluar', 'Anda telah berhasil keluar dari sistem.'); // Opsi dengan SweetAlert
        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar.'); // Menggunakan session flash standar Laravel
    }
}

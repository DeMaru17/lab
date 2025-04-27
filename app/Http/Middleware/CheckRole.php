<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Import Auth Facade
use Symfony\Component\HttpFoundation\Response;
use RealRashid\SweetAlert\Facades\Alert; // Import Alert Facade

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * Memeriksa apakah role pengguna yang login sesuai dengan role yang diizinkan.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string ...$roles // Menangkap satu atau lebih role yang diizinkan sebagai argumen
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // 1. Periksa apakah user sudah login
        if (!Auth::check()) {
            // Jika belum login, redirect ke halaman login
            return redirect()->route('login');
        }

        // 2. Ambil role user yang sedang login
        $userRole = Auth::user()->role; // Asumsi kolom role ada di model User

        // 3. Periksa apakah role user ada di dalam daftar $roles yang diizinkan
        //    Parameter $roles akan berisi ['admin'] atau ['manajemen'] atau ['admin', 'manajemen']
        //    tergantung bagaimana kita definisikan di route.
        if (!in_array($userRole, $roles)) {
            // Jika role tidak sesuai, tampilkan error dan redirect
            Alert::error('Akses Ditolak', 'Anda tidak memiliki hak akses untuk halaman ini.');
            // Redirect ke halaman sebelumnya atau dashboard
            // return redirect()->back();
            return redirect()->route('dashboard.index'); // Atau route lain yang sesuai
        }

        // 4. Jika role sesuai, lanjutkan request ke controller/middleware berikutnya
        return $next($request);
    }
}

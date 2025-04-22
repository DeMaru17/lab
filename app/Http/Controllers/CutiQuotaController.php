<?php

namespace App\Http\Controllers;

use App\Models\CutiQuota;
use App\Models\User;      // Diperlukan untuk Order By
use App\Models\JenisCuti; // Diperlukan untuk Order By
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CutiQuotaController extends Controller
{
    /**
     * Menampilkan daftar kuota cuti (semua data relevan) untuk DataTables.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'personil') {
            // --- Logika untuk Personil ---
            // Ambil SEMUA kuota miliknya, diurutkan
            $cutiQuota = CutiQuota::with('jenisCuti')
                ->where('user_id', $user->id)
                ->orderBy(JenisCuti::select('nama_cuti')->whereColumn('jenis_cuti.id', 'cuti_quota.jenis_cuti_id'))
                ->get();
        } else {
            // --- Logika untuk Admin / Manajemen ---
            // Mulai query dasar
            $query = CutiQuota::with('jenisCuti', 'user');

            // Terapkan filter pencarian jika ada
            if ($request->filled('search')) {
                $searchTerm = '%' . $request->search . '%';
                $query->whereHas('user', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm);
                });
            }

            // Terapkan pengurutan
            $query->orderBy(
                User::select('name')
                    ->whereColumn('users.id', 'cuti_quota.user_id')
                    ->limit(1)
            )
                ->orderBy(
                    JenisCuti::select('nama_cuti')
                        ->whereColumn('jenis_cuti.id', 'cuti_quota.jenis_cuti_id')
                        ->limit(1)
                );

            // Ambil SEMUA hasil query yang sesuai
            $cutiQuota = $query->get();
        }

        // Kirim data (sekarang Collection, bukan Paginator) ke view
        return view('cuti.quota.index', compact('cutiQuota'));
    }

    // Method update() tetap sama, JANGAN LUPA OTORISASI
    public function update(Request $request, $id)
    {
        // ... (Kode Otorisasi Update ) ...
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Anda tidak memiliki hak akses untuk melakukan tindakan ini.');
        }

        $request->validate([
            'durasi_cuti' => 'required|integer|min:0',
        ]);

        $cutiQuota = CutiQuota::findOrFail($id);
        $cutiQuota->durasi_cuti = $request->durasi_cuti;
        $cutiQuota->save();

        return redirect()->route('cuti-quota.index')->with('success', 'Kuota cuti berhasil diperbarui.');
    }
}

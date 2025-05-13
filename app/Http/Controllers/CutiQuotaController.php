<?php

namespace App\Http\Controllers;

use App\Models\CutiQuota;
use App\Models\User;      // Diperlukan untuk pengurutan (Order By) pada Admin/Manajemen
use App\Models\JenisCuti; // Diperlukan untuk pengurutan (Order By)
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert; // Ditambahkan untuk notifikasi (sesuai CutiController)
use Illuminate\Support\Facades\Log;     // Ditambahkan untuk logging error
use Illuminate\Support\Facades\DB;      // Ditambahkan untuk transaksi database

/**
 * Class CutiQuotaController
 *
 * Mengelola operasi terkait data kuota cuti karyawan.
 * Termasuk menampilkan daftar kuota cuti (disesuaikan berdasarkan peran pengguna)
 * dan memperbarui sisa kuota cuti (hanya oleh Admin).
 *
 * @package App\Http\Controllers
 */
class CutiQuotaController extends Controller
{
    /**
     * Menampilkan daftar kuota cuti.
     * - Untuk pengguna dengan peran 'personil', hanya menampilkan daftar kuota cuti miliknya sendiri.
     * - Untuk pengguna dengan peran 'admin' atau 'manajemen', menampilkan semua data kuota cuti
     * dengan opsi pencarian berdasarkan nama atau email karyawan.
     * Data diurutkan berdasarkan nama karyawan (untuk admin/manajemen) dan kemudian berdasarkan nama jenis cuti.
     *
     * @param  \Illuminate\Http\Request  $request Data request yang mungkin berisi parameter pencarian.
     * @return \Illuminate\View\View Mengembalikan view 'cuti.quota.index' dengan data kuota cuti.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user Pengguna yang sedang login. */
        $user = Auth::user();

        if ($user->role === 'personil') {
            // --- Logika untuk Pengguna dengan Peran 'personil' ---
            // Mengambil SEMUA kuota cuti milik pengguna yang login, diurutkan berdasarkan nama jenis cuti.
            // Eager load relasi 'jenisCuti' untuk mendapatkan nama jenis cuti.
            $cutiQuota = CutiQuota::with('jenisCuti:id,nama_cuti') // Hanya ambil kolom id dan nama_cuti dari jenisCuti
                ->where('user_id', $user->id)
                // Pengurutan berdasarkan nama jenis cuti melalui subquery
                ->orderBy(
                    JenisCuti::select('nama_cuti')
                        ->whereColumn('jenis_cuti.id', 'cuti_quota.jenis_cuti_id') // Mencocokkan ID jenis cuti
                        ->limit(1) // Subquery harus mengembalikan satu nilai
                )
                ->get(); // Mengambil semua hasil sebagai Collection (bukan Paginator)
        } else {
            // --- Logika untuk Pengguna dengan Peran 'admin' atau 'manajemen' ---
            // Query dasar untuk mengambil semua CutiQuota beserta relasi 'jenisCuti' dan 'user'.
            $query = CutiQuota::with(['jenisCuti:id,nama_cuti', 'user:id,name,email']); // Eager load data terkait

            // Menerapkan filter pencarian jika ada input 'search' dari request
            if ($request->filled('search')) {
                $searchTerm = '%' . $request->search . '%'; // Tambahkan wildcard untuk pencarian 'like'
                // Mencari berdasarkan nama atau email pengguna melalui relasi 'user'
                $query->whereHas('user', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm);
                });
            }

            // Menerapkan pengurutan: pertama berdasarkan nama pengguna, kedua berdasarkan nama jenis cuti
            $query->orderBy(
                User::select('name') // Subquery untuk mengambil nama pengguna
                    ->whereColumn('users.id', 'cuti_quota.user_id') // Mencocokkan ID pengguna
                    ->limit(1)
            )
                ->orderBy(
                    JenisCuti::select('nama_cuti') // Subquery untuk mengambil nama jenis cuti
                        ->whereColumn('jenis_cuti.id', 'cuti_quota.jenis_cuti_id')
                        ->limit(1)
                );

            // Mengambil SEMUA hasil query yang sesuai (bukan dipaginasi)
            $cutiQuota = $query->get();
        }

        // Mengirim data (sekarang berupa Collection) ke view 'cuti.quota.index'
        return view('cuti.quota.index', compact('cutiQuota'));
    }

    /**
     * Memperbarui sisa kuota cuti untuk entri CutiQuota tertentu.
     * Method ini hanya dapat diakses oleh pengguna dengan peran 'admin'.
     *
     * @param  \Illuminate\Http\Request  $request Data request yang berisi 'durasi_cuti' baru.
     * @param  int  $id ID dari record CutiQuota yang akan diperbarui.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke halaman daftar kuota cuti dengan pesan status.
     */
    public function update(Request $request, $id) // Parameter $id adalah ID CutiQuota
    {
        // Otorisasi: Pastikan hanya Admin yang bisa melakukan update.
        // Alternatif: Gunakan Policy: $this->authorize('update', CutiQuota::find($id));
        if (Auth::user()->role !== 'admin') {
            // Jika bukan admin, kembalikan error 403 (Forbidden)
            Log::warning("CutiQuotaController@update: Upaya akses update kuota cuti oleh non-admin. User ID: " . Auth::id());
            Alert::error('Akses Ditolak', 'Anda tidak memiliki hak akses untuk melakukan tindakan ini.');
            return redirect()->route('cuti-quota.index'); // Atau ke halaman lain yang sesuai
            // abort(403, 'Anda tidak memiliki hak akses untuk melakukan tindakan ini.'); // Ini akan menampilkan halaman error 403
        }

        // Validasi input: 'durasi_cuti' harus ada, berupa integer, dan minimal 0.
        $request->validate([
            'durasi_cuti' => 'required|integer|min:0',
        ], [
            'durasi_cuti.required' => 'Sisa kuota wajib diisi.',
            'durasi_cuti.integer' => 'Sisa kuota harus berupa angka.',
            'durasi_cuti.min' => 'Sisa kuota tidak boleh kurang dari 0.',
        ]);

        DB::beginTransaction(); // Memulai transaksi database
        try {
            // Cari record CutiQuota berdasarkan ID, atau lempar error jika tidak ditemukan
            $cutiQuota = CutiQuota::findOrFail($id);
            $cutiQuota->durasi_cuti = $request->durasi_cuti; // Update durasi cuti
            $cutiQuota->save(); // Simpan perubahan ke database

            DB::commit(); // Simpan transaksi jika berhasil

            Log::info("CutiQuota ID {$id} berhasil diperbarui oleh Admin ID " . Auth::id() . ". Durasi baru: {$request->durasi_cuti}");
            Alert::success('Sukses', 'Kuota cuti berhasil diperbarui.'); // Menggunakan Alert dari RealRashid
            // Mengganti with('success', ...) dengan Alert facade
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Log::error("CutiQuotaController@update: CutiQuota dengan ID {$id} tidak ditemukan.");
            Alert::error('Data Tidak Ditemukan', 'Data kuota cuti yang ingin Anda ubah tidak ditemukan.');
        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan transaksi jika terjadi error lain
            Log::error("Error saat memperbarui CutiQuota ID {$id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Update', 'Terjadi kesalahan saat memperbarui kuota cuti. Silakan coba lagi.');
        }

        return redirect()->route('cuti-quota.index'); // Mengarahkan kembali ke halaman daftar kuota cuti
    }
}

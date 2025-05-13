<?php

namespace App\Http\Controllers;

use App\Models\PerjalananDinas; // Model untuk data perjalanan dinas
use App\Models\User; // Model User, digunakan untuk memilih pengguna saat Admin membuat/mengedit
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Untuk mendapatkan pengguna yang sedang login
use RealRashid\SweetAlert\Facades\Alert; // Untuk menampilkan notifikasi SweetAlert
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Trait untuk menggunakan $this->authorize() dari Policy
use Illuminate\Support\Facades\Log; // Untuk logging error dan informasi
use Illuminate\Support\Facades\DB; // Untuk transaksi database

/**
 * Class PerjalananDinasController
 *
 * Mengelola semua operasi yang berkaitan dengan data perjalanan dinas karyawan.
 * Ini mencakup pembuatan, penampilan daftar, pengeditan, dan penghapusan data perjalanan dinas.
 * Akses ke setiap operasi diatur oleh PerjalananDinasPolicy.
 * Model PerjalananDinas memiliki event listener (booted method) untuk menghitung 'lama_dinas'
 * dan memberikan kuota cuti khusus secara otomatis setelah perjalanan selesai.
 *
 * @package App\Http\Controllers
 */
class PerjalananDinasController extends Controller
{
    use AuthorizesRequests; // Mengaktifkan penggunaan method authorize() dari Laravel Policy

    /**
     * Menampilkan daftar semua data perjalanan dinas.
     * - Untuk pengguna dengan peran 'personil', hanya menampilkan daftar perjalanan dinas miliknya sendiri.
     * - Untuk pengguna dengan peran 'admin' atau 'manajemen', dapat menampilkan semua data
     * dan mendukung fitur pencarian berdasarkan jurusan atau nama pengguna.
     * Data dipaginasi untuk tampilan yang lebih baik.
     *
     * @param  \Illuminate\Http\Request  $request Data request yang mungkin berisi parameter pencarian.
     * @return \Illuminate\View\View Mengembalikan view 'perjalanan-dinas.index' dengan data perjalanan dinas.
     */
    public function index(Request $request)
    {
        // Otorisasi: Memastikan pengguna berhak melihat daftar perjalanan dinas.
        // Biasanya, Policy 'viewAny' akan mengizinkan akses ke halaman index,
        // kemudian data yang ditampilkan difilter berdasarkan peran di sini.
        // $this->authorize('viewAny', PerjalananDinas::class); // Bisa ditambahkan jika ada logic khusus di policy viewAny

        /** @var \App\Models\User $user Pengguna yang sedang login. */
        $user = Auth::user();
        // Query dasar untuk mengambil data PerjalananDinas beserta relasi 'user' (hanya kolom id dan name)
        $query = PerjalananDinas::with('user:id,name');

        if ($user->role === 'personil') {
            // Jika pengguna adalah personil, filter hanya menampilkan perjalanan dinas milik pengguna tersebut
            $query->where('user_id', $user->id);
        }
        // Untuk Admin & Manajemen, secara default bisa melihat semua (tidak ada filter peran tambahan di sini,
        // karena diasumsikan policy 'viewAny' sudah mengatur siapa yang boleh lihat apa).

        // Fitur pencarian (jika ada input 'search' dan pengguna bukan 'personil')
        $searchTerm = $request->input('search');
        if ($searchTerm && in_array($user->role, ['admin', 'manajemen'])) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('jurusan', 'like', '%' . $searchTerm . '%') // Cari berdasarkan kolom 'jurusan'
                    ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', '%' . $searchTerm . '%')); // Cari berdasarkan nama pengguna
                // Tambahkan pencarian untuk kolom lain jika diperlukan
            });
        }

        // Mengambil data perjalanan dinas dengan urutan berdasarkan tanggal berangkat terbaru, lalu dipaginasi
        $perjalananDinas = $query->orderBy('tanggal_berangkat', 'desc')->paginate(15);

        // Jika ada pencarian, sertakan parameter pencarian ke link pagination
        if ($searchTerm) {
            $perjalananDinas->appends(['search' => $searchTerm]);
        }

        return view('perjalanan-dinas.index', compact('perjalananDinas'));
    }

    /**
     * Menampilkan form untuk membuat data perjalanan dinas baru.
     * Memerlukan otorisasi 'create' dari PerjalananDinasPolicy.
     * Jika yang membuat adalah Admin, akan ada dropdown untuk memilih pengguna.
     *
     * @return \Illuminate\View\View Mengembalikan view 'perjalanan-dinas.create'.
     */
    public function create()
    {
        // Otorisasi: Memastikan pengguna berhak membuat data perjalanan dinas baru.
        $this->authorize('create', PerjalananDinas::class);

        $users = []; // Array untuk menampung daftar pengguna (untuk Admin)
        if (Auth::user()->role === 'admin') {
            // Jika Admin, ambil daftar semua pengguna untuk dipilih di form
            $users = User::orderBy('name')->pluck('name', 'id');
        }

        return view('perjalanan-dinas.create', compact('users'));
    }

    /**
     * Menyimpan data perjalanan dinas baru ke database setelah validasi.
     * Status awal perjalanan dinas diatur sebagai 'berlangsung'.
     * Perhitungan 'lama_dinas' akan dihandle oleh event 'saving' di model PerjalananDinas.
     *
     * @param  \Illuminate\Http\Request  $request Data dari form pembuatan perjalanan dinas.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke halaman daftar perjalanan dinas dengan pesan status.
     */
    public function store(Request $request)
    {
        // Otorisasi: Memastikan pengguna berhak menyimpan data perjalanan dinas baru.
        $this->authorize('create', PerjalananDinas::class);

        // Validasi data input dari form
        $validatedData = $request->validate([
            // Jika Admin yang input, 'user_id' wajib ada dan valid.
            // Jika Personil yang input, 'user_id' tidak perlu dikirim (akan diambil dari Auth::id()).
            'user_id' => Auth::user()->role === 'admin' ? 'required|exists:users,id' : 'nullable',
            'tanggal_berangkat' => 'required|date',
            'perkiraan_tanggal_pulang' => 'required|date|after_or_equal:tanggal_berangkat', // Tgl pulang tidak boleh sebelum tgl berangkat
            'jurusan' => 'required|string|max:255', // Tujuan perjalanan
            // Kolom 'tanggal_pulang', 'lama_dinas', 'status', 'is_processed' akan dihandle oleh model atau saat update.
        ]);

        // Tentukan user_id: jika Admin, ambil dari input; jika Personil, ambil dari Auth::id().
        $userId = Auth::user()->role === 'admin' ? $validatedData['user_id'] : Auth::id();

        // Menyiapkan data untuk disimpan ke tabel 'perjalanan_dinas'
        $createData = [
            'user_id' => $userId,
            'tanggal_berangkat' => $validatedData['tanggal_berangkat'],
            'perkiraan_tanggal_pulang' => $validatedData['perkiraan_tanggal_pulang'],
            'jurusan' => $validatedData['jurusan'],
            'status' => 'berlangsung', // Status awal saat perjalanan dinas baru dibuat
            // 'lama_dinas' akan dihitung secara otomatis oleh event 'saving' di model PerjalananDinas.
        ];

        DB::beginTransaction(); // Memulai transaksi database
        try {
            PerjalananDinas::create($createData); // Membuat record baru
            DB::commit(); // Simpan perubahan jika berhasil

            Alert::success('Sukses', 'Data perjalanan dinas berhasil ditambahkan.');
            return redirect()->route('perjalanan-dinas.index'); // Mengarahkan kembali ke halaman daftar
        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan transaksi jika terjadi error
            Log::error("Error saat membuat Perjalanan Dinas baru untuk User ID {$userId}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal', 'Gagal menyimpan data perjalanan dinas. Silakan coba lagi.');
            return redirect()->back()->withInput(); // Kembali ke form dengan input sebelumnya
        }
    }

    /**
     * Display the specified resource.
     * (Method ini tidak digunakan sesuai informasi dari pengguna,
     * karena detail biasanya dilihat dari daftar atau saat edit).
     * Jika akan digunakan, pastikan ada view 'perjalanan-dinas.show'.
     */
    // public function show(PerjalananDinas $perjalananDinas)
    // {
    //     $this->authorize('view', $perjalananDinas);
    //     // return view('perjalanan-dinas.show', compact('perjalananDinas'));
    // }

    /**
     * Menampilkan form untuk mengedit data perjalanan dinas yang sudah ada.
     * Memerlukan otorisasi 'update' dari PerjalananDinasPolicy.
     *
     * @param  \App\Models\PerjalananDinas  $perjalananDina Instance PerjalananDinas yang akan diedit (via Route Model Binding).
     * Nama parameter '$perjalananDina' sesuai dengan yang didefinisikan di route.
     * @return \Illuminate\View\View Mengembalikan view 'perjalanan-dinas.edit'.
     */
    public function edit(PerjalananDinas $perjalananDina) // Parameter '$perjalananDina' harus konsisten dengan nama di route
    {
        // Otorisasi: Memastikan pengguna berhak mengedit data ini.
        $this->authorize('update', $perjalananDina);

        $users = []; // Untuk dropdown pilihan user jika Admin yang mengedit (meskipun user_id biasanya tidak diubah)
        if (Auth::user()->role === 'admin') {
            $users = User::orderBy('name')->pluck('name', 'id');
        }
        // Mengirim data perjalanan dinas yang akan diedit ke view
        return view('perjalanan-dinas.edit', compact('perjalananDina', 'users'));
    }

    /**
     * Memperbarui data perjalanan dinas yang sudah ada di database.
     * Memerlukan otorisasi 'update' dari PerjalananDinasPolicy.
     * Perhitungan ulang 'lama_dinas' dan pemberian kuota cuti khusus (jika status 'selesai')
     * akan dihandle oleh event 'saving' dan 'saved' di model PerjalananDinas.
     *
     * @param  \Illuminate\Http\Request  $request Data dari form edit perjalanan dinas.
     * @param  \App\Models\PerjalananDinas  $perjalananDina Instance PerjalananDinas yang akan diupdate.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali dengan pesan status.
     */
    public function update(Request $request, PerjalananDinas $perjalananDina)
    {
        // Otorisasi: Memastikan pengguna berhak memperbarui data ini.
        $this->authorize('update', $perjalananDina);

        // Validasi data input (sesuaikan field yang boleh diubah saat edit)
        $validatedData = $request->validate([
            // 'user_id' biasanya tidak diubah saat edit, kecuali oleh Admin dengan pertimbangan khusus.
            'tanggal_berangkat' => 'required|date',
            'perkiraan_tanggal_pulang' => 'required|date|after_or_equal:tanggal_berangkat',
            'tanggal_pulang' => 'nullable|date|after_or_equal:tanggal_berangkat', // Boleh null jika belum pulang
            'jurusan' => 'required|string|max:255',
            'status' => 'required|in:berlangsung,selesai', // Status yang valid
            // Kolom 'is_processed' tidak diinput oleh pengguna, dihandle oleh sistem.
        ]);

        // Data yang akan diupdate
        $updateData = $validatedData;
        // Jika user_id tidak boleh diubah saat edit, pastikan tidak masuk ke $updateData
        // unset($updateData['user_id']); // Contoh jika user_id tidak boleh diubah

        DB::beginTransaction();
        try {
            // Melakukan update pada record perjalanan dinas.
            // Event 'saving' di model PerjalananDinas akan otomatis menghitung ulang 'lama_dinas'.
            // Event 'saved' di model akan otomatis mengecek dan menambah kuota cuti khusus jika status 'selesai' dan belum diproses.
            $perjalananDina->update($updateData);
            DB::commit();

            Alert::success('Sukses', 'Data perjalanan dinas berhasil diperbarui.');
            return redirect()->route('perjalanan-dinas.index'); // Mengarahkan kembali ke halaman daftar
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat memperbarui Perjalanan Dinas ID {$perjalananDina->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal', 'Gagal memperbarui data perjalanan dinas. Silakan coba lagi.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Menghapus data perjalanan dinas dari database.
     * Memerlukan otorisasi 'delete' dari PerjalananDinasPolicy (biasanya hanya Admin).
     *
     * @param  \App\Models\PerjalananDinas  $perjalananDinas Instance PerjalananDinas yang akan dihapus.
     * Nama parameter diubah dari $perjalananDina menjadi $perjalananDinas agar konsisten.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke halaman daftar perjalanan dinas dengan pesan status.
     */
    public function destroy(PerjalananDinas $perjalananDinas) // Parameter diubah ke $perjalananDinas
    {
        // Otorisasi: Memastikan pengguna berhak menghapus data ini.
        $this->authorize('delete', $perjalananDinas);

        try {
            $perjalananDinas->delete(); // Menghapus record dari database
            Alert::success('Sukses Dihapus', 'Data perjalanan dinas berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error("Error saat menghapus Perjalanan Dinas ID {$perjalananDinas->id}: " . $e->getMessage());
            Alert::error('Gagal Menghapus', 'Gagal menghapus data perjalanan dinas.');
        }
        return redirect()->route('perjalanan-dinas.index'); // Mengarahkan kembali ke halaman daftar
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Overtime;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage; // Meskipun tidak digunakan langsung di kode ini, mungkin relevan jika ada upload terkait lembur
use Carbon\Carbon;
use RealRashid\SweetAlert\Facades\Alert; // Untuk notifikasi SweetAlert
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Untuk otorisasi via Policy
use Barryvdh\DomPDF\Facade\Pdf; // Untuk generate PDF
use ZipArchive; // Untuk membuat file ZIP (bulk download PDF)
use Illuminate\Support\Str;
use App\Mail\OvertimeStatusNotificationMail; // Mailable untuk notifikasi status lembur individu
use Illuminate\Support\Facades\Mail; // Facade untuk mengirim email
use App\Mail\BulkOvertimeStatusNotificationMail; // Mailable untuk notifikasi status lembur massal
use Illuminate\Support\Facades\Validator; // Facade untuk validasi input
use Illuminate\Validation\Rule; // Import Rule class for validation
use Illuminate\Support\Facades\Gate; // Import Gate facade for authorization

/**
 * Class OvertimeController
 *
 * Mengelola semua operasi yang berkaitan dengan pengajuan, persetujuan,
 * dan pelaporan data lembur karyawan.
 *
 * @package App\Http\Controllers
 */
class OvertimeController extends Controller
{
    use AuthorizesRequests; // Mengaktifkan penggunaan Policy untuk otorisasi

    /**
     * Batas maksimal total menit lembur bulanan per karyawan.
     * Digunakan untuk memberikan peringatan saat pengajuan.
     * 3240 menit = 54 jam.
     * @const int
     */
    private const MONTHLY_OVERTIME_LIMIT_MINUTES = 3240;

    /**
     * Menampilkan daftar pengajuan lembur.
     * Tampilan dan data yang ditampilkan disesuaikan berdasarkan peran pengguna (Personil, Admin, Manajemen).
     * Mendukung filter berdasarkan pengguna, vendor, status, dan rentang tanggal.
     *
     * @param  \Illuminate\Http\Request  $request Data request yang mungkin berisi parameter filter.
     * @return \Illuminate\View\View Mengembalikan view 'overtimes.index' dengan data lembur yang dipaginasi.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user Pengguna yang sedang login. */
        $user = Auth::user();
        $perPage = 50; // Jumlah item per halaman untuk pagination
        $searchTerm = $request->input('search'); // Untuk fitur pencarian cepat

        // --- Menyiapkan Data untuk Dropdown Filter (hanya untuk Admin/Manajemen) ---
        $users = collect(); // Default koleksi kosong
        $vendors = collect(); // Default koleksi kosong
        if (in_array($user->role, ['admin', 'manajemen'])) {
            $users = User::orderBy('name')->select('id', 'name')->get();
            $vendors = Vendor::orderBy('name')->select('id', 'name')->get();
        }

        // --- Mengambil Nilai Filter dari Request ---
        $selectedUserId = $request->input('filter_user_id');
        $selectedVendorId = $request->input('filter_vendor_id');
        $selectedStatus = $request->input('filter_status');
        $startDate = $request->input('filter_start_date');
        $endDate = $request->input('filter_end_date');

        // --- Query Dasar untuk Mengambil Data Lembur ---
        $query = Overtime::with([
            'user:id,name,jabatan,vendor_id', // Eager load data user
            'user.vendor:id,name',          // Eager load data vendor dari user
            'rejecter:id,name'              // Eager load data user yang menolak (jika ada)
        ]);

        // --- Menerapkan Filter Berdasarkan Peran dan Input ---
        if ($user->role === 'personil') {
            // Personil hanya melihat data lembur miliknya sendiri
            $query->where('user_id', $user->id);
        }

        // Filter berdasarkan rentang tanggal jika kedua tanggal diisi
        if ($startDate && $endDate) {
            try {
                $query->whereBetween('tanggal_lembur', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()]);
            } catch (\Exception $e) {
                Log::warning('Format tanggal tidak valid untuk filter lembur: ' . $e->getMessage());
                // Abaikan filter tanggal jika formatnya salah
            }
        }

        // Filter berdasarkan status pengajuan jika dipilih
        if ($selectedStatus) {
            $query->where('status', $selectedStatus);
        }

        // Filter berdasarkan pengguna spesifik (hanya untuk Admin/Manajemen)
        if ($selectedUserId && in_array($user->role, ['admin', 'manajemen'])) {
            $query->where('user_id', $selectedUserId);
        }

        // Filter berdasarkan vendor (hanya untuk Admin/Manajemen)
        if ($selectedVendorId && in_array($user->role, ['admin', 'manajemen'])) {
            if ($selectedVendorId === 'is_null') { // Handle untuk karyawan internal (tanpa vendor)
                $query->whereHas('user', fn($q) => $q->whereNull('vendor_id'));
            } else {
                $query->whereHas('user', fn($q) => $q->where('vendor_id', $selectedVendorId));
            }
        }

        // Filter berdasarkan pencarian cepat (uraian pekerjaan atau nama pengguna)
        // Hanya berlaku jika pengguna bukan 'personil'
        if ($searchTerm && $user->role !== 'personil') {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('uraian_pekerjaan', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', '%' . $searchTerm . '%'));
            });
        }
        // --- Akhir Menerapkan Filter ---

        // Mengurutkan hasil dan melakukan pagination
        $overtimes = $query->orderBy('tanggal_lembur', 'desc') // Urutan utama berdasarkan tanggal lembur terbaru
            ->orderBy('created_at', 'desc') // Urutan kedua berdasarkan waktu pengajuan terbaru
            ->paginate($perPage);

        // Menyertakan parameter filter ke link pagination agar filter tetap aktif
        $overtimes->appends($request->except('page'));

        // Data total lembur bulanan (opsional, bisa dipertimbangkan performanya jika tidak esensial di halaman index)
        $monthlyTotals = [];
        // Jika Anda masih memerlukan $monthlyTotals, logika untuk mengambilnya bisa diletakkan di sini.
        // Contoh:
        // if ($user->role === 'personil') {
        //     $monthlyTotals[$user->id] = $this->getCurrentMonthOvertimeTotal($user);
        // } elseif (in_array($user->role, ['admin', 'manajemen']) && $overtimes->isNotEmpty()) {
        //     $userIdsOnPage = $overtimes->pluck('user_id')->unique()->toArray();
        //     $usersOnPage = User::whereIn('id', $userIdsOnPage)->with('vendor')->get()->keyBy('id');
        //     foreach ($usersOnPage as $listUser) {
        //         $monthlyTotals[$listUser->id] = $this->getCurrentMonthOvertimeTotal($listUser);
        //     }
        // }

        return view('overtimes.index', compact(
            'overtimes',
            'monthlyTotals', // Akan kosong jika logika di atas tidak diaktifkan
            'users',         // Untuk dropdown filter user
            'vendors',       // Untuk dropdown filter vendor
            'selectedUserId',
            'selectedVendorId',
            'selectedStatus',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Menampilkan form untuk membuat pengajuan lembur baru.
     *
     * @return \Illuminate\View\View Mengembalikan view 'overtimes.create'.
     */
    public function create()
    {
        // Otorisasi: Memastikan pengguna berhak membuat pengajuan lembur.
        $this->authorize('create', Overtime::class);

        $users = []; // Untuk dropdown pilihan user jika Admin yang membuat
        if (Auth::user()->role === 'admin') {
            // Admin bisa memilih user dengan peran 'personil' atau 'admin' lainnya
            $users = User::whereIn('role', ['personil', 'admin'])->orderBy('name')->pluck('name', 'id');
        }

        // Hitung total lembur pengguna saat ini untuk bulan berjalan (untuk peringatan)
        $currentMonthTotal = 0;
        if (in_array(Auth::user()->role, ['personil', 'admin'])) { // Jika Admin juga bisa mengajukan untuk diri sendiri
            $currentMonthTotal = $this->getCurrentMonthOvertimeTotal(Auth::user());
        }
        // Tentukan apakah perlu menampilkan peringatan batas lembur
        $showWarning = ($currentMonthTotal >= self::MONTHLY_OVERTIME_LIMIT_MINUTES);

        return view('overtimes.create', compact('users', 'showWarning', 'currentMonthTotal'));
    }

    /**
     * Menyimpan pengajuan lembur baru ke database.
     * Melakukan validasi input, pengecekan overlap tanggal, dan pengecekan batas lembur bulanan.
     * Durasi lembur akan dihitung secara otomatis oleh event model 'saving' pada model Overtime.
     *
     * @param  \Illuminate\Http\Request  $request Data dari form pengajuan lembur.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali dengan pesan status.
     */
    public function store(Request $request)
    {
        // Otorisasi: Memastikan pengguna berhak menyimpan pengajuan lembur.
        $this->authorize('create', Overtime::class);

        // Validasi data input dari form
        $validatedData = $request->validate([
            'user_id' => Auth::user()->role === 'admin' ? 'required|exists:users,id' : 'nullable', // Wajib jika Admin, opsional jika Personil
            'tanggal_lembur' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i', // Format jam:menit
            'jam_selesai' => 'required|date_format:H:i', // Validasi 'after' akan dihandle lebih detail di logika atau model
            'uraian_pekerjaan' => 'required|string|max:1000',
        ]);

        // Tentukan user_id berdasarkan peran yang mengajukan
        $userId = Auth::user()->role === 'admin' ? $validatedData['user_id'] : Auth::id();
        /** @var \App\Models\User $targetUser Pengguna yang lemburnya diajukan. */
        $targetUser = User::with('vendor')->find($userId); // Eager load vendor untuk perhitungan periode
        $tanggalLembur = Carbon::parse($validatedData['tanggal_lembur']);

        // --- Validasi Overlap Tanggal Lembur ---
        // Memeriksa apakah sudah ada pengajuan lembur lain (pending/approved) pada tanggal yang sama untuk user tersebut.
        if ($this->checkOverlap($userId, $tanggalLembur, $tanggalLembur)) {
            Alert::error('Tanggal Bertabrakan', 'Anda sudah memiliki pengajuan lembur lain (pending/approved) pada tanggal ' . $tanggalLembur->translatedFormat('d F Y') . '.');
            return back()->withInput();
        }
        // --- Akhir Validasi Overlap ---

        // Hitung durasi lembur sementara untuk pengecekan batas bulanan
        // Perhitungan durasi final yang memperhitungkan cross midnight dilakukan di model event.
        $tempStartTime = Carbon::parse($validatedData['jam_mulai']);
        $tempEndTime = Carbon::parse($validatedData['jam_selesai']);
        if ($tempEndTime->lessThanOrEqualTo($tempStartTime)) { // Asumsi sederhana jika jam selesai < jam mulai, berarti hari berikutnya
            $tempEndTime->addDay();
        }
        $newDurationMinutes = $tempStartTime->diffInMinutes($tempEndTime);

        // Cek batas total lembur bulanan
        $currentMonthTotal = $this->getCurrentMonthOvertimeTotal($targetUser, $tanggalLembur);
        $totalAfterSubmit = $currentMonthTotal + $newDurationMinutes;
        $exceedsLimit = ($totalAfterSubmit > self::MONTHLY_OVERTIME_LIMIT_MINUTES);

        // Menyiapkan data untuk disimpan ke database
        $createData = $validatedData;
        $createData['user_id'] = $userId;
        $createData['status'] = 'pending'; // Status awal pengajuan adalah 'pending'

        DB::beginTransaction();
        try {
            Overtime::create($createData); // Model event 'saving' akan menghitung dan mengisi 'durasi_menit'
            DB::commit();

            // Memberikan notifikasi sukses kepada pengguna
            if ($exceedsLimit) {
                // Jika melebihi batas, berikan pesan sukses beserta peringatan
                $hoursOver = round(($totalAfterSubmit - self::MONTHLY_OVERTIME_LIMIT_MINUTES) / 60, 1);
                Alert::success('Berhasil Diajukan', 'Pengajuan lembur berhasil disimpan.')
                    ->persistent(true) // Membuat alert tetap ada sampai ditutup manual
                    ->warning('Perhatian!', 'Total jam lembur bulan ini telah melebihi batas 54 jam (Sekitar ' . $hoursOver . ' jam lebih dari batas).');
            } else {
                Alert::success('Berhasil Diajukan', 'Pengajuan lembur berhasil disimpan.');
            }

            return redirect()->route('overtimes.index'); // Mengarahkan ke halaman daftar lembur

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat membuat pengajuan lembur baru untuk User ID {$userId}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Sistem', 'Gagal menyimpan data lembur. Silakan coba lagi.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Menampilkan form untuk mengedit pengajuan lembur yang sudah ada.
     *
     * @param  \App\Models\Overtime  $overtime Instance lembur yang akan diedit (via Route Model Binding).
     * @return \Illuminate\View\View Mengembalikan view 'overtimes.edit'.
     */
    public function edit(Overtime $overtime)
    {
        // Otorisasi: Memastikan pengguna berhak mengedit pengajuan lembur ini.
        $this->authorize('update', $overtime);

        $users = []; // Untuk dropdown pilihan user jika Admin yang mengedit (meskipun user_id tidak diubah)
        if (Auth::user()->role === 'admin') {
            $users = User::orderBy('name')->pluck('name', 'id');
        }
        return view('overtimes.edit', compact('overtime', 'users'));
    }

    /**
     * Memperbarui data pengajuan lembur yang sudah ada di database.
     * Setelah diupdate, status akan direset ke 'pending' dan semua approval sebelumnya akan dihapus.
     *
     * @param  \Illuminate\Http\Request  $request Data dari form edit lembur.
     * @param  \App\Models\Overtime  $overtime Instance lembur yang akan diupdate.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali dengan pesan status.
     */
    public function update(Request $request, Overtime $overtime)
    {
        // Otorisasi: Memastikan pengguna berhak memperbarui pengajuan ini.
        $this->authorize('update', $overtime);

        // Hanya bisa edit jika status 'pending' atau 'rejected'
        if (!in_array($overtime->status, ['pending', 'rejected'])) {
            Alert::error('Tidak Dapat Diedit', 'Pengajuan lembur ini tidak dapat diedit karena statusnya bukan pending atau rejected.');
            return redirect()->route('overtimes.index');
        }

        // Validasi input
        $validatedData = $request->validate([
            'tanggal_lembur' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i',
            'uraian_pekerjaan' => 'required|string|max:1000',
        ]);

        $startDate = Carbon::parse($validatedData['tanggal_lembur']);
        $startTime = Carbon::parse($validatedData['jam_mulai']);
        $endTime = Carbon::parse($validatedData['jam_selesai']);
        $userId = $overtime->user_id; // User ID tidak diubah saat edit
        /** @var \App\Models\User $targetUser Pengguna yang lemburnya diedit. */
        $targetUser = $overtime->user()->with('vendor')->first(); // Eager load vendor

        // Hitung durasi baru sementara untuk validasi batas bulanan
        $tempEndTimeForCalc = $endTime->copy();
        if ($tempEndTimeForCalc->lessThanOrEqualTo($startTime)) {
            $tempEndTimeForCalc->addDay();
        }
        $newLamaMenit = $startTime->diffInMinutes($tempEndTimeForCalc);

        // Validasi ulang batas lembur bulanan, dengan mengecualikan durasi lembur ini yang lama
        $currentMonthTotal = $this->getCurrentMonthOvertimeTotal($targetUser, $startDate, $overtime->id); // Kecualikan ID lembur ini dari perhitungan
        $totalAfterUpdate = $currentMonthTotal + $newLamaMenit;
        $exceedsLimit = ($totalAfterUpdate > self::MONTHLY_OVERTIME_LIMIT_MINUTES);

        // Validasi ulang overlap tanggal, dengan mengecualikan ID lembur ini
        if ($this->checkOverlap($userId, $startDate, $startDate, $overtime->id)) {
            Alert::error('Tanggal Bertabrakan', 'Tanggal lembur yang Anda ajukan bertabrakan dengan pengajuan lain.');
            return back()->withInput();
        }

        // --- Proses Update Data ---
        DB::beginTransaction();
        try {
            $updateData = $validatedData;
            // Reset status dan semua field approval/rejection karena pengajuan diedit
            $updateData['status'] = 'pending';
            $updateData['notes'] = null;
            $updateData['approved_by_asisten_id'] = null;
            $updateData['approved_at_asisten'] = null;
            $updateData['approved_by_manager_id'] = null;
            $updateData['approved_at_manager'] = null;
            $updateData['rejected_by_id'] = null;
            $updateData['rejected_at'] = null;
            $updateData['last_reminder_sent_at'] = null; // Reset juga timestamp reminder

            // Lakukan update (Model event 'saving' akan menghitung ulang 'durasi_menit')
            $overtime->update($updateData);
            DB::commit();

            // Memberikan notifikasi sukses beserta peringatan jika batas terlewati
            if ($exceedsLimit) {
                $hoursOver = round(($totalAfterUpdate - self::MONTHLY_OVERTIME_LIMIT_MINUTES) / 60, 1);
                Alert::success('Berhasil Diperbarui', 'Pengajuan lembur berhasil diperbarui dan diajukan ulang.')
                    ->persistent(true)
                    ->warning('Perhatian!', 'Total jam lembur bulan ini telah melebihi batas 54 jam (Sekitar ' . $hoursOver . ' jam lebih).');
            } else {
                Alert::success('Berhasil Diperbarui', 'Pengajuan lembur berhasil diperbarui dan diajukan ulang.');
            }
            return redirect()->route('overtimes.index');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat memperbarui Overtime ID {$overtime->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Update', 'Gagal menyimpan perubahan pengajuan lembur.');
            return back()->withInput();
        }
    }

    /**
     * Menghapus data pengajuan lembur dari database.
     * Hanya Admin yang diizinkan melakukan ini.
     *
     * @param  \App\Models\Overtime  $overtime Instance lembur yang akan dihapus.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar lembur.
     */
    public function destroy(Overtime $overtime)
    {
        // Otorisasi: Memastikan pengguna (Admin) berhak menghapus.
        $this->authorize('delete', $overtime);
        try {
            $overtime->delete();
            Alert::success('Sukses Dihapus', 'Data lembur berhasil dihapus secara permanen.');
        } catch (\Exception $e) {
            Log::error("Error saat menghapus Overtime ID {$overtime->id}: " . $e->getMessage());
            Alert::error('Gagal Menghapus', 'Gagal menghapus data lembur.');
        }
        return redirect()->route('overtimes.index');
    }

    /**
     * Membatalkan pengajuan lembur oleh pengguna yang mengajukan.
     *
     * @param  \App\Models\Overtime  $overtime Instance lembur yang akan dibatalkan.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar lembur.
     */
    public function cancel(Overtime $overtime)
    {
        // Otorisasi: Memastikan pengguna berhak membatalkan pengajuan ini.
        $this->authorize('cancel', $overtime);

        // Validasi tambahan: Hanya pemilik dan status tertentu yang boleh cancel
        if (Auth::id() !== $overtime->user_id || !in_array($overtime->status, ['pending', 'approved'])) {
            Alert::error('Proses Tidak Valid', 'Anda tidak dapat membatalkan pengajuan lembur ini atau statusnya tidak memungkinkan.');
            return redirect()->route('overtimes.index');
        }
        // Untuk lembur, pembatalan biasanya bisa dilakukan kapan saja sebelum diproses untuk penggajian.
        // Tidak ada logika pengembalian kuota seperti cuti.

        DB::beginTransaction();
        try {
            $overtime->status = 'cancelled'; // Ubah status menjadi 'cancelled'
            $overtime->save();
            DB::commit();
            Alert::success('Berhasil Dibatalkan', 'Pengajuan lembur telah berhasil dibatalkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat membatalkan Overtime ID {$overtime->id}: " . $e->getMessage());
            Alert::error('Gagal Membatalkan', 'Gagal membatalkan pengajuan lembur.');
        }
        return redirect()->route('overtimes.index');
    }


    // --- Method-method untuk Alur Persetujuan Lembur ---

    /**
     * Menampilkan daftar pengajuan lembur yang menunggu persetujuan Asisten Manager.
     * Daftar difilter berdasarkan scope jabatan Asisten Manager yang login.
     *
     * @return \Illuminate\View\View Mengembalikan view 'overtimes.approval.asisten_list'.
     */
    public function listForAsisten()
    {
        // Otorisasi akses halaman ini biasanya dihandle oleh middleware 'role:manajemen' pada route
        // dan policy 'viewAssistantApprovalList' jika ada.
        /** @var \App\Models\User $user Asisten Manager yang sedang login. */
        $user = Auth::user();
        $this->authorize('viewAsistenApprovalList', Overtime::class); // Memanggil policy

        $perPage = 50;
        // Query mengambil lembur yang statusnya 'pending' dan belum ada approval L1
        $query = Overtime::where('status', 'pending')
            ->whereNull('approved_by_asisten_id') // Pastikan belum pernah diproses Asisten lain
            ->with(['user:id,name,jabatan', 'user.vendor:id,name']); // Eager load data user & vendor

        // Filter berdasarkan scope jabatan Asisten Manager
        if ($user->jabatan === 'asisten manager analis') {
            $query->whereHas('user', fn($q) => $q->whereIn('jabatan', ['analis', 'admin']));
        } elseif ($user->jabatan === 'asisten manager preparator') {
            $query->whereHas('user', fn($q) => $q->whereIn('jabatan', ['preparator', 'mekanik', 'admin']));
        } else {
            // Jika jabatan Asisten tidak sesuai, jangan tampilkan apa-apa
            $query->whereRaw('1 = 0');
        }

        $pendingOvertimes = $query->orderBy('created_at', 'asc')->paginate($perPage); // Urutkan berdasarkan pengajuan terlama

        // Mengambil total lembur bulanan untuk setiap user di halaman ini (untuk peringatan)
        $monthlyTotals = [];
        if ($pendingOvertimes->isNotEmpty()) {
            $userIdsOnPage = $pendingOvertimes->pluck('user_id')->unique()->toArray();
            // Ambil data user sekali saja untuk efisiensi
            $usersOnPage = User::whereIn('id', $userIdsOnPage)->with('vendor')->get()->keyBy('id');
            foreach ($usersOnPage as $listUser) {
                // Asumsi $listUser adalah objek User yang sudah di-load dengan vendor
                $monthlyTotals[$listUser->id] = $this->getCurrentMonthOvertimeTotal($listUser, Carbon::parse($pendingOvertimes->where('user_id', $listUser->id)->first()->tanggal_lembur));
            }
        }

        return view('overtimes.approval.asisten_list', compact('pendingOvertimes', 'monthlyTotals'));
    }

    /**
     * Memproses persetujuan pengajuan lembur oleh Asisten Manager (Level 1).
     *
     * @param  \App\Models\Overtime  $overtime Instance lembur yang akan disetujui.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar approval Asisten.
     */
    public function approveAsisten(Overtime $overtime)
    {
        // Otorisasi menggunakan Policy
        $this->authorize('approveAsisten', $overtime);
        $assistant = Auth::user();

        if ($overtime->status !== 'pending') {
            Alert::warning('Sudah Diproses', 'Pengajuan ini sudah diproses atau statusnya tidak lagi pending.');
            return redirect()->route('overtimes.approval.asisten.list');
        }

        DB::beginTransaction();
        try {
            $overtime->approved_by_asisten_id = $assistant->id;
            $overtime->approved_at_asisten = now();
            $overtime->status = 'pending_manager_approval'; // Status berikutnya menunggu Manager
            $overtime->save();
            DB::commit();

            // Opsional: Kirim email notifikasi ke Manager
            // ...

            Alert::success('Berhasil Disetujui (L1)', 'Pengajuan lembur telah disetujui dan diteruskan ke Manager.');
            Log::info("Overtime ID {$overtime->id} approved (L1) by Assistant ID {$assistant->id}.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error approving L1 Overtime ID {$overtime->id} by Assistant {$assistant->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Approve', 'Gagal memproses persetujuan level 1.');
        }
        return redirect()->route('overtimes.approval.asisten.list');
    }

    /**
     * Menampilkan daftar pengajuan lembur yang menunggu persetujuan final dari Manager.
     *
     * @return \Illuminate\View\View Mengembalikan view 'overtimes.approval.manager_list'.
     */
    public function listForManager()
    {
        // Otorisasi akses halaman ini
        $this->authorize('viewManagerApprovalList', Overtime::class); // Memanggil policy
        /** @var \App\Models\User $user Manager yang sedang login. */
        $user = Auth::user();

        // Pastikan hanya Manager yang bisa mengakses (double check dengan policy)
        if ($user->jabatan !== 'manager') {
            Alert::error('Akses Ditolak', 'Hanya Manager yang dapat mengakses halaman ini.');
            return redirect()->route('dashboard.index');
        }

        $perPage = 50;
        // Query mengambil lembur yang statusnya 'pending_manager_approval'
        $pendingOvertimesManager = Overtime::where('status', 'pending_manager_approval')
            ->whereNull('approved_by_manager_id') // Pastikan belum diapprove Manager
            ->whereNull('rejected_by_id')       // Dan belum direject
            ->with(['user:id,name,jabatan', 'approverAsisten:id,name']) // Eager load user & approver L1
            ->orderBy('approved_at_asisten', 'asc') // Urutkan berdasarkan approval Asisten terlama
            ->paginate($perPage);

        // Mengambil total lembur bulanan untuk setiap user di halaman ini (untuk peringatan)
        $monthlyTotals = [];
        if ($pendingOvertimesManager->isNotEmpty()) {
            $userIdsOnPage = $pendingOvertimesManager->pluck('user_id')->unique()->toArray();
            $usersOnPage = User::whereIn('id', $userIdsOnPage)->with('vendor')->get()->keyBy('id');
            foreach ($usersOnPage as $listUser) {
                $monthlyTotals[$listUser->id] = $this->getCurrentMonthOvertimeTotal($listUser, Carbon::parse($pendingOvertimesManager->where('user_id', $listUser->id)->first()->tanggal_lembur));
            }
        }

        return view('overtimes.approval.manager_list', compact('pendingOvertimesManager', 'monthlyTotals'));
    }

    /**
     * Memproses persetujuan final pengajuan lembur oleh Manager (Level 2).
     *
     * @param  \App\Models\Overtime  $overtime Instance lembur yang akan disetujui.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar approval Manager.
     */
    public function approveManager(Overtime $overtime)
    {
        // Otorisasi menggunakan Policy
        $this->authorize('approveManager', $overtime);
        /** @var \App\Models\User $approver Manager yang melakukan approval. */
        $approver = Auth::user();

        if ($overtime->status !== 'pending_manager_approval') {
            Alert::warning('Sudah Diproses', 'Pengajuan ini sudah diproses atau statusnya tidak lagi menunggu persetujuan Manager.');
            return redirect()->route('overtimes.approval.manager.list');
        }

        DB::beginTransaction();
        try {
            $overtime->approved_by_manager_id = $approver->id;
            $overtime->approved_at_manager = now();
            $overtime->status = 'approved'; // Status final: disetujui
            $overtime->rejected_by_id = null; // Hapus data rejecter jika ada
            $overtime->rejected_at = null;
            $overtime->notes = $overtime->notes ? $overtime->notes . ' | Approved by Manager.' : 'Approved by Manager.';
            $overtime->save();
            DB::commit();

            // Kirim Email Notifikasi ke Pengaju bahwa lemburnya disetujui
            try {
                /** @var \App\Models\User $applicant Pengguna yang mengajukan lembur. */
                $applicant = $overtime->user()->first();
                if ($applicant && $applicant->email) {
                    Mail::to($applicant->email)->queue(new OvertimeStatusNotificationMail($overtime, 'approved', $approver));
                    Log::info("Notifikasi persetujuan lembur (ID: {$overtime->id}) telah diantrikan untuk {$applicant->email}");
                } else {
                    Log::warning("Tidak dapat mengirim notifikasi persetujuan lembur: User atau email tidak ditemukan untuk Overtime ID {$overtime->id}");
                }
            } catch (\Exception $e) {
                Log::error("Gagal mengirim email notifikasi persetujuan lembur untuk Overtime ID {$overtime->id}: " . $e->getMessage());
            }
            Alert::success('Berhasil Disetujui Final', 'Pengajuan lembur untuk ' . ($overtime->user?->name ?? 'N/A') . ' telah disetujui.');
            Log::info("Overtime ID {$overtime->id} approved (Final) by Manager ID {$approver->id}.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error approving L2 Overtime ID {$overtime->id} by Manager {$approver->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Approve Final', 'Gagal memproses persetujuan final.');
        }
        return redirect()->route('overtimes.approval.manager.list');
    }

    /**
     * Menolak pengajuan lembur. Dapat dilakukan oleh Asisten Manager atau Manager.
     *
     * @param  \Illuminate\Http\Request  $request Data request yang berisi alasan penolakan.
     * @param  \App\Models\Overtime  $overtime Instance lembur yang akan ditolak.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar approval yang relevan.
     */
    public function reject(Request $request, Overtime $overtime)
    {
        // Otorisasi menggunakan Policy
        $this->authorize('reject', $overtime);
        /** @var \App\Models\User $rejecter Pengguna yang melakukan penolakan. */
        $rejecter = Auth::user();

        // Validasi input alasan penolakan
        $validated = $request->validate(['notes' => 'required|string|min:2|max:500'], [
            'notes.required' => 'Alasan penolakan wajib diisi.',
            'notes.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        // Validasi status sebelum diproses
        if (!in_array($overtime->status, ['pending', 'pending_manager_approval'])) {
            Alert::warning('Tidak Dapat Diproses', 'Pengajuan lembur ini tidak dalam status yang bisa ditolak.');
            return redirect()->back();
        }

        DB::beginTransaction();
        try {
            $overtime->rejected_by_id = $rejecter->id;
            $overtime->rejected_at = now();
            $overtime->status = 'rejected'; // Status diubah menjadi ditolak
            $overtime->notes = $validated['notes']; // Simpan alasan penolakan

            // Jika Manager yang menolak, reset approval Asisten sebelumnya (jika ada)
            if ($rejecter->jabatan === 'manager' && $overtime->approved_by_asisten_id) {
                $overtime->approved_by_asisten_id = null;
                $overtime->approved_at_asisten = null;
            }
            $overtime->save();
            DB::commit();

            // Kirim Email Notifikasi ke Pengaju bahwa lemburnya ditolak
            try {
                /** @var \App\Models\User $applicant Pengguna yang mengajukan lembur. */
                $applicant = $overtime->user()->first();
                if ($applicant && $applicant->email) {
                    Mail::to($applicant->email)->queue(new OvertimeStatusNotificationMail($overtime, 'rejected', $rejecter));
                    Log::info("Notifikasi penolakan lembur (ID: {$overtime->id}) telah diantrikan untuk {$applicant->email}");
                } else {
                    Log::warning("Tidak dapat mengirim notifikasi penolakan lembur: User atau email tidak ditemukan untuk Overtime ID {$overtime->id}");
                }
            } catch (\Exception $e) {
                Log::error("Gagal mengirim email notifikasi penolakan lembur untuk Overtime ID {$overtime->id}: " . $e->getMessage());
            }
            Alert::success('Berhasil Ditolak', 'Pengajuan lembur telah ditolak.');
            Log::info("Overtime ID {$overtime->id} rejected by User ID {$rejecter->id}. Reason: {$validated['notes']}");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error rejecting Overtime ID {$overtime->id} by User {$rejecter->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Menolak', 'Gagal memproses penolakan.');
        }

        // Redirect kembali ke halaman daftar approval yang sesuai dengan peran penolak
        if ($rejecter->jabatan === 'manager') {
            return redirect()->route('overtimes.approval.manager.list');
        } else { // Asisten Manager
            return redirect()->route('overtimes.approval.asisten.list');
        }
    }

    /**
     * Memproses persetujuan massal (bulk approve) untuk pengajuan lembur.
     * Dapat dilakukan oleh Asisten Manager atau Manager.
     *
     * @param  \Illuminate\Http\Request  $request Data request berisi ID lembur yang dipilih dan level approval.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar approval yang relevan.
     */
    public function bulkApprove(Request $request)
    {
        // Otorisasi umum untuk fitur bulk approve (akan memanggil OvertimePolicy@bulkApprove)
        $this->authorize('bulkApprove', Overtime::class);

        // Validasi input dasar
        $validator = Validator::make($request->all(), [
            'selected_ids'   => 'required|array|min:1', // Minimal 1 ID harus dipilih
            'selected_ids.*' => 'required|integer|exists:overtimes,id', // Setiap ID harus ada di tabel overtimes
            'approval_level' => ['required', Rule::in(['asisten', 'manager'])], // Level approval yang valid
        ]);

        if ($validator->fails()) {
            Alert::error('Input Tidak Valid', $validator->errors()->first());
            return redirect()->back();
        }

        $validated = $validator->validated();
        $selectedIds = $validated['selected_ids'];
        $approvalLevel = $validated['approval_level'];
        /** @var \App\Models\User $approver Pengguna yang melakukan bulk approve. */
        $approver = Auth::user();

        // Otorisasi tambahan berdasarkan level dan jabatan approver
        if ($approvalLevel === 'asisten' && !in_array($approver->jabatan, ['asisten manager analis', 'asisten manager preparator'])) {
            Alert::error('Akses Ditolak', 'Anda bukan Asisten Manager yang berwenang untuk melakukan persetujuan massal level ini.');
            return redirect()->back();
        }
        if ($approvalLevel === 'manager' && $approver->jabatan !== 'manager') {
            Alert::error('Akses Ditolak', 'Anda bukan Manager yang berwenang untuk melakukan persetujuan massal level ini.');
            return redirect()->back();
        }

        // Ambil data lembur yang akan diproses, termasuk relasi user untuk notifikasi email
        $overtimesToProcess = Overtime::with(['user:id,name,jabatan,email'])
            ->whereIn('id', $selectedIds)
            ->get();

        // Inisialisasi counter dan array untuk feedback
        $successCount = 0;
        $failCount = 0;
        $emailFailCount = 0;
        $failedDetails = []; // Untuk menyimpan detail kegagalan per item
        $approvedRequestsByUser = []; // Untuk mengelompokkan lembur yang disetujui per user (untuk notifikasi email bulk)

        DB::beginTransaction();
        try {
            foreach ($overtimesToProcess as $overtime) {
                $canProcess = false; // Flag apakah item ini bisa diproses
                $errorMessage = null; // Pesan error spesifik untuk item ini
                $newStatus = null; // Status baru jika diproses
                $pengajuJabatan = $overtime->user?->jabatan; // Jabatan pengguna yang mengajukan lembur

                // Logika untuk approval level Asisten
                if ($approvalLevel === 'asisten') {
                    if ($overtime->status === 'pending') { // Asisten hanya approve yang statusnya 'pending'
                        // Cek scope jabatan Asisten vs Pengaju
                        if (($approver->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) ||
                            ($approver->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin']))
                        ) {
                            // Otorisasi per item menggunakan Policy (Gate::allows memanggil method policy)
                            if (Gate::allows('approveAsisten', $overtime)) {
                                $canProcess = true;
                                $newStatus = 'pending_manager_approval'; // Status setelah diapprove Asisten
                            } else {
                                $errorMessage = "Tidak berwenang (Policy Asisten).";
                            }
                        } else {
                            $errorMessage = "Tidak berwenang (Scope Jabatan Asisten).";
                        }
                    } else {
                        $errorMessage = "Status bukan 'pending'.";
                    }

                    if ($canProcess) {
                        $overtime->approved_by_asisten_id = $approver->id;
                        $overtime->approved_at_asisten = now();
                        $overtime->status = $newStatus;
                        $overtime->save();
                        $successCount++;
                        // Tidak ada notifikasi email di level Asisten untuk bulk approve
                    }
                }
                // Logika untuk approval level Manager
                elseif ($approvalLevel === 'manager') {
                    // Manager bisa approve yang statusnya 'pending_manager_approval'
                    if ($overtime->status === 'pending_manager_approval') {
                        // Otorisasi per item menggunakan Policy
                        if (Gate::allows('approveManager', $overtime)) {
                            $canProcess = true;
                            $newStatus = 'approved'; // Status final
                        } else {
                            $errorMessage = "Tidak berwenang (Policy Manager).";
                        }
                    } else {
                        $errorMessage = "Status bukan 'pending_manager_approval'.";
                    }

                    if ($canProcess) {
                        $overtime->approved_by_manager_id = $approver->id;
                        $overtime->approved_at_manager = now();
                        $overtime->status = $newStatus;
                        $overtime->rejected_by_id = null; // Reset data reject jika ada
                        $overtime->rejected_at = null;
                        $overtime->notes = $overtime->notes ? $overtime->notes . ' | Approved by Manager (Bulk).' : 'Approved by Manager (Bulk).';
                        $overtime->save();
                        $successCount++;

                        // Kumpulkan data lembur yang disetujui final untuk notifikasi email bulk
                        $applicantUser = $overtime->user;
                        if ($applicantUser) {
                            $approvedRequestsByUser[$applicantUser->id]['user'] = $applicantUser; // Simpan objek User
                            $approvedRequestsByUser[$applicantUser->id]['requests'][] = $overtime->fresh(); // Simpan objek Overtime yang sudah diupdate
                        }
                    }
                }

                // Jika item ini tidak bisa diproses, catat sebagai gagal
                if (!$canProcess) {
                    $failCount++;
                    $userName = $overtime->user?->name ?? 'N/A';
                    $reason = $errorMessage ?? 'Gagal diproses (tidak memenuhi syarat).';
                    $failedDetails[] = "ID {$overtime->id} ({$userName}): " . $reason;
                    Log::warning("Bulk Approve Overtime Gagal: ID {$overtime->id}. Alasan: {$reason}. Approver: {$approver->id}");
                }
            } // Akhir loop foreach $overtimesToProcess

            DB::commit(); // Simpan semua perubahan ke database jika tidak ada error fatal

            // --- Kirim Email Notifikasi Ringkasan SETELAH commit jika approval oleh Manager ---
            if ($approvalLevel === 'manager' && !empty($approvedRequestsByUser)) {
                Log::info("Bulk Approve Overtimes: Mengantrikan notifikasi persetujuan final untuk " . count($approvedRequestsByUser) . " pengguna.");
                foreach ($approvedRequestsByUser as $userId => $userData) {
                    /** @var \App\Models\User $applicantUser */
                    $applicantUser = $userData['user'];
                    /** @var \Illuminate\Support\Collection $approvedList Koleksi objek Overtime. */
                    $approvedList = collect($userData['requests']);

                    if ($applicantUser && $applicantUser->email && $approvedList->isNotEmpty()) {
                        try {
                            // Menggunakan Mailable khusus untuk notifikasi bulk
                            Mail::to($applicantUser->email)->queue(new BulkOvertimeStatusNotificationMail($approvedList, $approver, $applicantUser));
                            Log::info("Notifikasi Bulk Overtime disetujui telah diantrikan untuk {$applicantUser->email} untuk " . $approvedList->count() . " pengajuan.");
                        } catch (\Exception $e) {
                            $emailFailCount++; // Hitung jumlah email yang gagal diantrikan
                            Log::error("Gagal mengantrikan email notifikasi Bulk Overtime disetujui untuk User ID {$userId}: " . $e->getMessage());
                        }
                    } else {
                        Log::warning("Melewati pengiriman email bulk untuk User ID {$userId} karena data pengguna/email/pengajuan tidak lengkap.");
                        if ($approvedList->isNotEmpty()) $emailFailCount++;
                    }
                }
            }
            // --- Akhir Kirim Email Notifikasi Ringkasan ---

            // Menyiapkan pesan feedback untuk pengguna menggunakan SweetAlert
            $successMessage = "{$successCount} pengajuan lembur berhasil diproses.";
            if ($failCount > 0 || $emailFailCount > 0) {
                $alertMessage = $successMessage;
                if ($failCount > 0) {
                    $errorList = implode("<br>", array_map('htmlspecialchars', $failedDetails)); // Format untuk HTML
                    $alertMessage .= "<br><br>Namun, {$failCount} pengajuan lembur gagal diproses:<br><div style='text-align:left; font-size: smaller; max-height: 150px; overflow-y: auto; border:1px solid #ccc; padding:5px;'><pre style='margin:0; white-space:pre-wrap;'>{$errorList}</pre></div>";
                }
                if ($emailFailCount > 0) {
                    $alertMessage .= "<br><br>Peringatan: {$emailFailCount} notifikasi email gagal diantrikan/dikirim.";
                }
                Alert::html('Proses Selesai Sebagian', $alertMessage, 'warning')->persistent(true, false);
            } else {
                Alert::success('Berhasil Diproses', $successMessage);
            }
        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan semua perubahan jika terjadi error fatal
            Log::error("Error selama proses bulk approve lembur: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Total', 'Terjadi kesalahan sistem saat memproses persetujuan massal. Semua perubahan dibatalkan.');
        }

        // Redirect kembali ke halaman daftar approval yang sesuai dengan level approver
        $redirectRoute = $approvalLevel === 'manager' ? 'overtimes.approval.manager.list' : 'overtimes.approval.asisten.list';
        return redirect()->route($redirectRoute);
    }


    /**
     * Helper method untuk menghitung total durasi lembur (dalam menit) yang sudah disetujui
     * untuk seorang pengguna dalam periode bulan tertentu.
     * Periode bulan ditentukan berdasarkan tanggal target dan aturan vendor (khususnya CSI).
     *
     * @param  \App\Models\User  $user Pengguna yang akan dihitung total lemburnya.
     * @param  \Carbon\Carbon|null  $targetDate Tanggal acuan untuk menentukan periode bulan. Jika null, default ke hari ini.
     * @param  int|null  $excludeOvertimeId ID pengajuan lembur yang ingin dikecualikan dari perhitungan (berguna saat update).
     * @return int Total durasi lembur dalam menit.
     */
    private function getCurrentMonthOvertimeTotal(User $user, ?Carbon $targetDate = null, ?int $excludeOvertimeId = null): int
    {
        $targetDate = $targetDate ?? Carbon::now(config('app.timezone', 'Asia/Jakarta')); // Default ke hari ini jika tidak ada tanggal target

        // Pastikan relasi vendor sudah di-load pada objek User, atau load di sini jika perlu.
        // Ini penting untuk menentukan periode cut-off yang benar.
        $userVendorName = $user->vendor?->name ?? null;
        if (!$user->relationLoaded('vendor') && $user->vendor_id) { // Cek jika relasi belum di-load dan ada vendor_id
            $user->load('vendor:id,name'); // Eager load vendor jika belum
            $userVendorName = $user->vendor?->name ?? null;
        }

        $periodStartDate = null;
        $periodEndDate = null;

        // Menentukan periode start dan end date berdasarkan vendor (khususnya CSI)
        if ($userVendorName === 'PT Cakra Satya Internusa') { // Sesuaikan nama vendor ini
            if ($targetDate->day >= 16) {
                $periodStartDate = $targetDate->copy()->day(16);
                $periodEndDate = $targetDate->copy()->addMonthNoOverflow()->day(15);
            } else {
                $periodStartDate = $targetDate->copy()->subMonthNoOverflow()->day(16);
                $periodEndDate = $targetDate->copy()->day(15);
            }
        } else { // Untuk vendor lain atau pengguna internal, periode adalah awal hingga akhir bulan target
            $periodStartDate = $targetDate->copy()->startOfMonth();
            $periodEndDate = $targetDate->copy()->endOfMonth();
        }

        // Query untuk menghitung total durasi lembur yang sudah 'approved'
        $query = Overtime::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereBetween('tanggal_lembur', [$periodStartDate->toDateString(), $periodEndDate->toDateString()]);

        // Jika ada ID lembur yang ingin dikecualikan (misalnya saat update)
        if ($excludeOvertimeId) {
            $query->where('id', '!=', $excludeOvertimeId);
        }

        return (int) $query->sum('durasi_menit'); // Mengembalikan total durasi dalam menit
    }

    /**
     * Mengunduh formulir lembur yang sudah disetujui dalam format PDF.
     *
     * @param  \App\Models\Overtime  $overtime Instance lembur yang akan diunduh (via Route Model Binding).
     * @return \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\RedirectResponse File PDF atau redirect jika gagal.
     */
    public function downloadOvertimePdf(Overtime $overtime)
    {
        // Otorisasi: Memastikan pengguna berhak mengunduh PDF ini.
        // Akan memanggil method 'downloadPdf' di OvertimePolicy (jika ada).
        $this->authorize('downloadPdf', $overtime);

        // Validasi tambahan: Hanya lembur yang sudah 'approved' yang bisa diunduh PDF-nya.
        if ($overtime->status !== 'approved') {
            Alert::error('Belum Disetujui', 'Formulir PDF hanya bisa diunduh untuk lembur yang sudah disetujui.');
            return redirect()->back();
        }

        // Eager load relasi yang dibutuhkan untuk template PDF
        $overtime->loadMissing([ // Gunakan loadMissing agar tidak load ulang jika sudah ada
            'user' => function ($query) {
                $query->select('id', 'name', 'jabatan', 'signature_path', 'vendor_id')
                    ->with('vendor:id,name,logo_path'); // Ambil juga logo vendor
            },
            'approverAsisten:id,name,jabatan,signature_path', // Ambil juga jabatan & TTD Asisten
            'approverManager:id,name,jabatan,signature_path', // Ambil juga jabatan & TTD Manager
            // 'rejecter:id,name' // Mungkin tidak perlu untuk PDF lembur approved
        ]);

        // Membuat nama file PDF yang unik dan deskriptif
        $tanggalLemburFormatted = $overtime->tanggal_lembur ? Carbon::parse($overtime->tanggal_lembur)->format('dmY') : 'nodate';
        $namaPengajuFormatted = Str::slug($overtime->user?->name ?? 'user', '_'); // Slug nama pengguna
        $filename =  $tanggalLemburFormatted . '_lembur_' . $namaPengajuFormatted . '_id' . $overtime->id . '.pdf'; // Tambahkan ID untuk keunikan

        // Menggunakan view 'overtimes.pdf_template' untuk generate PDF
        // Pastikan view ini sudah ada dan menerima variabel $overtime
        try {
            $pdf = Pdf::loadView('overtimes.pdf_template', compact('overtime'));
            // Opsi: $pdf->setPaper('a4', 'portrait'); // Atur ukuran dan orientasi kertas jika perlu
            return $pdf->download($filename); // Langsung download file PDF
        } catch (\Exception $e) {
            Log::error("Error saat generate PDF Lembur untuk ID {$overtime->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Membuat PDF', 'Terjadi kesalahan saat membuat file PDF lembur. Silakan coba lagi.');
            return redirect()->back();
        }
    }

    /**
     * Mengunduh beberapa formulir lembur (yang sudah dipilih dan disetujui) dalam satu file ZIP.
     * Fitur ini biasanya hanya untuk Admin.
     *
     * @param  \Illuminate\Http\Request  $request Data request berisi array ID lembur yang dipilih.
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\RedirectResponse File ZIP atau redirect jika gagal.
     */
    public function bulkDownloadPdf(Request $request)
    {
        // Validasi input: selected_ids harus ada, berupa array, dan setiap ID harus ada di tabel overtimes
        $validated = $request->validate([
            'selected_ids'   => 'required|array|min:1',
            'selected_ids.*' => 'required|integer|exists:overtimes,id',
        ]);
        $selectedIds = $validated['selected_ids'];

        // Otorisasi: Hanya Admin yang boleh melakukan bulk download.
        // Anda bisa membuat policy khusus 'bulkDownloadPdf' atau cek role langsung.
        if (Auth::user()->role !== 'admin') {
            Alert::error('Akses Ditolak', 'Anda tidak memiliki hak untuk melakukan aksi ini.');
            return redirect()->back();
        }

        // Ambil data lembur yang dipilih, pastikan statusnya 'approved'
        $overtimesToExport = Overtime::with([
            'user' => fn($q) => $q->select('id', 'name', 'jabatan', 'signature_path', 'vendor_id')->with('vendor:id,name,logo_path'),
            'approverAsisten:id,name,jabatan,signature_path',
            'approverManager:id,name,jabatan,signature_path',
        ])->whereIn('id', $selectedIds)
            ->where('status', 'approved') // Hanya ekspor yang sudah disetujui
            ->get();

        if ($overtimesToExport->isEmpty()) {
            Alert::warning('Tidak Ada Data Valid', 'Tidak ada data lembur yang disetujui dan valid untuk diekspor dalam pilihan Anda.');
            return redirect()->back();
        }

        // --- Proses Pembuatan File ZIP ---
        $zip = new ZipArchive;
        $zipFileName = 'bulk_lembur_' . date('dmY_His') . '.zip'; // Nama file ZIP unik
        $tempDir = storage_path('app/temp_pdf_bulk'); // Direktori temporary untuk menyimpan PDF sebelum di-zip
        $zipPath = $tempDir . '/' . $zipFileName; // Path lengkap ke file ZIP

        // Pastikan direktori temporary ada dan bisa ditulis
        if (!Storage::disk('local')->exists('temp_pdf_bulk')) { // Cek di disk local (storage/app)
            Storage::disk('local')->makeDirectory('temp_pdf_bulk');
        }
        if (!is_writable($tempDir)) {
            Log::error("Direktori penyimpanan sementara tidak dapat ditulis: " . $tempDir);
            Alert::error('Error Server', 'Direktori penyimpanan sementara tidak dapat ditulis. Hubungi administrator.');
            return redirect()->back();
        }

        // Buka (atau buat) file ZIP untuk ditulis
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            Log::error("Tidak dapat membuka file ZIP untuk ditulis: " . $zipPath);
            Alert::error('Gagal Membuka ZIP', 'Tidak dapat membuka file ZIP untuk ditulis. Hubungi administrator.');
            return redirect()->back();
        }

        $pdfGenerationErrors = 0; // Counter untuk PDF yang gagal dibuat
        foreach ($overtimesToExport as $overtime) {
            // Format nama file PDF individual di dalam ZIP
            $tanggalLemburFormatted = Carbon::parse($overtime->tanggal_lembur)->format('Ymd');
            $namaPengajuFormatted = Str::slug($overtime->user?->name ?? 'user', '_');
            $pdfFileNameInZip =  $tanggalLemburFormatted . '_lembur_' . $namaPengajuFormatted . '_id' . $overtime->id . '.pdf';

            try {
                // Generate PDF
                $pdf = Pdf::loadView('overtimes.pdf_template', compact('overtime'));
                // Tambahkan konten PDF ke dalam file ZIP
                $zip->addFromString($pdfFileNameInZip, $pdf->output());
            } catch (\Exception $e) {
                $pdfGenerationErrors++;
                Log::error("Gagal generate PDF (Overtime ID {$overtime->id}) dalam proses bulk download: " . $e->getMessage());
                // Lanjutkan ke PDF berikutnya meskipun ada yang gagal
            }
        }

        // Tutup arsip ZIP setelah semua PDF ditambahkan
        $zip->close();

        // Jika semua PDF gagal dibuat ATAU file ZIP tidak ada (seharusnya tidak terjadi jika open berhasil)
        if ($pdfGenerationErrors === $overtimesToExport->count() || !file_exists($zipPath)) {
            Alert::error('Gagal Membuat PDF', 'Tidak ada file PDF yang berhasil dibuat untuk dimasukkan ke dalam ZIP.');
            if (file_exists($zipPath)) unlink($zipPath); // Hapus file ZIP kosong jika terbuat
            return redirect()->back();
        }

        // Kirim file ZIP untuk di-download dan hapus file ZIP dari server setelah terkirim
        return response()->download($zipPath)->deleteFileAfterSend(true);
        // --- Akhir Proses Pembuatan File ZIP ---
    }

    /**
     * Helper method untuk memeriksa apakah ada pengajuan lembur lain yang tumpang tindih
     * pada tanggal yang sama untuk pengguna tertentu.
     *
     * @param  int  $userId ID pengguna.
     * @param  \Carbon\Carbon  $startDate Tanggal mulai lembur (atau tanggal lembur jika hanya satu hari).
     * @param  \Carbon\Carbon  $endDate Tanggal selesai lembur (biasanya sama dengan startDate untuk lembur harian).
     * @param  int|null  $excludeOvertimeId ID pengajuan lembur yang ingin dikecualikan dari pengecekan (berguna saat update).
     * @return bool True jika ada tumpang tindih, false jika tidak.
     */
    private function checkOverlap(int $userId, Carbon $startDate, Carbon $endDate, ?int $excludeOvertimeId = null): bool
    {
        // Untuk lembur, pengecekan overlap biasanya cukup pada tanggal yang sama.
        // Jika lembur bisa berhari-hari, logika ini perlu disesuaikan seperti pada Cuti.
        // Saat ini, kita asumsikan lembur adalah per hari dan cek hanya pada 'tanggal_lembur'.
        $query = Overtime::where('user_id', $userId)
            ->where('tanggal_lembur', $startDate->toDateString()) // Cek hanya pada tanggal lembur yang sama
            ->whereIn('status', ['pending', 'pending_manager_approval', 'approved']); // Hanya cek yang masih aktif/pending

        // Jika sedang mengupdate, kecualikan record lembur yang sedang diedit dari pengecekan
        if ($excludeOvertimeId) {
            $query->where('id', '!=', $excludeOvertimeId);
        }

        return $query->exists(); // Mengembalikan true jika ada record lain yang cocok
    }
}

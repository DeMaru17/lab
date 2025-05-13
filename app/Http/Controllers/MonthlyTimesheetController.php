<?php

namespace App\Http\Controllers;

// Models
use App\Models\MonthlyTimesheet;
use App\Models\Attendance;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Holiday; // Diperlukan jika ada logika terkait libur di sini (meski utama di Command)

// Facades & Helpers
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan; // Untuk memanggil command Artisan
use RealRashid\SweetAlert\Facades\Alert; // Untuk notifikasi SweetAlert
use Barryvdh\DomPDF\Facade\Pdf; // Untuk generate PDF
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Untuk otorisasi via Policy
use Illuminate\Support\Facades\Validator; // Untuk validasi input
use Illuminate\Validation\Rule; // Untuk aturan validasi 'in'
use Illuminate\Support\Facades\Mail; // Untuk mengirim email
use Illuminate\Pagination\LengthAwarePaginator; // Untuk pagination manual
use App\Mail\MonthlyTimesheetStatusNotification; // Mailable untuk notifikasi status timesheet
use Illuminate\Support\Facades\Gate; // Untuk otorisasi via Gate (digunakan di bulkApprove)

/**
 * Class MonthlyTimesheetController
 *
 * Mengelola semua operasi terkait rekapitulasi timesheet bulanan karyawan.
 * Ini mencakup penampilan daftar timesheet, detail, alur persetujuan (Asisten Manager & Manager),
 * penolakan, ekspor ke PDF, serta fitur persetujuan massal dan pemrosesan ulang paksa.
 *
 * @package App\Http\Controllers
 */
class MonthlyTimesheetController extends Controller
{
    use AuthorizesRequests; // Mengaktifkan penggunaan Policy untuk otorisasi

    /**
     * Menampilkan daftar semua rekap timesheet bulanan dengan opsi filter.
     * Ditujukan untuk Admin atau Manajemen Umum yang memiliki hak akses 'viewAny'.
     * Personil akan melihat daftar timesheet miliknya sendiri melalui logika filter di method ini.
     *
     * @param  \Illuminate\Http\Request  $request Data request yang mungkin berisi parameter filter.
     * @return \Illuminate\View\View Mengembalikan view 'monthly_timesheets.index' dengan data timesheet yang dipaginasi.
     */
    public function index(Request $request)
    {
        // Otorisasi: Memastikan pengguna berhak melihat halaman daftar timesheet.
        $this->authorize('viewAny', MonthlyTimesheet::class);

        /** @var \App\Models\User $user Pengguna yang sedang login. */
        $user = Auth::user();
        $perPage = 20; // Jumlah item per halaman untuk pagination

        // Menyiapkan data untuk dropdown filter (hanya untuk Admin/Manajemen)
        $usersForFilter = collect();
        $vendorsForFilter = collect();
        if ($user->role === 'admin' || $user->role === 'manajemen') {
            $usersForFilter = User::orderBy('name')->select('id', 'name')->get();
            $vendorsForFilter = Vendor::orderBy('name')->select('id', 'name')->get();
        }
        $statuses = ['generated', 'pending_asisten', 'pending_manager', 'approved', 'rejected'];

        // Mengambil nilai filter dari request.
        // Default filter bulan dan tahun adalah bulan lalu (untuk tampilan filter, bukan untuk query awal).
        $filterUserId = $request->input('filter_user_id');
        $filterVendorId = $request->input('filter_vendor_id');
        $filterStatus = $request->input('filter_status');
        $filterMonth = $request->input('filter_month', Carbon::now()->subMonthNoOverflow()->month);
        $filterYear = $request->input('filter_year', Carbon::now()->subMonthNoOverflow()->year);

        // Tentukan apakah filter telah diterapkan oleh pengguna
        // Ini true jika salah satu parameter filter_ ada di URL
        $hasFiltered = $request->has('filter_month') ||
            $request->has('filter_year') || // Meskipun ada default, kita anggap aktif jika dikirim
            $request->has('filter_user_id') ||
            $request->has('filter_vendor_id') ||
            $request->has('filter_status');

        if ($user->role === 'personil' && !$request->has('filter_month') && !$request->has('filter_year') && !$request->has('filter_status')) {
            // Jika personil dan belum ada filter eksplisit sama sekali, anggap belum filter
            // Kecuali jika Anda ingin personil langsung melihat data bulan lalu tanpa filter.
            // Untuk konsistensi "tidak ada data sebelum filter", kita set $hasFiltered false jika tidak ada param filter.
            if (!$request->query()) { // Jika tidak ada query string sama sekali
                $hasFiltered = false;
            }
        }


        $timesheets = new LengthAwarePaginator(collect(), 0, $perPage, 1, [
            'path' => $request->url(),
            'query' => $request->query()
        ]);


        if ($hasFiltered) {
            // Query dasar untuk mengambil data MonthlyTimesheet
            $query = MonthlyTimesheet::with([
                'user:id,name,jabatan,vendor_id',
                'user.vendor:id,name',
                'approverAsisten:id,name',
                'approverManager:id,name',
                'rejecter:id,name'
            ]);

            // Menerapkan filter berdasarkan peran pengguna
            if ($user->role === 'personil') {
                $query->where('user_id', $user->id);
            } elseif ($filterUserId && ($user->role === 'admin' || $user->role === 'manajemen')) {
                $query->where('user_id', $filterUserId);
            }

            if ($user->role !== 'personil' && $filterVendorId) {
                if ($filterVendorId === 'is_null') {
                    $query->whereHas('user', fn($q) => $q->whereNull('vendor_id'));
                } else {
                    $query->whereHas('user', fn($q) => $q->where('vendor_id', $filterVendorId));
                }
            }

            if ($filterStatus) {
                $query->where('status', $filterStatus);
            }

            // Untuk filter bulan dan tahun, gunakan nilai dari request jika ada,
            // atau nilai default jika $hasFiltered true tapi param bulan/tahun tidak diubah dari default.
            $queryMonth = $request->input('filter_month', $filterMonth); // Gunakan nilai dari request jika ada
            $queryYear = $request->input('filter_year', $filterYear);   // Gunakan nilai dari request jika ada

            if ($queryMonth && $queryYear) {
                $query->where(function ($q) use ($queryMonth, $queryYear) {
                    $q->where(fn($sub) => $sub->whereMonth('period_start_date', $queryMonth)->whereYear('period_start_date', $queryYear))
                        ->orWhere(fn($sub) => $sub->whereMonth('period_end_date', $queryMonth)->whereYear('period_end_date', $queryYear));
                });
            }

            $timesheets = $query->orderBy('period_start_date', 'desc')
                ->orderBy(User::select('name')->whereColumn('users.id', 'monthly_timesheets.user_id'))
                ->paginate($perPage);

            $timesheets->appends($request->except('page'));
        }

        return view('monthly_timesheets.index', compact(
            'timesheets',
            'usersForFilter',
            'vendorsForFilter',
            'statuses',
            'filterUserId',
            'filterVendorId',
            'filterStatus',
            'filterMonth',     // Untuk mengisi nilai filter di view
            'filterYear',      // Untuk mengisi nilai filter di view
            'hasFiltered'      // Flag untuk view
        ));
    }

    /**
     * Menampilkan daftar rekap timesheet yang menunggu persetujuan Asisten Manager.
     * Daftar difilter berdasarkan scope jabatan Asisten Manager yang login.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View Mengembalikan view 'monthly_timesheets.approval.asisten_list'.
     */
    public function listForAsistenApproval(Request $request)
    {
        /** @var \App\Models\User $user Pengguna (Asisten Manager) yang sedang login. */
        $user = Auth::user();
        $this->authorize('viewAsistenApprovalList', MonthlyTimesheet::class);

        $perPage = 20;
        // Default filter bulan dan tahun adalah bulan lalu.
        $filterMonth = $request->input('filter_month', Carbon::now()->subMonthNoOverflow()->month);
        $filterYear = $request->input('filter_year', Carbon::now()->subMonthNoOverflow()->year);

        // Tentukan apakah filter periode telah diterapkan oleh pengguna
        $hasFiltered = $request->has('filter_month') || $request->has('filter_year');

        // Inisialisasi paginator kosong
        $pendingAsistenTimesheets = new LengthAwarePaginator(collect(), 0, $perPage, 1, [
            'path' => $request->url(),
            'query' => $request->query() // Sertakan query string untuk pagination
        ]);

        if ($hasFiltered) {
            $query = MonthlyTimesheet::whereIn('status', ['generated', 'rejected'])
                ->with([
                    'user:id,name,jabatan,vendor_id',
                    'user.vendor:id,name',
                    'rejecter:id,name',
                ]);

            if ($user->jabatan === 'asisten manager analis') {
                $query->whereHas('user', fn($q) => $q->whereIn('jabatan', ['analis', 'admin']));
            } elseif ($user->jabatan === 'asisten manager preparator') {
                $query->whereHas('user', fn($q) => $q->whereIn('jabatan', ['preparator', 'mekanik', 'admin']));
            } else {
                $query->whereRaw('1 = 0'); // Seharusnya tidak terjadi jika policy benar
            }

            // Gunakan nilai filter dari request untuk query
            $queryMonth = $request->input('filter_month');
            $queryYear = $request->input('filter_year');

            if ($queryMonth && $queryYear) {
                $query->where(function ($q) use ($queryMonth, $queryYear) {
                    $q->where(fn($sub) => $sub->whereMonth('period_start_date', $queryMonth)->whereYear('period_start_date', $queryYear))
                        ->orWhere(fn($sub) => $sub->whereMonth('period_end_date', $queryMonth)->whereYear('period_end_date', $queryYear));
                });
            }

            $pendingAsistenTimesheets = $query->orderBy('period_start_date', 'desc')
                ->orderBy(User::select('name')->whereColumn('users.id', 'monthly_timesheets.user_id'))
                ->paginate($perPage);

            $pendingAsistenTimesheets->appends($request->except('page'));
        }

        return view('monthly_timesheets.approval.asisten_list', compact(
            'pendingAsistenTimesheets',
            'filterMonth', // Untuk mengisi nilai filter di view
            'filterYear',  // Untuk mengisi nilai filter di view
            'hasFiltered'  // Flag untuk view
        ));
    }

    /**
     * Menampilkan daftar rekap timesheet yang menunggu persetujuan final dari Manager.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View Mengembalikan view 'monthly_timesheets.approval.manager_list'.
     */
    public function listForManagerApproval(Request $request)
    {
        $this->authorize('viewManagerApprovalList', MonthlyTimesheet::class);
        $perPage = 20;

        // Data untuk dropdown filter di view Manager
        $usersForFilter = User::orderBy('name')->select('id', 'name')->get();
        $vendorsForFilter = Vendor::orderBy('name')->select('id', 'name')->get();

        // Default filter bulan dan tahun adalah bulan lalu.
        $filterUserId = $request->input('filter_user_id');
        $filterVendorId = $request->input('filter_vendor_id');
        $filterMonth = $request->input('filter_month', Carbon::now()->subMonthNoOverflow()->month);
        $filterYear = $request->input('filter_year', Carbon::now()->subMonthNoOverflow()->year);

        // Tentukan apakah filter telah diterapkan oleh pengguna
        $hasFiltered = $request->has('filter_month') ||
            $request->has('filter_year') ||
            $request->has('filter_user_id') ||
            $request->has('filter_vendor_id');

        // Inisialisasi paginator kosong
        $pendingManagerTimesheets = new LengthAwarePaginator(collect(), 0, $perPage, 1, [
            'path' => $request->url(),
            'query' => $request->query()
        ]);

        if ($hasFiltered) {
            $query = MonthlyTimesheet::where('status', 'pending_manager')
                ->with([
                    'user:id,name,jabatan,vendor_id',
                    'user.vendor:id,name',
                    'approverAsisten:id,name',
                    'rejecter:id,name',
                ]);

            // Gunakan nilai filter dari request untuk query
            $queryUserId = $request->input('filter_user_id');
            $queryVendorId = $request->input('filter_vendor_id');
            $queryMonth = $request->input('filter_month');
            $queryYear = $request->input('filter_year');

            if ($queryUserId) {
                $query->where('user_id', $queryUserId);
            }
            if ($queryVendorId) {
                if ($queryVendorId === 'is_null') {
                    $query->whereHas('user', fn($q) => $q->whereNull('vendor_id'));
                } else {
                    $query->whereHas('user', fn($q) => $q->where('vendor_id', $queryVendorId));
                }
            }
            if ($queryMonth && $queryYear) {
                // Untuk approval manager, mungkin lebih baik filter hanya berdasarkan period_start_date
                // agar tidak membingungkan jika periode melintasi bulan.
                // Namun, untuk konsistensi dengan index, kita gunakan logika yang sama.
                $query->where(function ($q) use ($queryMonth, $queryYear) {
                    $q->where(fn($sub) => $sub->whereMonth('period_start_date', $queryMonth)->whereYear('period_start_date', $queryYear))
                        ->orWhere(fn($sub) => $sub->whereMonth('period_end_date', $queryMonth)->whereYear('period_end_date', $queryYear));
                });
            }

            $pendingManagerTimesheets = $query->orderBy('approved_at_asisten', 'asc') // Urutkan berdasarkan kapan Asisten menyetujui
                ->orderBy('period_start_date', 'desc')
                ->paginate($perPage);

            $pendingManagerTimesheets->appends($request->except('page'));
        }

        return view('monthly_timesheets.approval.manager_list', compact(
            'pendingManagerTimesheets',
            'usersForFilter',
            'vendorsForFilter',
            'filterUserId',    // Untuk mengisi nilai filter di view
            'filterVendorId',  // Untuk mengisi nilai filter di view
            'filterMonth',     // Untuk mengisi nilai filter di view
            'filterYear',      // Untuk mengisi nilai filter di view
            'hasFiltered'      // Flag untuk view
        ));
    }

    /**
     * Menampilkan detail satu rekap timesheet bulanan beserta detail absensi hariannya.
     *
     * @param  \App\Models\MonthlyTimesheet  $timesheet Instance timesheet yang akan ditampilkan (via Route Model Binding).
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse Mengembalikan view detail atau redirect jika data tidak lengkap.
     */
    public function show(MonthlyTimesheet $timesheet)
    {
        // Otorisasi: Memastikan pengguna berhak melihat detail timesheet ini.
        $this->authorize('view', $timesheet);

        // Eager load relasi yang dibutuhkan untuk tampilan detail
        $timesheet->loadMissing([
            'user:id,name,jabatan,tanggal_mulai_bekerja,vendor_id',
            'user.vendor:id,name',
            'vendor:id,name', // Jika timesheet punya relasi langsung ke vendor (selain via user)
            'approverAsisten:id,name',
            'approverManager:id,name',
            'rejecter:id,name'
        ]);

        // Validasi dasar apakah data user dan periode ada
        if (!$timesheet->user) {
            Log::error("User tidak ditemukan untuk MonthlyTimesheet ID: {$timesheet->id}");
            Alert::error('Data Tidak Lengkap', 'Data karyawan untuk timesheet ini tidak ditemukan.');
            // Redirect ke halaman yang sesuai berdasarkan peran pengguna
            $redirectRoute = Auth::user()->role === 'admin' ? 'monthly_timesheets.index' : (Auth::user()->jabatan === 'manager' ? 'monthly_timesheets.approval.manager.list' : 'monthly_timesheets.approval.asisten.list');
            return redirect()->route($redirectRoute);
        }
        if (!$timesheet->period_start_date || !$timesheet->period_end_date) {
            Log::error("Tanggal periode tidak valid untuk MonthlyTimesheet ID: {$timesheet->id}");
            Alert::error('Data Tidak Lengkap', 'Informasi periode untuk timesheet ini tidak lengkap.');
            $redirectRoute = Auth::user()->role === 'admin' ? 'monthly_timesheets.index' : (Auth::user()->jabatan === 'manager' ? 'monthly_timesheets.approval.manager.list' : 'monthly_timesheets.approval.asisten.list');
            return redirect()->route($redirectRoute);
        }

        // Mengambil detail absensi harian untuk periode timesheet tersebut
        $dailyAttendances = Attendance::where('user_id', $timesheet->user_id)
            ->whereBetween('attendance_date', [
                $timesheet->period_start_date,
                $timesheet->period_end_date
            ])
            ->select( // Pilih hanya kolom yang dibutuhkan
                'attendance_date',
                'clock_in_time',
                'clock_out_time',
                'attendance_status',
                'notes',
                'shift_id',
                'is_corrected',
                'clock_in_photo_path', // Untuk menampilkan foto di detail
                'clock_out_photo_path' // Untuk menampilkan foto di detail
            )
            ->with(['shift:id,name', 'user:id,name']) // Eager load nama shift dan user (untuk tooltip foto)
            ->orderBy('attendance_date', 'asc')
            ->get();

        return view('monthly_timesheets.show', compact('timesheet', 'dailyAttendances'));
    }

    /**
     * Memproses persetujuan timesheet oleh Asisten Manager (Level 1).
     *
     * @param  \App\Models\MonthlyTimesheet  $timesheet Instance timesheet yang akan disetujui.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar approval Asisten.
     */
    public function approveAsisten(MonthlyTimesheet $timesheet)
    {
        // Otorisasi: Memastikan Asisten Manager berhak menyetujui timesheet ini.
        $this->authorize('approveAsisten', $timesheet);

        // Validasi status timesheet sebelum diproses
        if (!in_array($timesheet->status, ['generated', 'rejected'])) {
            Alert::warning('Gagal Proses', 'Status timesheet saat ini (' . $timesheet->status . ') tidak dapat disetujui oleh Asisten.');
            return redirect()->route('monthly_timesheets.approval.asisten.list');
        }

        DB::beginTransaction();
        try {
            $timesheet->update([
                'status' => 'pending_manager', // Status diubah menjadi menunggu persetujuan Manager
                'approved_by_asisten_id' => Auth::id(),
                'approved_at_asisten' => now(),
                'rejected_by_id' => null, // Hapus data rejecter jika sebelumnya ditolak
                'rejected_at' => null,
                'notes' => $timesheet->notes ? $timesheet->notes . ' | Approved by Asisten.' : 'Approved by Asisten.', // Tambahkan catatan approval
            ]);
            DB::commit();
            Alert::success('Berhasil Disetujui (L1)', 'Timesheet (' . ($timesheet->user?->name ?? 'N/A') . ' - ' . ($timesheet->period_start_date?->format('M Y') ?? '?') . ') telah disetujui dan diteruskan ke Manager.');
            Log::info("Timesheet ID {$timesheet->id} approved (L1) by Asisten ID " . Auth::id());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error approving L1 Timesheet ID {$timesheet->id} by User " . Auth::id() . ": " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Sistem', 'Terjadi kesalahan sistem saat memproses persetujuan.');
        }
        return redirect()->route('monthly_timesheets.approval.asisten.list');
    }

    /**
     * Memproses persetujuan final timesheet oleh Manager (Level 2).
     *
     * @param  \App\Models\MonthlyTimesheet  $timesheet Instance timesheet yang akan disetujui.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar approval Manager.
     */
    public function approveManager(MonthlyTimesheet $timesheet)
    {
        // Otorisasi: Memastikan Manager berhak menyetujui timesheet ini.
        $this->authorize('approveManager', $timesheet);

        // Validasi status timesheet sebelum diproses
        if (!in_array($timesheet->status, ['pending_manager', 'rejected'])) { // Manager bisa approve yg 'rejected' jika alur memperbolehkan (misal, setelah direview ulang)
            Alert::warning('Gagal Proses', 'Status timesheet saat ini (' . $timesheet->status . ') tidak dapat disetujui oleh Manager.');
            return redirect()->route('monthly_timesheets.approval.manager.list');
        }

        DB::beginTransaction();
        try {
            /** @var \App\Models\User $manager Pengguna (Manager) yang melakukan approval. */
            $manager = Auth::user();
            /** @var \App\Models\User $employee Karyawan pemilik timesheet. */
            $employee = $timesheet->user()->first(); // Ambil data karyawan untuk notifikasi

            $timesheet->update([
                'status' => 'approved', // Status diubah menjadi disetujui final
                'approved_by_manager_id' => $manager->id,
                'approved_at_manager' => now(),
                'rejected_by_id' => null, // Hapus data rejecter jika sebelumnya ditolak
                'rejected_at' => null,
                'notes' => $timesheet->notes ? $timesheet->notes . ' | Approved by Manager.' : 'Approved by Manager.',
            ]);

            DB::commit();

            // Kirim Notifikasi Email ke Karyawan bahwa timesheetnya telah disetujui final
            if ($employee && $employee->email) {
                try {
                    Mail::to($employee->email)->queue(new MonthlyTimesheetStatusNotification($timesheet, 'approved', $manager));
                    Log::info("Notifikasi persetujuan final timesheet (ID: {$timesheet->id}) telah diantrikan untuk User ID {$employee->id}.");
                } catch (\Exception $e) {
                    Log::error("Gagal mengantrikan email notifikasi persetujuan final timesheet untuk User ID {$employee->id}, Timesheet ID {$timesheet->id}: " . $e->getMessage());
                }
            } else {
                Log::warning("Tidak dapat mengirim notifikasi email persetujuan final: Karyawan atau email tidak ditemukan untuk Timesheet ID {$timesheet->id}.");
            }

            Alert::success('Berhasil Disetujui Final', 'Timesheet (' . ($employee?->name ?? 'N/A') . ' - ' . ($timesheet->period_start_date?->format('M Y') ?? '?') . ') telah disetujui secara final.');
            Log::info("Timesheet ID {$timesheet->id} approved (Final) by Manager ID {$manager->id}.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error approving L2 Timesheet ID {$timesheet->id} by Manager " . ($manager->id ?? Auth::id()) . ": " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Sistem', 'Terjadi kesalahan sistem saat memproses persetujuan final.');
        }
        return redirect()->route('monthly_timesheets.approval.manager.list');
    }

    /**
     * Memproses penolakan timesheet oleh Asisten Manager atau Manager.
     *
     * @param  \Illuminate\Http\Request  $request Data request yang berisi alasan penolakan.
     * @param  \App\Models\MonthlyTimesheet  $timesheet Instance timesheet yang akan ditolak.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar approval yang relevan.
     */
    public function reject(Request $request, MonthlyTimesheet $timesheet)
    {
        // Otorisasi: Memastikan pengguna berhak menolak timesheet ini.
        $this->authorize('reject', $timesheet);

        // Validasi input alasan penolakan
        $validated = $request->validate([
            'notes' => 'required|string|min:5|max:1000',
        ], [
            'notes.required' => 'Alasan penolakan wajib diisi.',
            'notes.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        // Timesheet yang sudah 'approved' tidak dapat ditolak lagi melalui alur ini.
        if ($timesheet->status === 'approved') {
            Alert::warning('Proses Tidak Valid', 'Timesheet yang sudah disetujui tidak dapat ditolak.');
            return redirect()->back();
        }
        // Validasi tambahan: hanya bisa reject jika statusnya relevan untuk di-reject oleh peran saat ini
        $user = Auth::user();
        if (($user->jabatan === 'manager' && $timesheet->status !== 'pending_manager') &&
            (in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator']) && !in_array($timesheet->status, ['generated', 'rejected'])) // Asisten bisa reject yg generated atau yg sudah rejected (untuk di-reject ulang dgn alasan baru)
        ) {
            Alert::warning('Proses Tidak Valid', 'Status timesheet saat ini tidak memungkinkan untuk ditolak oleh Anda.');
            return redirect()->back();
        }


        DB::beginTransaction();
        try {
            /** @var \App\Models\User $rejecter Pengguna yang melakukan penolakan. */
            $rejecter = Auth::user();
            /** @var \App\Models\User $employee Karyawan pemilik timesheet. */
            $employee = $timesheet->user()->first();

            $timesheet->update([
                'status' => 'rejected', // Status diubah menjadi ditolak
                'rejected_by_id' => $rejecter->id,
                'rejected_at' => now(),
                'notes' => $validated['notes'], // Simpan alasan penolakan
                // Reset data approval sebelumnya jika ada
                'approved_by_asisten_id' => null,
                'approved_at_asisten' => null,
                'approved_by_manager_id' => null,
                'approved_at_manager' => null,
            ]);
            DB::commit();

            // Kirim Notifikasi Email ke Karyawan bahwa timesheetnya ditolak
            if ($employee && $employee->email) {
                try {
                    Mail::to($employee->email)->queue(new MonthlyTimesheetStatusNotification($timesheet, 'rejected', $rejecter));
                    Log::info("Notifikasi penolakan timesheet (ID: {$timesheet->id}) telah diantrikan untuk User ID {$employee->id}.");
                } catch (\Exception $e) {
                    Log::error("Gagal mengantrikan email notifikasi penolakan timesheet untuk User ID {$employee->id}, Timesheet ID {$timesheet->id}: " . $e->getMessage());
                }
            } else {
                Log::warning("Tidak dapat mengirim notifikasi email penolakan: Karyawan atau email tidak ditemukan untuk Timesheet ID {$timesheet->id}.");
            }

            Alert::success('Berhasil Ditolak', 'Timesheet (' . ($employee?->name ?? 'N/A') . ' - ' . ($timesheet->period_start_date?->format('M Y') ?? '?') . ') telah ditolak.');
            Log::info("Timesheet ID {$timesheet->id} rejected by User ID {$rejecter->id}. Reason: {$validated['notes']}");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error rejecting Timesheet ID {$timesheet->id} by User {$rejecter->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Sistem', 'Terjadi kesalahan sistem saat memproses penolakan.');
        }

        // Redirect kembali ke halaman daftar approval yang sesuai dengan peran penolak
        if ($rejecter->jabatan === 'manager') {
            return redirect()->route('monthly_timesheets.approval.manager.list');
        } elseif (in_array($rejecter->jabatan, ['asisten manager analis', 'asisten manager preparator'])) {
            return redirect()->route('monthly_timesheets.approval.asisten.list');
        } else {
            // Fallback jika peran tidak teridentifikasi (seharusnya tidak terjadi jika otorisasi benar)
            return redirect()->route('monthly_timesheets.index');
        }
    }

    /**
     * Mengekspor detail timesheet bulanan ke format PDF.
     * Hanya timesheet yang sudah 'approved' yang bisa diekspor.
     *
     * @param  \App\Models\MonthlyTimesheet  $timesheet Instance timesheet yang akan diekspor.
     * @param  string $format Format ekspor (saat ini hanya 'pdf' yang diimplementasikan di contoh ini).
     * @return \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\RedirectResponse File PDF atau redirect jika gagal.
     */
    public function export(MonthlyTimesheet $timesheet, $format = 'pdf') // Tambahkan default format jika perlu
    {
        // Otorisasi: Memastikan pengguna berhak mengekspor timesheet ini.
        $this->authorize('export', $timesheet);

        // Validasi tambahan: Hanya timesheet yang sudah 'approved' yang bisa diekspor.
        if ($timesheet->status !== 'approved') {
            Alert::error('Belum Disetujui', 'Hanya timesheet yang sudah disetujui final yang dapat diekspor.');
            return redirect()->back();
        }

        // Eager load relasi yang dibutuhkan untuk template PDF
        $timesheet->loadMissing([
            'user' => function ($q) {
                $q->select('id', 'name', 'jabatan', 'tanggal_mulai_bekerja', 'vendor_id')->with('vendor:id,name,logo_path'); // Ambil juga logo vendor
            },
            'approverAsisten:id,name,jabatan,signature_path', // Ambil juga jabatan & TTD
            'approverManager:id,name,jabatan,signature_path', // Ambil juga jabatan & TTD
            // 'rejecter:id,name' // Mungkin tidak perlu untuk PDF timesheet approved
        ]);

        if (!$timesheet->user || !$timesheet->period_start_date || !$timesheet->period_end_date) {
            Alert::error('Data Tidak Lengkap', 'Tidak dapat membuat PDF karena data timesheet tidak lengkap.');
            return redirect()->back();
        }

        // Mengambil detail absensi harian untuk periode timesheet
        $dailyAttendances = Attendance::where('user_id', $timesheet->user_id)
            ->whereBetween('attendance_date', [
                $timesheet->period_start_date,
                $timesheet->period_end_date
            ])
            ->select(
                'attendance_date',
                'clock_in_time',
                'clock_out_time',
                'attendance_status',
                'notes',
                'shift_id'
            )
            ->with('shift:id,name')
            ->orderBy('attendance_date', 'asc')
            ->get();

        $userNameSlug = Str::slug($timesheet->user->name ?? 'user');
        $periodSlug = Carbon::parse($timesheet->period_start_date)->format('Ym'); // Pastikan $timesheet->period_start_date adalah Carbon instance atau bisa di-parse
        $filename = "timesheet_{$userNameSlug}_{$periodSlug}.pdf";

        // Untuk saat ini hanya mendukung PDF
        if (strtolower($format) === 'pdf') {
            try {
                // Menggunakan view 'monthly_timesheets.pdf_template' untuk generate PDF
                // Pastikan view ini sudah ada dan menerima variabel $timesheet dan $dailyAttendances
                $pdf = Pdf::loadView('monthly_timesheets.pdf_template', compact('timesheet', 'dailyAttendances'));
                // Opsi: $pdf->setPaper('a4', 'portrait'); // Atur ukuran dan orientasi kertas
                return $pdf->download($filename);
            } catch (\Exception $e) {
                Log::error("Error generating PDF for Timesheet ID {$timesheet->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                Alert::error('Gagal Membuat PDF', 'Terjadi kesalahan saat membuat file PDF. Silakan coba lagi.');
                return redirect()->back();
            }
        } elseif (strtolower($format) === 'excel') {
            // TODO: Implementasi ekspor Excel jika diperlukan
            Alert::warning('Fitur Belum Tersedia', 'Ekspor ke format Excel belum diimplementasikan.');
            return redirect()->back();
        } else {
            Alert::error('Format Tidak Didukung', "Format ekspor '{$format}' tidak didukung.");
            return redirect()->back();
        }
    }


    /**
     * Memproses persetujuan massal (bulk approve) untuk timesheet.
     * Dapat dilakukan oleh Asisten Manager atau Manager.
     *
     * @param  \Illuminate\Http\Request  $request Data request berisi ID timesheet yang dipilih dan level approval.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar approval yang relevan.
     */
    public function bulkApprove(Request $request)
    {
        // Otorisasi umum untuk fitur bulk approve
        $this->authorize('bulkApprove', MonthlyTimesheet::class);

        // Validasi input
        $validator = Validator::make($request->all(), [
            'selected_ids'   => 'required|array|min:1', // Minimal 1 ID harus dipilih
            'selected_ids.*' => 'required|integer|exists:monthly_timesheets,id', // Setiap ID harus ada di tabel
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

        // Otorisasi spesifik berdasarkan level dan jabatan approver
        if ($approvalLevel === 'asisten' && !in_array($approver->jabatan, ['asisten manager analis', 'asisten manager preparator'])) {
            Alert::error('Akses Ditolak', 'Anda bukan Asisten Manager yang berwenang untuk melakukan persetujuan massal level ini.');
            return redirect()->back();
        }
        if ($approvalLevel === 'manager' && $approver->jabatan !== 'manager') {
            Alert::error('Akses Ditolak', 'Anda bukan Manager yang berwenang untuk melakukan persetujuan massal level ini.');
            return redirect()->back();
        }

        // Ambil data timesheet yang akan diproses, termasuk relasi user untuk notifikasi email
        $timesheetsToProcess = MonthlyTimesheet::with(['user:id,name,jabatan,email'])
            ->whereIn('id', $selectedIds)->get();

        $successCount = 0;
        $failCount = 0;
        $failedDetails = []; // Untuk menyimpan detail kegagalan
        $emailFailCount = 0;
        $processedForNotification = collect(); // Untuk notifikasi email jika Manager approve final

        DB::beginTransaction();
        try {
            foreach ($timesheetsToProcess as $timesheet) {
                $canProcess = false;
                $errorMessage = null;
                $pengajuJabatan = $timesheet->user?->jabatan;

                if ($approvalLevel === 'asisten') {
                    // Asisten hanya bisa approve yang statusnya 'generated' atau 'rejected'
                    if (in_array($timesheet->status, ['generated', 'rejected'])) {
                        // Cek scope jabatan Asisten vs Pengaju
                        if (($approver->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) ||
                            ($approver->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin']))
                        ) {
                            // Otorisasi per item menggunakan Policy
                            if (Gate::allows('approveAsisten', $timesheet)) { // Menggunakan Gate untuk memanggil Policy
                                $canProcess = true;
                            } else {
                                $errorMessage = "Tidak berwenang (Policy Asisten).";
                            }
                        } else {
                            $errorMessage = "Tidak berwenang (Scope Jabatan Asisten).";
                        }
                    } else {
                        $errorMessage = "Status bukan 'generated' atau 'rejected'.";
                    }

                    if ($canProcess) {
                        $timesheet->status = 'pending_manager';
                        $timesheet->approved_by_asisten_id = $approver->id;
                        $timesheet->approved_at_asisten = now();
                        $timesheet->rejected_by_id = null;
                        $timesheet->rejected_at = null;
                        $timesheet->notes = $timesheet->notes ? $timesheet->notes . ' | Approved by Asisten (Bulk).' : 'Approved by Asisten (Bulk).';
                        $timesheet->save();
                        $successCount++;
                    }
                } elseif ($approvalLevel === 'manager') {
                    // Manager bisa approve yang statusnya 'pending_manager' atau 'rejected'
                    if (in_array($timesheet->status, ['pending_manager', 'rejected'])) {
                        // Otorisasi per item menggunakan Policy
                        if (Gate::allows('approveManager', $timesheet)) { // Menggunakan Gate untuk memanggil Policy
                            $canProcess = true;
                        } else {
                            $errorMessage = "Tidak berwenang (Policy Manager).";
                        }
                    } else {
                        $errorMessage = "Status bukan 'pending_manager' atau 'rejected'.";
                    }

                    if ($canProcess) {
                        $timesheet->status = 'approved';
                        $timesheet->approved_by_manager_id = $approver->id;
                        $timesheet->approved_at_manager = now();
                        $timesheet->rejected_by_id = null;
                        $timesheet->rejected_at = null;
                        $timesheet->notes = $timesheet->notes ? $timesheet->notes . ' | Approved by Manager (Bulk).' : 'Approved by Manager (Bulk).';
                        $timesheet->save();
                        $successCount++;
                        // Kumpulkan timesheet yang diapprove final untuk notifikasi email
                        $processedForNotification->push($timesheet->fresh(['user:id,name,email', 'approverManager:id,name']));
                    }
                }

                if (!$canProcess) {
                    $failCount++;
                    $userName = $timesheet->user?->name ?? 'N/A';
                    $reason = $errorMessage ?? 'Gagal diproses (tidak memenuhi syarat).';
                    $failedDetails[] = "ID {$timesheet->id} ({$userName}): " . $reason;
                    Log::warning("Bulk Approve Timesheet Failed: ID {$timesheet->id}. Reason: {$reason}. Approver: {$approver->id}");
                }
            }

            DB::commit(); // Commit semua perubahan database jika tidak ada error

            // Kirim Notifikasi Email SETELAH commit jika approval oleh Manager
            if ($approvalLevel === 'manager' && $processedForNotification->isNotEmpty()) {
                Log::info("Bulk Approve: Mengantrikan notifikasi persetujuan final untuk {$processedForNotification->count()} timesheet.");
                foreach ($processedForNotification as $approvedTimesheet) {
                    $employee = $approvedTimesheet->user;
                    if ($employee && $employee->email) {
                        try {
                            // Menggunakan Mailable yang sama dengan approval tunggal
                            Mail::to($employee->email)->queue(new MonthlyTimesheetStatusNotification($approvedTimesheet, 'approved', $approver));
                            Log::info("Notifikasi Timesheet (Bulk) disetujui telah diantrikan untuk User ID {$employee->id}, Timesheet ID {$approvedTimesheet->id}.");
                        } catch (\Exception $e) {
                            $emailFailCount++;
                            Log::error("Gagal mengantrikan notifikasi Timesheet (Bulk) disetujui untuk User ID {$employee->id}, Timesheet ID {$approvedTimesheet->id}: " . $e->getMessage());
                        }
                    } else {
                        $emailFailCount++;
                        Log::warning("Tidak dapat mengirim notifikasi Timesheet (Bulk) disetujui: Karyawan atau email tidak ditemukan untuk Timesheet ID {$approvedTimesheet->id}.");
                    }
                }
            }

            // Menyiapkan pesan feedback untuk pengguna
            $successMessage = "{$successCount} timesheet berhasil diproses.";
            if ($failCount > 0 || $emailFailCount > 0) {
                $alertMessage = $successMessage;
                if ($failCount > 0) {
                    $errorList = implode("<br>", array_map('htmlspecialchars', $failedDetails));
                    $alertMessage .= "<br><br>Namun, {$failCount} timesheet gagal diproses:<br><div style='text-align:left; font-size: smaller; max-height: 150px; overflow-y: auto; border:1px solid #ccc; padding:5px;'><pre style='margin:0; white-space:pre-wrap;'>{$errorList}</pre></div>";
                }
                if ($emailFailCount > 0) {
                    $alertMessage .= "<br><br>Peringatan: {$emailFailCount} notifikasi email gagal diantrikan/dikirim.";
                }
                Alert::html('Proses Selesai Sebagian', $alertMessage, 'warning')->persistent(true, false);
            } else {
                Alert::success('Berhasil Diproses', $successMessage);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error selama proses bulk approve timesheet: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Total', 'Terjadi kesalahan sistem saat memproses persetujuan massal. Semua perubahan dibatalkan.');
        }

        // Redirect kembali ke halaman daftar approval yang sesuai
        $redirectRoute = $approvalLevel === 'manager' ? 'monthly_timesheets.approval.manager.list' : 'monthly_timesheets.approval.asisten.list';
        return redirect()->route($redirectRoute);
    }

    /**
     * Memaksa pemrosesan ulang (re-generate) sebuah timesheet bulanan.
     * Biasanya digunakan oleh Admin/Manajemen jika ada perubahan data absensi
     * setelah timesheet ditolak dan dikoreksi, dan ingin segera diproses ulang
     * tanpa menunggu scheduled task harian.
     *
     * @param  \App\Models\MonthlyTimesheet  $timesheet Instance timesheet yang akan diproses ulang.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke halaman detail timesheet.
     */
    public function forceReprocess(MonthlyTimesheet $timesheet)
    {
        // Otorisasi: Memastikan pengguna berhak melakukan force reprocess.
        $this->authorize('forceReprocess', $timesheet);

        // Validasi tambahan peran (meskipun policy seharusnya sudah menangani)
        if (!(Auth::user()->role === 'admin' || (Auth::user()->role === 'manajemen' && Auth::user()->jabatan === 'manager'))) {
            Alert::error('Akses Ditolak', 'Anda tidak memiliki izin untuk melakukan aksi ini.');
            return redirect()->back();
        }

        try {
            /** @var \App\Models\User $userModel Karyawan pemilik timesheet. */
            $userModel = $timesheet->user()->with('vendor:id,name')->first(); // Eager load vendor untuk logika periode
            if (!$userModel) {
                Alert::error('Data Tidak Valid', 'Karyawan untuk timesheet ini tidak ditemukan.');
                return redirect()->back();
            }
            $vendorName = $userModel->vendor?->name;

            // Tentukan bulan dan tahun acuan yang akan dikirim ke command generate-monthly.
            // Ini penting agar command bisa merekonstruksi periode yang benar, terutama untuk vendor CSI.
            $referenceDateForCommand = Carbon::parse($timesheet->period_start_date);
            if ($vendorName === 'PT Cakra Satya Internusa') { // Sesuaikan nama vendor jika berbeda
                // Untuk CSI, bulan acuan adalah bulan dari period_end_date agar getUserPeriod di command menghasilkan rentang yang benar.
                $referenceDateForCommand = Carbon::parse($timesheet->period_end_date);
            }

            $monthToSend = $referenceDateForCommand->month;
            $yearToSend = $referenceDateForCommand->year;

            Log::info("Memulai force reprocess untuk Timesheet ID: {$timesheet->id}, User ID: {$timesheet->user_id}. Parameter dikirim ke command: Bulan={$monthToSend}, Tahun={$yearToSend}, UserID={$timesheet->user_id}, Force=true");

            // Memanggil command 'timesheet:generate-monthly' dengan parameter yang sesuai.
            // Opsi '--force' akan memastikan timesheet ini dihitung ulang dan statusnya direset ke 'generated'.
            $exitCode = Artisan::call('timesheet:generate-monthly', [
                '--month' => $monthToSend,
                '--year' => $yearToSend,
                '--user_id' => [$timesheet->user_id], // Command mengharapkan array untuk --user_id
                '--force' => true
            ]);

            if ($exitCode === 0) {
                // Ambil ulang data periode dari timesheet yang mungkin sudah diupdate oleh command untuk pesan
                $updatedTimesheet = MonthlyTimesheet::find($timesheet->id); // Ambil data terbaru
                $displayPeriod = $updatedTimesheet ? (Carbon::parse($updatedTimesheet->period_start_date)->format('M Y') . ($updatedTimesheet->period_start_date != $updatedTimesheet->period_end_date ? ' - ' . Carbon::parse($updatedTimesheet->period_end_date)->format('M Y') : '')) : $referenceDateForCommand->format('F Y');

                Alert::success('Berhasil Diproses Ulang', 'Timesheet untuk ' . ($userModel->name ?? 'N/A') . ' terkait periode ' . $displayPeriod . ' telah berhasil diproses ulang dan statusnya direset.');
                Log::info("Timesheet ID {$timesheet->id} berhasil dipicu untuk force reprocessing oleh User ID " . Auth::id());
            } else {
                $output = Artisan::output(); // Ambil output dari command jika ada error
                Log::error("Gagal memicu proses ulang timesheet ID {$timesheet->id} via controller. Exit Code: {$exitCode}. Output: " . $output);
                Alert::error('Gagal Proses Ulang', 'Gagal memicu proses ulang timesheet. Silakan periksa log sistem.');
            }
        } catch (\Exception $e) {
            Log::error("Error saat force reprocessing timesheet ID {$timesheet->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Error Sistem', 'Terjadi kesalahan sistem saat memproses permintaan.');
        }

        // Redirect kembali ke halaman detail timesheet yang sama
        return redirect()->route('monthly_timesheets.show', $timesheet->id);
    }
}

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
use RealRashid\SweetAlert\Facades\Alert;
use Barryvdh\DomPDF\Facade\Pdf; // Pastikan package sudah diinstal: composer require barryvdh/laravel-dompdf
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Untuk Policy
use Illuminate\Support\Facades\Validator; // Untuk validasi input
use Illuminate\Validation\Rule; // Untuk validasi 'in'

// Mail (jika notifikasi diimplementasikan)
// use Illuminate\Support\Facades\Mail;
// use App\Mail\TimesheetReadyForManagerMail;
// use App\Mail\TimesheetFinalizedMail;


class MonthlyTimesheetController extends Controller
{
    use AuthorizesRequests; // Aktifkan untuk menggunakan Policy

    /**
     * Menampilkan daftar SEMUA rekap timesheet bulanan dengan filter (untuk Admin/Manajemen Umum).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Otorisasi: Pastikan user boleh melihat daftar umum ini
        // $this->authorize('viewAny', MonthlyTimesheet::class); // Menggunakan Policy

        $user = Auth::user();
        $perPage = 20;

        // Data untuk filter dropdown
        // Hanya tampilkan jika user adalah admin/manajemen
        $usersForFilter = collect();
        $vendorsForFilter = collect();
        if (in_array($user->role, ['admin', 'manajemen'])) {
            $usersForFilter = User::orderBy('name')->select('id', 'name')->get();
            $vendorsForFilter = Vendor::orderBy('name')->select('id', 'name')->get();
        }
        // Daftar status bisa diambil dari ENUM atau hardcode
        $statuses = ['generated', 'pending_asisten', 'pending_manager_approval', 'approved', 'rejected'];

        // Ambil nilai filter dari request
        $filterUserId = $request->input('filter_user_id');
        $filterVendorId = $request->input('filter_vendor_id');
        $filterStatus = $request->input('filter_status');
        $filterMonth = $request->input('filter_month', Carbon::now()->subMonth()->month);
        $filterYear = $request->input('filter_year', Carbon::now()->subMonth()->year);

        // Query dasar
        $query = MonthlyTimesheet::with([
            'user:id,name,jabatan,vendor_id', // Tambahkan vendor_id untuk filter vendor
            'user.vendor:id,name',
            'approverAsisten:id,name',
            'approverManager:id,name',
            'rejecter:id,name'
        ]);

        // Terapkan filter (Admin bisa filter semua)
        if ($filterUserId && (Auth::user()->role === 'admin' || Auth::user()->role === 'manajemen')) {
            $query->where('user_id', $filterUserId);
        }
        if ($filterVendorId && (Auth::user()->role === 'admin' || Auth::user()->role === 'manajemen')) {
            if ($filterVendorId === 'is_null') {
                // Filter user internal (tidak punya vendor)
                $query->whereHas('user', fn($q) => $q->whereNull('vendor_id'));
            } else {
                // Filter user dengan vendor spesifik
                $query->whereHas('user', fn($q) => $q->where('vendor_id', $filterVendorId));
                // Alternatif jika vendor_id juga ada di monthly_timesheets: $query->where('vendor_id', $filterVendorId);
            }
        }
        if ($filterStatus) {
            $query->where('status', $filterStatus);
        }
        if ($filterMonth && $filterYear) {
            // Filter periode (handle periode yg melintasi bulan kalender)
            $query->where(function ($q) use ($filterMonth, $filterYear) {
                $q->where(fn($sub) => $sub->whereMonth('period_start_date', $filterMonth)->whereYear('period_start_date', $filterYear))
                    ->orWhere(fn($sub) => $sub->whereMonth('period_end_date', $filterMonth)->whereYear('period_end_date', $filterYear));
            });
        }

        // Urutkan & Paginasi
        $timesheets = $query->orderBy('period_start_date', 'desc')
            ->orderBy(User::select('name')->whereColumn('users.id', 'monthly_timesheets.user_id')) // Order by user name
            ->paginate($perPage);

        // Append filter
        $timesheets->appends($request->except('page'));

        return view('monthly_timesheets.index', compact(
            'timesheets',
            'usersForFilter',
            'vendorsForFilter',
            'statuses', // Kirim daftar status untuk filter
            'filterUserId',
            'filterVendorId',
            'filterStatus',
            'filterMonth',
            'filterYear'
        ));
    }

    /**
     * Menampilkan daftar timesheet yang menunggu persetujuan Asisten Manager.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function listForAsistenApproval(Request $request)
    {
        $user = Auth::user();
        // Otorisasi via Policy (lebih baik) atau cek jabatan manual
        // $this->authorize('viewAsistenApprovalList', MonthlyTimesheet::class);
        // if (!in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator'])) {
        //     abort(403, 'Hanya Asisten Manager yang dapat mengakses halaman ini.');
        // }

        $perPage = 20;

        // Query dasar: status 'generated' atau 'rejected' (sesuaikan jika reject tidak bisa diapprove ulang)
        $query = MonthlyTimesheet::whereIn('status', ['generated', 'rejected'])
            ->with([
                'user:id,name,jabatan,vendor_id', // Perlu vendor_id untuk info
                'user.vendor:id,name',
                'rejecter:id,name',
            ]);

        // Filter berdasarkan lingkup jabatan Asisten Manager
        if ($user->jabatan === 'asisten manager analis') {
            $query->whereHas('user', fn($q) => $q->whereIn('jabatan', ['analis', 'admin']));
        } elseif ($user->jabatan === 'asisten manager preparator') {
            $query->whereHas('user', fn($q) => $q->whereIn('jabatan', ['preparator', 'mekanik', 'admin']));
        }

        // Filter opsional berdasarkan Periode (Bulan & Tahun)
        $filterMonth = $request->input('filter_month', Carbon::now()->subMonth()->month);
        $filterYear = $request->input('filter_year', Carbon::now()->subMonth()->year);

        if ($filterMonth && $filterYear) {
            $query->where(function ($q) use ($filterMonth, $filterYear) {
                $q->where(fn($sub) => $sub->whereMonth('period_start_date', $filterMonth)->whereYear('period_start_date', $filterYear))
                    ->orWhere(fn($sub) => $sub->whereMonth('period_end_date', $filterMonth)->whereYear('period_end_date', $filterYear));
            });
        }

        // Urutkan: periode terbaru dulu, lalu berdasarkan nama user
        $pendingAsistenTimesheets = $query->orderBy('period_start_date', 'desc')
            ->orderBy(User::select('name')->whereColumn('users.id', 'monthly_timesheets.user_id'))
            ->paginate($perPage);

        // Append filter
        $pendingAsistenTimesheets->appends($request->except('page'));

        // Kirim data ke view
        return view('monthly_timesheets.approval.asisten_list', compact(
            'pendingAsistenTimesheets',
            'filterMonth',
            'filterYear'
        ));
    }


    /**
     * Menampilkan daftar timesheet yang menunggu persetujuan Manager.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function listForManagerApproval(Request $request)
    {
        // Otorisasi via Policy atau cek jabatan
        // $this->authorize('viewManagerApprovalList', MonthlyTimesheet::class);
        // if (Auth::user()->jabatan !== 'manager') {
        //     abort(403, 'Hanya Manager yang dapat mengakses halaman ini.');
        // }

        $perPage = 20;

        // Query dasar: status 'pending_manager_approval'
        $query = MonthlyTimesheet::where('status', 'pending_manager_approval')
            ->with([
                'user:id,name,jabatan,vendor_id',
                'user.vendor:id,name',
                'approverAsisten:id,name', // Info siapa Asisten yg approve L1
            ]);

        // Filter opsional untuk Manager (Periode, User, Vendor)
        $filterUserId = $request->input('filter_user_id');
        $filterVendorId = $request->input('filter_vendor_id');
        $filterMonth = $request->input('filter_month', Carbon::now()->subMonth()->month);
        $filterYear = $request->input('filter_year', Carbon::now()->subMonth()->year);

        if ($filterUserId) {
            $query->where('user_id', $filterUserId);
        }
        if ($filterVendorId) {
            if ($filterVendorId === 'is_null') {
                $query->whereHas('user', fn($q) => $q->whereNull('vendor_id'));
            } else {
                $query->whereHas('user', fn($q) => $q->where('vendor_id', $filterVendorId));
            }
        }
        if ($filterMonth && $filterYear) {
            $query->where(function ($q) use ($filterMonth, $filterYear) {
                $q->where(fn($sub) => $sub->whereMonth('period_start_date', $filterMonth)->whereYear('period_start_date', $filterYear))
                    ->orWhere(fn($sub) => $sub->whereMonth('period_end_date', $filterMonth)->whereYear('period_end_date', $filterYear));
            });
        }

        // --- Data untuk filter dropdown di view ---
        $usersForFilter = User::orderBy('name')->select('id', 'name')->get();
        $vendorsForFilter = Vendor::orderBy('name')->select('id', 'name')->get();
        // --- End ---

        // Urutkan: yang di-approve Asisten lebih dulu, lalu periode terbaru
        $pendingManagerTimesheets = $query->orderBy('approved_at_asisten', 'asc')
            ->orderBy('period_start_date', 'desc')
            ->paginate($perPage);

        // Append filter
        $pendingManagerTimesheets->appends($request->except('page'));

        // Kirim data ke view
        return view('monthly_timesheets.approval.manager_list', compact(
            'pendingManagerTimesheets',
            'usersForFilter',
            'vendorsForFilter',
            'filterUserId',
            'filterVendorId',
            'filterMonth',
            'filterYear'
        ));
    }


    /**
     * Menampilkan detail satu rekap timesheet, termasuk detail absensi harian.
     *
     * @param  \App\Models\MonthlyTimesheet  $timesheet // Variabel disesuaikan menjadi $timesheet
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show(MonthlyTimesheet $timesheet) // Variabel $timesheet
    {
        // Otorisasi: Pastikan user boleh melihat timesheet spesifik ini
        // $this->authorize('view', $timesheet); // Menggunakan Policy

        // Load relasi yang dibutuhkan untuk menampilkan info ringkasan
        $timesheet->loadMissing([
            'user:id,name,jabatan,tanggal_mulai_bekerja,vendor_id',
            'user.vendor:id,name',
            'vendor:id,name',
            'approverAsisten:id,name',
            'approverManager:id,name',
            'rejecter:id,name'
        ]);

        // Cek jika user tidak ditemukan (data tidak konsisten)
        if (!$timesheet->user) {
            Log::error("User tidak ditemukan untuk MonthlyTimesheet ID: {$timesheet->id}");
            Alert::error('Data Tidak Lengkap', 'Data karyawan untuk timesheet ini tidak ditemukan.');
            // Redirect ke halaman index umum atau list approval tergantung role
            $redirectRoute = Auth::user()->role === 'admin' ? 'monthly-timesheets.index' : (Auth::user()->jabatan === 'manager' ? 'monthly-timesheets.approval.manager.list' : 'monthly-timesheets.approval.asisten.list');
            return redirect()->route($redirectRoute);
        }

        // Cek jika tanggal periode tidak valid
        if (!$timesheet->period_start_date || !$timesheet->period_end_date) {
            Log::error("Tanggal periode tidak valid untuk MonthlyTimesheet ID: {$timesheet->id}");
            Alert::error('Data Tidak Lengkap', 'Informasi periode untuk timesheet ini tidak lengkap.');
            $redirectRoute = Auth::user()->role === 'admin' ? 'monthly-timesheets.index' : (Auth::user()->jabatan === 'manager' ? 'monthly-timesheets.approval.manager.list' : 'monthly-timesheets.approval.asisten.list');
            return redirect()->route($redirectRoute);
        }


        // Ambil Detail Absensi Harian (Pendekatan A)
        $dailyAttendances = Attendance::where('user_id', $timesheet->user_id)
            ->whereBetween('attendance_date', [
                $timesheet->period_start_date,
                $timesheet->period_end_date
            ])
            ->select( // Pilih kolom yang dibutuhkan
                'attendance_date',
                'clock_in_time',
                'clock_out_time',
                'attendance_status',
                'notes',
                'shift_id',
                'is_corrected'
            )
            ->with('shift:id,name') // Eager load shift
            ->orderBy('attendance_date', 'asc')
            ->get();

        // Kirim kedua set data (ringkasan dan detail) ke view
        return view('monthly_timesheets.show', compact('timesheet', 'dailyAttendances'));
    }

    /**
     * Menyetujui timesheet (Level 1 - Asisten Manager).
     *
     * @param  \App\Models\MonthlyTimesheet  $timesheet // Variabel $timesheet
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approveAsisten(MonthlyTimesheet $timesheet) // Variabel $timesheet
    {
        // Otorisasi
        // $this->authorize('approveAsisten', $timesheet);

        // Validasi Status
        if (!in_array($timesheet->status, ['generated', 'rejected'])) {
            Alert::warning('Gagal', 'Status timesheet saat ini (' . $timesheet->status . ') tidak dapat disetujui oleh Asisten.');
            return redirect()->route('monthly-timesheets.approval.asisten.list');
        }

        // Update Data
        try {
            $timesheet->update([
                'status' => 'pending_manager_approval',
                'approved_by_asisten_id' => Auth::id(),
                'approved_at_asisten' => now(),
                'rejected_by_id' => null,
                'rejected_at' => null,
                'notes' => null,
            ]);

            // (Opsional) Kirim Notifikasi ke Manager
            // ...

            Alert::success('Berhasil', 'Timesheet (' . ($timesheet->user?->name ?? 'N/A') . ' - ' . ($timesheet->period_start_date?->format('M Y') ?? '?') . ') telah disetujui dan diteruskan ke Manager.');
        } catch (\Exception $e) {
            Log::error("Error approving L1 Timesheet ID {$timesheet->id} by User " . Auth::id() . ": " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan sistem saat memproses persetujuan.');
        }

        return redirect()->route('monthly-timesheets.approval.asisten.list');
    }

    /**
     * Menyetujui timesheet (Level 2 - Manager Final).
     *
     * @param  \App\Models\MonthlyTimesheet  $timesheet // Variabel $timesheet
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approveManager(MonthlyTimesheet $timesheet) // Variabel $timesheet
    {
        // Otorisasi
        // $this->authorize('approveManager', $timesheet);

        // Validasi Status
        if (!in_array($timesheet->status, ['pending_manager_approval', 'rejected'])) {
            Alert::warning('Gagal', 'Status timesheet saat ini (' . $timesheet->status . ') tidak dapat disetujui oleh Manager.');
            return redirect()->route('monthly-timesheets.approval.manager.list');
        }

        // Update Data
        try {
            $managerId = Auth::id();
            $timesheet->update([
                'status' => 'approved',
                'approved_by_manager_id' => $managerId,
                'approved_at_manager' => now(),
                'rejected_by_id' => null,
                'rejected_at' => null,
                'notes' => 'Approved by Manager.',
            ]);

            // (Opsional) Kirim Notifikasi ke Karyawan
            // ...

            Alert::success('Berhasil', 'Timesheet (' . ($timesheet->user?->name ?? 'N/A') . ' - ' . ($timesheet->period_start_date?->format('M Y') ?? '?') . ') telah disetujui secara final.');
        } catch (\Exception $e) {
            Log::error("Error approving L2 Timesheet ID {$timesheet->id} by Manager {$managerId}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan sistem saat memproses persetujuan final.');
        }

        return redirect()->route('monthly-timesheets.approval.manager.list');
    }

    /**
     * Menolak timesheet.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\MonthlyTimesheet  $timesheet // Variabel $timesheet
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reject(Request $request, MonthlyTimesheet $timesheet) // Variabel $timesheet
    {
        // Otorisasi
        // $this->authorize('reject', $timesheet);

        // Validasi Input Alasan
        $validated = $request->validate([
            'notes' => 'required|string|min:5|max:1000',
        ], [
            'notes.required' => 'Alasan penolakan wajib diisi.',
            'notes.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        // Validasi Status (Tidak bisa reject yg sudah approved final)
        if ($timesheet->status === 'approved') {
            Alert::warning('Gagal', 'Timesheet yang sudah disetujui tidak dapat ditolak.');
            return redirect()->back();
        }

        // Update Data
        try {
            $rejecterId = Auth::id();
            $timesheet->update([
                'status' => 'rejected',
                'rejected_by_id' => $rejecterId,
                'rejected_at' => now(),
                'notes' => $validated['notes'],
                // Reset approval fields
                'approved_by_asisten_id' => null,
                'approved_at_asisten' => null,
                'approved_by_manager_id' => null,
                'approved_at_manager' => null,
            ]);

            // (Opsional) Kirim Notifikasi ke Karyawan
            // ...

            Alert::success('Berhasil', 'Timesheet (' . ($timesheet->user?->name ?? 'N/A') . ' - ' . ($timesheet->period_start_date?->format('M Y') ?? '?') . ') telah ditolak.');
        } catch (\Exception $e) {
            Log::error("Error rejecting Timesheet ID {$timesheet->id} by User {$rejecterId}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan sistem saat memproses penolakan.');
        }

        // Redirect kembali ke daftar approval yang relevan
        $user = Auth::user();
        if ($user->jabatan === 'manager') {
            return redirect()->route('monthly-timesheets.approval.manager.list');
        } elseif (in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator'])) {
            return redirect()->route('monthly-timesheets.approval.asisten.list');
        } else {
            // Fallback (seharusnya tidak terjadi jika otorisasi benar)
            return redirect()->route('monthly-timesheets.index');
        }
    }

    /**
     * Menghasilkan dan mengunduh PDF untuk timesheet.
     * !!! Metode ini perlu disesuaikan dari route Anda yang menggunakan {format} !!!
     * Asumsi route diubah menjadi spesifik PDF seperti contoh saya sebelumnya.
     *
     * @param  \App\Models\MonthlyTimesheet  $timesheet // Variabel $timesheet
     * @return \Illuminate\Http\Response
     */
    public function exportPdf(MonthlyTimesheet $timesheet) // Variabel $timesheet
    {
        // Otorisasi
        // $this->authorize('exportPdf', $timesheet); // Atau 'view'

        // Load relasi
        $timesheet->loadMissing([
            'user' => function ($q) {
                $q->select('id', 'name', 'jabatan', 'tanggal_mulai_bekerja', 'vendor_id')->with('vendor:id,name');
            },
            'approverAsisten:id,name,signature_path',
            'approverManager:id,name,signature_path',
            'rejecter:id,name'
        ]);

        // Cek data penting sebelum generate PDF
        if (!$timesheet->user || !$timesheet->period_start_date || !$timesheet->period_end_date) {
            Alert::error('Data Tidak Lengkap', 'Tidak dapat membuat PDF karena data timesheet tidak lengkap.');
            return redirect()->back();
        }


        // Ambil Detail Absensi Harian
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

        // Generate Nama File PDF
        $userNameSlug = Str::slug($timesheet->user->name ?? 'user');
        $periodSlug = $timesheet->period_start_date->format('Ym');
        $filename = "timesheet_{$userNameSlug}_{$periodSlug}.pdf";

        // Load View PDF dan Kirim Data
        try {
            $pdf = Pdf::loadView('monthly_timesheets.pdf_template', compact('timesheet', 'dailyAttendances'));
            // Opsi: $pdf->setPaper('a4', 'landscape');
            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error("Error generating PDF for Timesheet ID {$timesheet->id}: " . $e->getMessage());
            Alert::error('Gagal Membuat PDF', 'Terjadi kesalahan saat membuat file PDF.');
            return redirect()->back();
        }
    }

    // Jika Anda tetap ingin menggunakan route export dengan {format}:
    // public function export(MonthlyTimesheet $timesheet, $format)
    // {
    //     if ($format === 'pdf') {
    //         // Panggil logika exportPdf di atas
    //         return $this->exportPdf($timesheet); // Panggil metode yg sudah ada
    //     } elseif ($format === 'excel') {
    //         // Logika export Excel (misal pakai Maatwebsite/Excel)
    //         // $this->authorize('exportExcel', $timesheet);
    //         // ... ambil data ...
    //         // return Excel::download(new MonthlyTimesheetExport($timesheet, $dailyAttendances), 'timesheet.xlsx');
    //         Alert::warning('Fitur Belum Tersedia', 'Ekspor Excel belum diimplementasikan.');
    //         return redirect()->back();
    //     } else {
    //         abort(404); // Format tidak didukung
    //     }
    // }
    /**
     * Memproses persetujuan massal (bulk approve) untuk timesheet.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulkApprove(Request $request)
    {
        // 1. Validasi Input
        $validator = Validator::make($request->all(), [
            'selected_ids'   => 'required|array',
            'selected_ids.*' => 'required|integer|exists:monthly_timesheets,id', // Pastikan ID valid
            'approval_level' => ['required', Rule::in(['asisten', 'manager'])], // Validasi level
        ]);

        if ($validator->fails()) {
            Alert::error('Input Tidak Valid', 'Tidak ada data yang dipilih atau level approval tidak sesuai.');
            // Redirect kembali ke halaman sebelumnya atau halaman default
            return redirect()->back();
        }

        $validated = $validator->validated();
        $selectedIds = $validated['selected_ids'];
        $approvalLevel = $validated['approval_level'];
        $approver = Auth::user();

        // 2. Otorisasi Umum (contoh, idealnya pakai Policy terpisah untuk bulk)
        if ($approvalLevel === 'asisten' && !in_array($approver->jabatan, ['asisten manager analis', 'asisten manager preparator'])) {
            abort(403, 'Anda bukan Asisten Manager yang berwenang.');
        }
        if ($approvalLevel === 'manager' && $approver->jabatan !== 'manager') {
            abort(403, 'Anda bukan Manager.');
        }
        // $this->authorize('bulkApprove', [MonthlyTimesheet::class, $approvalLevel]); // Contoh Policy

        // 3. Ambil Data & Proses
        $timesheetsToProcess = MonthlyTimesheet::with(['user:id,name,jabatan,email']) // Perlu email jika ada notif
            ->whereIn('id', $selectedIds)->get();

        $successCount = 0;
        $failCount = 0;
        $failedDetails = [];
        $approvedForNotification = []; // Untuk notifikasi bulk manager (jika perlu)

        DB::beginTransaction();
        try {
            foreach ($timesheetsToProcess as $timesheet) {
                $canProcess = false;
                $errorMessage = null;
                $pengajuJabatan = $timesheet->user?->jabatan; // Ambil jabatan pengaju

                // Lakukan otorisasi & validasi status per item
                if ($approvalLevel === 'asisten') {
                    // Validasi status & otorisasi scope Asisten
                    if (in_array($timesheet->status, ['generated', 'rejected'])) {
                        if (($approver->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) ||
                            ($approver->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin']))
                        ) {
                            $canProcess = true;
                        } else {
                            $errorMessage = "Tidak berwenang (scope).";
                        }
                    } else {
                        $errorMessage = "Status bukan 'generated'/'rejected'.";
                    }

                    if ($canProcess) {
                        $timesheet->status = 'pending_manager_approval';
                        $timesheet->approved_by_asisten_id = $approver->id;
                        $timesheet->approved_at_asisten = now();
                        $timesheet->rejected_by_id = null;
                        $timesheet->rejected_at = null;
                        $timesheet->notes = null;
                        $timesheet->save();
                        $successCount++;
                        // Tidak ada notifikasi di L1 biasanya
                    }
                } elseif ($approvalLevel === 'manager') {
                    // Validasi status & otorisasi Manager
                    if ($timesheet->status === 'pending_manager_approval') { // Hanya approve yg menunggu manager
                        $canProcess = true;
                    } else {
                        $errorMessage = "Status bukan 'pending_manager_approval'.";
                    }

                    if ($canProcess) {
                        $timesheet->status = 'approved';
                        $timesheet->approved_by_manager_id = $approver->id;
                        $timesheet->approved_at_manager = now();
                        $timesheet->rejected_by_id = null;
                        $timesheet->rejected_at = null;
                        $timesheet->notes = 'Approved by Manager (Bulk).';
                        $timesheet->save();
                        $successCount++;

                        // Kumpulkan untuk notifikasi ke karyawan (jika perlu)
                        if ($timesheet->user?->email) {
                            $approvedForNotification[$timesheet->user->id]['user'] = $timesheet->user;
                            $approvedForNotification[$timesheet->user->id]['timesheets'][] = $timesheet;
                        }
                    }
                }

                // Catat jika gagal
                if (!$canProcess) {
                    $failCount++;
                    $userName = (!empty($timesheet->user) && !empty($timesheet->user->name)) ? $timesheet->user->name : 'N/A';
                    $reason = isset($errorMessage) ? $errorMessage : 'Gagal.';
                    $failedDetails[] = "ID {$timesheet->id} ({$userName}): " . $reason;

                    $logReason = isset($errorMessage) ? $errorMessage : 'Failed';
                    Log::warning("Bulk Approve Timesheet Failed: ID {$timesheet->id}. Reason: " . $logReason . ". Approver: {$approver->id}");
                }
            } // End foreach

            DB::commit();

            // 4. (Opsional) Kirim Notifikasi Bulk Setelah Commit
            // if ($approvalLevel === 'manager' && !empty($approvedForNotification)) {
            //     foreach ($approvedForNotification as $userId => $data) {
            //         $employee = $data['user'];
            //         $approvedList = collect($data['timesheets']);
            //         try {
            //             // Buat Mailable khusus untuk notifikasi bulk status timesheet
            //             // Mail::to($employee->email)->queue(new BulkTimesheetStatusNotificationMail($approvedList, $approver, 'approved'));
            //             Log::info("Bulk timesheet approval notification queued for User ID {$userId}");
            //         } catch (\Exception $e) {
            //              Log::error("Failed queueing bulk timesheet approval notification for User ID {$userId}: " . $e->getMessage());
            //         }
            //     }
            // }

            // 5. Siapkan Pesan Feedback
            $successMessage = "{$successCount} timesheet berhasil diproses.";
            if ($failCount > 0) {
                $errorList = implode("\n", $failedDetails);
                // Gabungkan pesan sukses dan gagal
                Alert::html(
                    'Proses Selesai Sebagian',
                    $successMessage . "<br><br>Namun, {$failCount} timesheet gagal diproses:<br><pre style='text-align:left; font-size: smaller;'>{$errorList}</pre>",
                    'warning' // Tipe alert warning
                )->persistent(true, false); // Tampilkan tombol OK
            } else {
                Alert::success('Berhasil', $successMessage);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error during bulk approve timesheet: " . $e->getMessage());
            Alert::error('Gagal Total', 'Terjadi kesalahan sistem saat memproses persetujuan massal.');
        }

        // 6. Redirect kembali ke halaman approval yang sesuai
        $redirectRoute = $approvalLevel === 'manager' ? 'monthly_timesheets.approval.manager.list' : 'monthly_timesheets.approval.asisten.list';
        return redirect()->route($redirectRoute);
    }
} // End Class MonthlyTimesheetController

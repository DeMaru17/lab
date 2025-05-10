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
use Illuminate\Support\Facades\Artisan;
use RealRashid\SweetAlert\Facades\Alert;
use Barryvdh\DomPDF\Facade\Pdf; // Pastikan package sudah diinstal: composer require barryvdh/laravel-dompdf
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Untuk Policy
use Illuminate\Support\Facades\Validator; // Untuk validasi input
use Illuminate\Validation\Rule; // Untuk validasi 'in'
use Illuminate\Support\Facades\Mail; // Tambahkan ini
use App\Mail\MonthlyTimesheetStatusNotification; // Tambahkan ini
use Illuminate\Support\Facades\Gate; // Tambahkan ini untuk menggunakan Gate

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
        $this->authorize('viewAny', MonthlyTimesheet::class); // Otorisasi akses halaman index

        $user = Auth::user();
        $perPage = 20;

        // Data untuk filter dropdown
        $usersForFilter = collect();
        $vendorsForFilter = collect();
        if ($user->role === 'admin' || $user->role === 'manajemen') {
            $usersForFilter = User::orderBy('name')->select('id', 'name')->get();
            $vendorsForFilter = Vendor::orderBy('name')->select('id', 'name')->get();
        }
        $statuses = ['generated', 'pending_asisten', 'pending_manager', 'approved', 'rejected'];

        // Ambil nilai filter dari request
        // Jika personil, $filterUserId otomatis adalah ID mereka sendiri
        $filterUserId = ($user->role === 'personil') ? $user->id : $request->input('filter_user_id');
        $filterVendorId = ($user->role === 'personil') ? null : $request->input('filter_vendor_id'); // Personil tidak filter vendor
        $filterStatus = $request->input('filter_status');
        $filterMonth = $request->input('filter_month', Carbon::now()->subMonthNoOverflow()->month);
        $filterYear = $request->input('filter_year', Carbon::now()->subMonthNoOverflow()->year);

        // Query dasar
        $query = MonthlyTimesheet::with([
            'user:id,name,jabatan,vendor_id',
            'user.vendor:id,name',
            'approverAsisten:id,name',
            'approverManager:id,name',
            'rejecter:id,name'
        ]);

        // Filter berdasarkan role
        if ($user->role === 'personil') {
            $query->where('user_id', $user->id);
        } elseif ($filterUserId && ($user->role === 'admin' || $user->role === 'manajemen')) {
            $query->where('user_id', $filterUserId);
        }

        // Terapkan filter vendor hanya jika bukan personil dan filter vendor diisi
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

        if ($filterMonth && $filterYear) {
            $query->where(function ($q) use ($filterMonth, $filterYear) {
                $q->where(fn($sub) => $sub->whereMonth('period_start_date', $filterMonth)->whereYear('period_start_date', $filterYear))
                    ->orWhere(fn($sub) => $sub->whereMonth('period_end_date', $filterMonth)->whereYear('period_end_date', $filterYear));
            });
        }

        $timesheets = $query->orderBy('period_start_date', 'desc')
            ->orderBy(User::select('name')->whereColumn('users.id', 'monthly_timesheets.user_id'))
            ->paginate($perPage);

        $timesheets->appends($request->except('page'));

        return view('monthly_timesheets.index', compact(
            'timesheets',
            'usersForFilter', // Akan kosong jika personil
            'vendorsForFilter', // Akan kosong jika personil
            'statuses',
            'filterUserId',
            'filterVendorId',
            'filterStatus',
            'filterMonth',
            'filterYear'
        ));
    }

    public function listForAsistenApproval(Request $request)
    {
        $user = Auth::user();
        $this->authorize('viewAsistenApprovalList', MonthlyTimesheet::class);

        $perPage = 20;
        $query = MonthlyTimesheet::whereIn('status', ['generated', 'rejected']) // Asisten approve yang 'generated' atau 'rejected'
            ->with([
                'user:id,name,jabatan,vendor_id',
                'user.vendor:id,name',
                'rejecter:id,name',
            ]);

        if ($user->jabatan === 'asisten manager analis') {
            $query->whereHas('user', fn($q) => $q->whereIn('jabatan', ['analis', 'admin']));
        } elseif ($user->jabatan === 'asisten manager preparator') {
            $query->whereHas('user', fn($q) => $q->whereIn('jabatan', ['preparator', 'mekanik', 'admin']));
        }

        $filterMonth = $request->input('filter_month', Carbon::now()->subMonth()->month);
        $filterYear = $request->input('filter_year', Carbon::now()->subMonth()->year);

        if ($filterMonth && $filterYear) {
            $query->where(function ($q) use ($filterMonth, $filterYear) {
                $q->where(fn($sub) => $sub->whereMonth('period_start_date', $filterMonth)->whereYear('period_start_date', $filterYear))
                    ->orWhere(fn($sub) => $sub->whereMonth('period_end_date', $filterMonth)->whereYear('period_end_date', $filterYear));
            });
        }

        $pendingAsistenTimesheets = $query->orderBy('period_start_date', 'desc')
            ->orderBy(User::select('name')->whereColumn('users.id', 'monthly_timesheets.user_id'))
            ->paginate($perPage);

        $pendingAsistenTimesheets->appends($request->except('page'));

        return view('monthly_timesheets.approval.asisten_list', compact(
            'pendingAsistenTimesheets',
            'filterMonth',
            'filterYear'
        ));
    }

    public function listForManagerApproval(Request $request)
    {
        $this->authorize('viewManagerApprovalList', MonthlyTimesheet::class);

        $perPage = 20;
        // Manager approve yang statusnya 'pending_manager' atau 'rejected' (jika Manager bisa approve ulang yg di-reject)
        $query = MonthlyTimesheet::whereIn('status', ['pending_manager'])
            ->with([
                'user:id,name,jabatan,vendor_id',
                'user.vendor:id,name',
                'approverAsisten:id,name',
                'rejecter:id,name', // Tambahkan ini jika Manager bisa lihat siapa yg reject sebelumnya
            ]);

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

        $usersForFilter = User::orderBy('name')->select('id', 'name')->get();
        $vendorsForFilter = Vendor::orderBy('name')->select('id', 'name')->get();

        $pendingManagerTimesheets = $query->orderBy('approved_at_asisten', 'asc')
            ->orderBy('period_start_date', 'desc')
            ->paginate($perPage);

        $pendingManagerTimesheets->appends($request->except('page'));

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

    public function show(MonthlyTimesheet $timesheet)
    {
        $this->authorize('view', $timesheet);

        $timesheet->loadMissing([
            'user:id,name,jabatan,tanggal_mulai_bekerja,vendor_id',
            'user.vendor:id,name',
            'vendor:id,name',
            'approverAsisten:id,name',
            'approverManager:id,name',
            'rejecter:id,name'
        ]);

        if (!$timesheet->user) {
            Log::error("User tidak ditemukan untuk MonthlyTimesheet ID: {$timesheet->id}");
            Alert::error('Data Tidak Lengkap', 'Data karyawan untuk timesheet ini tidak ditemukan.');
            $redirectRoute = Auth::user()->role === 'admin' ? 'monthly-timesheets.index' : (Auth::user()->jabatan === 'manager' ? 'monthly-timesheets.approval.manager.list' : 'monthly-timesheets.approval.asisten.list');
            return redirect()->route($redirectRoute);
        }

        if (!$timesheet->period_start_date || !$timesheet->period_end_date) {
            Log::error("Tanggal periode tidak valid untuk MonthlyTimesheet ID: {$timesheet->id}");
            Alert::error('Data Tidak Lengkap', 'Informasi periode untuk timesheet ini tidak lengkap.');
            $redirectRoute = Auth::user()->role === 'admin' ? 'monthly-timesheets.index' : (Auth::user()->jabatan === 'manager' ? 'monthly-timesheets.approval.manager.list' : 'monthly-timesheets.approval.asisten.list');
            return redirect()->route($redirectRoute);
        }

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
                'shift_id',
                'is_corrected',
                'clock_in_photo_path',
                'clock_out_photo_path'
            )
            ->with('shift:id,name', 'user:id,name')
            ->orderBy('attendance_date', 'asc')
            ->get();

        return view('monthly_timesheets.show', compact('timesheet', 'dailyAttendances'));
    }

    public function approveAsisten(MonthlyTimesheet $timesheet)
    {
        $this->authorize('approveAsisten', $timesheet);

        if (!in_array($timesheet->status, ['generated', 'rejected'])) {
            Alert::warning('Gagal', 'Status timesheet saat ini (' . $timesheet->status . ') tidak dapat disetujui oleh Asisten.');
            return redirect()->route('monthly-timesheets.approval.asisten.list');
        }

        try {
            $timesheet->update([
                'status' => 'pending_manager', // DISESUAIKAN
                'approved_by_asisten_id' => Auth::id(),
                'approved_at_asisten' => now(),
                'rejected_by_id' => null,
                'rejected_at' => null,
                'notes' => null, // Atau 'Approved by Asisten' jika perlu
            ]);
            Alert::success('Berhasil', 'Timesheet (' . ($timesheet->user?->name ?? 'N/A') . ' - ' . ($timesheet->period_start_date?->format('M Y') ?? '?') . ') telah disetujui dan diteruskan ke Manager.');
        } catch (\Exception $e) {
            Log::error("Error approving L1 Timesheet ID {$timesheet->id} by User " . Auth::id() . ": " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan sistem saat memproses persetujuan.');
        }
        return redirect()->route('monthly-timesheets.approval.asisten.list');
    }

    public function approveManager(MonthlyTimesheet $timesheet)
    {
        $this->authorize('approveManager', $timesheet);

        if (!in_array($timesheet->status, ['pending_manager', 'rejected'])) {
            Alert::warning('Gagal', 'Status timesheet saat ini (' . $timesheet->status . ') tidak dapat disetujui oleh Manager.');
            return redirect()->route('monthly-timesheets.approval.manager.list');
        }

        DB::beginTransaction(); // Mulai transaksi
        try {
            $manager = Auth::user(); // Approver adalah user yang login
            $employee = $timesheet->user()->first(); // User pemilik timesheet

            $timesheet->update([
                'status' => 'approved',
                'approved_by_manager_id' => $manager->id,
                'approved_at_manager' => now(),
                'rejected_by_id' => null,
                'rejected_at' => null,
                'notes' => $timesheet->notes ? $timesheet->notes . ' | Approved by Manager.' : 'Approved by Manager.',
            ]);

            DB::commit(); // Commit transaksi

            // Kirim Notifikasi Email ke Karyawan
            if ($employee && $employee->email) {
                try {
                    Mail::to($employee->email)->queue(new MonthlyTimesheetStatusNotification($timesheet, 'approved', $manager));
                    Log::info("MonthlyTimesheet approved notification queued for User ID {$employee->id}, Timesheet ID {$timesheet->id}.");
                } catch (\Exception $e) {
                    Log::error("Failed to queue MonthlyTimesheet approved notification for User ID {$employee->id}, Timesheet ID {$timesheet->id}: " . $e->getMessage());
                    // Jangan gagalkan proses utama karena email
                }
            } else {
                Log::warning("Cannot send MonthlyTimesheet approved notification: Employee or email not found for Timesheet ID {$timesheet->id}.");
            }

            Alert::success('Berhasil', 'Timesheet (' . ($employee?->name ?? 'N/A') . ' - ' . ($timesheet->period_start_date?->format('M Y') ?? '?') . ') telah disetujui secara final.');
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback jika ada error
            Log::error("Error approving L2 Timesheet ID {$timesheet->id} by Manager {$manager->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan sistem saat memproses persetujuan final.');
        }

        return redirect()->route('monthly-timesheets.approval.manager.list');
    }

    public function reject(Request $request, MonthlyTimesheet $timesheet)
    {
        $this->authorize('reject', $timesheet);

        $validated = $request->validate([
            'notes' => 'required|string|min:5|max:1000',
        ], [
            'notes.required' => 'Alasan penolakan wajib diisi.',
            'notes.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        if ($timesheet->status === 'approved') {
            Alert::warning('Gagal', 'Timesheet yang sudah disetujui tidak dapat ditolak.');
            return redirect()->back();
        }

        DB::beginTransaction(); // Mulai transaksi
        try {
            $rejecter = Auth::user(); // User yang melakukan reject
            $employee = $timesheet->user()->first(); // User pemilik timesheet

            $timesheet->update([
                'status' => 'rejected',
                'rejected_by_id' => $rejecter->id,
                'rejected_at' => now(),
                'notes' => $validated['notes'],
                'approved_by_asisten_id' => null,
                'approved_at_asisten' => null,
                'approved_by_manager_id' => null,
                'approved_at_manager' => null,
            ]);

            DB::commit(); // Commit transaksi

            // Kirim Notifikasi Email ke Karyawan
            if ($employee && $employee->email) {
                try {
                    Mail::to($employee->email)->queue(new MonthlyTimesheetStatusNotification($timesheet, 'rejected', $rejecter));
                    Log::info("MonthlyTimesheet rejected notification queued for User ID {$employee->id}, Timesheet ID {$timesheet->id}.");
                } catch (\Exception $e) {
                    Log::error("Failed to queue MonthlyTimesheet rejected notification for User ID {$employee->id}, Timesheet ID {$timesheet->id}: " . $e->getMessage());
                }
            } else {
                Log::warning("Cannot send MonthlyTimesheet rejected notification: Employee or email not found for Timesheet ID {$timesheet->id}.");
            }

            Alert::success('Berhasil', 'Timesheet (' . ($employee?->name ?? 'N/A') . ' - ' . ($timesheet->period_start_date?->format('M Y') ?? '?') . ') telah ditolak.');
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback jika ada error
            Log::error("Error rejecting Timesheet ID {$timesheet->id} by User {$rejecter->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan sistem saat memproses penolakan.');
        }

        // Redirect kembali ke daftar approval yang relevan
        if ($rejecter->jabatan === 'manager') {
            return redirect()->route('monthly_timesheets.approval.manager.list');
        } elseif (in_array($rejecter->jabatan, ['asisten manager analis', 'asisten manager preparator'])) {
            return redirect()->route('monthly_timesheets.approval.asisten.list');
        } else {
            return redirect()->route('monthly_timesheets.index');
        }
    }

    public function export(MonthlyTimesheet $timesheet)
    {
        $this->authorize('export', $timesheet);

        $timesheet->loadMissing([
            'user' => function ($q) {
                $q->select('id', 'name', 'jabatan', 'tanggal_mulai_bekerja', 'vendor_id')->with('vendor:id,name');
            },
            'approverAsisten:id,name,signature_path',
            'approverManager:id,name,signature_path',
            'rejecter:id,name'
        ]);

        if (!$timesheet->user || !$timesheet->period_start_date || !$timesheet->period_end_date) {
            Alert::error('Data Tidak Lengkap', 'Tidak dapat membuat PDF karena data timesheet tidak lengkap.');
            return redirect()->back();
        }

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
        $periodSlug = $timesheet->period_start_date->format('Ym');
        $filename = "timesheet_{$userNameSlug}_{$periodSlug}.pdf";

        try {
            $pdf = Pdf::loadView('monthly_timesheets.pdf_template', compact('timesheet', 'dailyAttendances'));
            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error("Error generating PDF for Timesheet ID {$timesheet->id}: " . $e->getMessage());
            Alert::error('Gagal Membuat PDF', 'Terjadi kesalahan saat membuat file PDF.');
            return redirect()->back();
        }
    }


    public function bulkApprove(Request $request)
    {
        $this->authorize('bulkApprove', MonthlyTimesheet::class);

        $validator = Validator::make($request->all(), [
            'selected_ids'   => 'required|array',
            'selected_ids.*' => 'required|integer|exists:monthly_timesheets,id',
            'approval_level' => ['required', Rule::in(['asisten', 'manager'])],
        ]);

        if ($validator->fails()) {
            Alert::error('Input Tidak Valid', $validator->errors()->first());
            return redirect()->back();
        }

        $validated = $validator->validated();
        $selectedIds = $validated['selected_ids'];
        $approvalLevel = $validated['approval_level'];
        $approver = Auth::user();

        // Otorisasi spesifik level (sebelumnya sudah ada di respons Anda)
        if ($approvalLevel === 'asisten' && !in_array($approver->jabatan, ['asisten manager analis', 'asisten manager preparator'])) {
            Alert::error('Akses Ditolak', 'Anda bukan Asisten Manager yang berwenang.');
            return redirect()->back();
        }
        if ($approvalLevel === 'manager' && $approver->jabatan !== 'manager') {
            Alert::error('Akses Ditolak', 'Anda bukan Manager.');
            return redirect()->back();
        }

        // Ambil data timesheet lengkap dengan relasi user untuk email
        $timesheetsToProcess = MonthlyTimesheet::with(['user:id,name,jabatan,email'])
            ->whereIn('id', $selectedIds)->get();

        $successCount = 0;
        $failCount = 0;
        $failedDetails = [];
        $emailFailCount = 0;
        $processedTimesheetsForNotification = collect(); // Untuk menampung timesheet yg diapprove manager

        DB::beginTransaction();
        try {
            foreach ($timesheetsToProcess as $timesheet) {
                $canProcess = false;
                $errorMessage = null;
                $pengajuJabatan = $timesheet->user?->jabatan; // Ambil jabatan pengaju

                if ($approvalLevel === 'asisten') {
                    if (in_array($timesheet->status, ['generated', 'rejected'])) {
                        if (($approver->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) ||
                            ($approver->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin']))
                        ) {
                            $canProcess = true;
                        } else {
                            $errorMessage = "Tidak berwenang (scope Asisten).";
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
                        // Tidak ada notifikasi email di level Asisten untuk bulk
                    }
                } elseif ($approvalLevel === 'manager') {
                    // Manager bisa approve yang statusnya 'pending_manager' atau 'rejected'
                    if (in_array($timesheet->status, ['pending_manager', 'rejected'])) {
                        // Pastikan policy juga mengizinkan jika ada logika tambahan di sana
                        if (Gate::allows('approveManager', $timesheet)) {
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
                        // Jika Asisten sebelumnya approve, biarkan datanya.
                        // Jika Manager approve langsung dari 'rejected', maka approved_by_asisten_id & approved_at_asisten akan null.
                        $timesheet->rejected_by_id = null;
                        $timesheet->rejected_at = null;
                        $timesheet->notes = $timesheet->notes ? $timesheet->notes . ' | Approved by Manager (Bulk).' : 'Approved by Manager (Bulk).';
                        $timesheet->save();
                        $successCount++;
                        $processedTimesheetsForNotification->push($timesheet->fresh(['user:id,name,email', 'rejecter:id,name', 'approverManager:id,name'])); // Kumpulkan untuk notifikasi
                    }
                }

                if (!$canProcess) {
                    $failCount++;
                    $userName = $timesheet->user?->name ?? 'N/A';
                    $reason = $errorMessage ?? 'Gagal diproses.';
                    $failedDetails[] = "ID {$timesheet->id} ({$userName}): " . $reason;
                    Log::warning("Bulk Approve Timesheet Failed: ID {$timesheet->id}. Reason: " . $reason . ". Approver: {$approver->id}");
                }
            } // End foreach

            DB::commit(); // Commit semua perubahan database

            // Kirim Notifikasi Email SETELAH commit jika approval oleh Manager
            if ($approvalLevel === 'manager' && $processedTimesheetsForNotification->isNotEmpty()) {
                Log::info("Bulk Approve: Queuing final approval notifications for {$processedTimesheetsForNotification->count()} timesheets.");
                foreach ($processedTimesheetsForNotification as $approvedTimesheet) {
                    $employee = $approvedTimesheet->user;
                    if ($employee && $employee->email) {
                        try {
                            Mail::to($employee->email)->queue(new MonthlyTimesheetStatusNotification($approvedTimesheet, 'approved', $approver));
                            Log::info("MonthlyTimesheet (Bulk) approved notification queued for User ID {$employee->id}, Timesheet ID {$approvedTimesheet->id}.");
                        } catch (\Exception $e) {
                            $emailFailCount++;
                            Log::error("Failed to queue MonthlyTimesheet (Bulk) approved notification for User ID {$employee->id}, Timesheet ID {$approvedTimesheet->id}: " . $e->getMessage());
                        }
                    } else {
                        $emailFailCount++; // Hitung sebagai gagal jika email user tidak ada
                        Log::warning("Cannot send MonthlyTimesheet (Bulk) approved notification: Employee or email not found for Timesheet ID {$approvedTimesheet->id}.");
                    }
                }
            }

            // Siapkan Pesan Feedback
            $successMessage = "{$successCount} timesheet berhasil diproses.";
            if ($failCount > 0 || $emailFailCount > 0) {
                $alertMessage = $successMessage;
                if ($failCount > 0) {
                    $errorList = implode("<br>", array_map('htmlspecialchars', $failedDetails)); // Gunakan <br> untuk HTML
                    $alertMessage .= "<br><br>Namun, {$failCount} timesheet gagal diproses:<br><div style='text-align:left; font-size: smaller; max-height: 150px; overflow-y: auto;'><pre>{$errorList}</pre></div>";
                }
                if ($emailFailCount > 0) {
                    $alertMessage .= "<br><br>{$emailFailCount} notifikasi email gagal diantrikan/dikirim.";
                }
                Alert::html('Proses Selesai Sebagian', $alertMessage, 'warning')->persistent(true, false);
            } else {
                Alert::success('Berhasil', $successMessage);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error during bulk approve timesheet: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Total', 'Terjadi kesalahan sistem saat memproses persetujuan massal.');
        }

        $redirectRoute = $approvalLevel === 'manager' ? 'monthly_timesheets.approval.manager.list' : 'monthly_timesheets.approval.asisten.list';
        return redirect()->route($redirectRoute);
    }

    public function forceReprocess(MonthlyTimesheet $timesheet)
    {
        $this->authorize('forceReprocess', $timesheet);

        // Pindahkan pengecekan otorisasi yang lebih spesifik jika diperlukan (seperti di bawah)
        // atau pastikan policy 'forceReprocess' sudah mencakup ini.
        if (!(Auth::user()->role === 'admin' || (Auth::user()->role === 'manajemen' && Auth::user()->jabatan === 'manager'))) {
            Alert::error('Akses Ditolak', 'Anda tidak memiliki izin untuk melakukan aksi ini.');
            return redirect()->back();
        }

        try {
            // $periodStartDateOriginal = Carbon::parse($timesheet->period_start_date);
            $user = $timesheet->user()->with('vendor')->first(); // Ambil user dengan vendornya
            $vendorName = $user->vendor?->name;

            // Tentukan bulan dan tahun yang akan dikirim ke command
            // Ini adalah tanggal acuan yang akan digunakan oleh getUserPeriod di dalam command
            $referenceDateForCommand = Carbon::parse($timesheet->period_start_date);

            if ($vendorName === 'PT Cakra Satya Internusa') {
                // Untuk CSI, jika period_start_date adalah tanggal 16,
                // maka "bulan acuan" untuk getUserPeriod agar menghasilkan periode yang benar
                // adalah bulan dari period_end_date timesheet tersebut.
                // Contoh: jika timesheet 16 Maret - 15 April, maka period_end_date adalah April.
                // Kita ingin getUserPeriod menerima April agar menghasilkan 16 Maret - 15 April.
                $referenceDateForCommand = Carbon::parse($timesheet->period_end_date);
            }
            // Untuk vendor lain, bulan dari period_start_date sudah cukup.

            $monthToSend = $referenceDateForCommand->month;
            $yearToSend = $referenceDateForCommand->year;

            Log::info("Attempting to force reprocess timesheet ID: {$timesheet->id} for User ID: {$timesheet->user_id}. Sending month: {$monthToSend}, year: {$yearToSend} to command.");
            Log::info("Force Reprocessing: Timesheet ID {$timesheet->id}, User ID {$timesheet->user_id}. Command params: Month={$monthToSend}, Year={$yearToSend}, UserID={$timesheet->user_id}, Force=true");


            $exitCode = Artisan::call('timesheet:generate-monthly', [
                '--month' => $monthToSend,
                '--year' => $yearToSend,
                '--user_id' => [$timesheet->user_id],
                '--force' => true
            ]);

            if ($exitCode === 0) {
                // Ambil ulang data periode dari timesheet yang mungkin sudah diupdate oleh command
                $updatedTimesheet = MonthlyTimesheet::find($timesheet->id);
                $displayPeriodStart = $updatedTimesheet ? Carbon::parse($updatedTimesheet->period_start_date)->format('F Y') : $referenceDateForCommand->format('F Y');

                Alert::success('Berhasil', 'Timesheet untuk ' . ($user->name ?? 'N/A') . ' terkait periode ' . $displayPeriodStart . ' telah dijadwalkan untuk diproses ulang.');
                Log::info("Timesheet ID {$timesheet->id} successfully triggered for force reprocessing by User ID " . Auth::id());
            } else {
                $output = Artisan::output();
                Log::error("Gagal memicu proses ulang timesheet ID {$timesheet->id} via controller. Exit Code: {$exitCode}. Output: " . $output);
                Alert::error('Gagal', 'Gagal memicu proses ulang timesheet. Silakan cek log sistem.');
            }
        } catch (\Exception $e) {
            Log::error("Error force reprocessing timesheet ID {$timesheet->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Error Sistem', 'Terjadi kesalahan sistem saat memproses permintaan.');
        }

        // Redirect kembali ke halaman show dari timesheet yang sama
        return redirect()->route('monthly_timesheets.show', $timesheet->id);
    }
}

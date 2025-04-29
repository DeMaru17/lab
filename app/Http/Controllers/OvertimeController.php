<?php

namespace App\Http\Controllers;

use App\Models\Overtime;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Siapkan untuk Policy nanti
use Barryvdh\DomPDF\Facade\Pdf; // <-- Import PDF Facade
use ZipArchive;

class OvertimeController extends Controller
{
    // use AuthorizesRequests; // Aktifkan jika pakai Policy

    private const MONTHLY_OVERTIME_LIMIT_MINUTES = 3240;

    // --- Method CRUD (index, create, store, edit, update, destroy) ---
    // ... (Kode method index, create, store, edit, update, destroy yang sudah ada) ...
    // Pastikan method index mengirim $monthlyTotals jika diperlukan untuk warning
    public function index(Request $request)
    {

        $query = Overtime::with([
            'user:id,name,jabatan',
            'rejecter:id,name' // <-- Pastikan ini ada
        ]);

        $user = Auth::user();
        $perPage = 15;
        $searchTerm = $request->input('search');
        $query = Overtime::with(['user:id,name,jabatan']);

        if ($user->role === 'personil') {
            $query->where('user_id', $user->id);
        } elseif ($searchTerm && in_array($user->role, ['admin', 'manajemen'])) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('uraian_pekerjaan', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', '%' . $searchTerm . '%'));
            });
        }
        $overtimes = $query->orderBy('tanggal_lembur', 'desc')
            ->orderBy('jam_mulai', 'desc')
            ->paginate($perPage);
        if ($searchTerm) {
            $overtimes->appends(['search' => $searchTerm]);
        }

        // Siapkan data total lembur (opsional untuk warning di index)
        $monthlyTotals = [];
        if ($overtimes->isNotEmpty()) {
            $userIds = $overtimes->pluck('user_id')->unique()->toArray();
            $usersOnPage = User::whereIn('id', $userIds)->with('vendor')->get()->keyBy('id'); // Load vendor juga
            foreach ($usersOnPage as $listUser) {
                $monthlyTotals[$listUser->id] = $this->getCurrentMonthOvertimeTotal($listUser);
            }
        }

        return view('overtimes.index', compact('overtimes', 'monthlyTotals'));
    }

    public function create()
    {
        // $this->authorize('create', Overtime::class);
        $users = [];
        if (Auth::user()->role === 'admin') {
            $users = User::whereIn('role', ['personil', 'admin'])->orderBy('name')->pluck('name', 'id');
        }
        $currentMonthTotal = 0;
        if (in_array(Auth::user()->role, ['personil', 'admin'])) {
            $currentMonthTotal = $this->getCurrentMonthOvertimeTotal(Auth::user());
        }
        $showWarning = ($currentMonthTotal >= self::MONTHLY_OVERTIME_LIMIT_MINUTES);
        return view('overtimes.create', compact('users', 'showWarning', 'currentMonthTotal'));
    }

    public function store(Request $request)
    {
        // $this->authorize('create', Overtime::class);
        $validatedData = $request->validate([
            'user_id' => Auth::user()->role === 'admin' ? 'required|exists:users,id' : 'nullable',
            'tanggal_lembur' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i', // Validasi 'after' bisa rumit jika lewat hari, handle di model/logika
            'uraian_pekerjaan' => 'required|string|max:1000',
        ]);

        $userId = Auth::user()->role === 'admin' ? $validatedData['user_id'] : Auth::id();
        $targetUser = User::with('vendor')->find($userId); // Load vendor untuk cek periode

        // Hitung durasi sementara
        $tempStartTime = Carbon::parse($validatedData['jam_mulai']);
        $tempEndTime = Carbon::parse($validatedData['jam_selesai']);
        if ($tempEndTime->lessThanOrEqualTo($tempStartTime)) {
            $tempEndTime->addDay();
        }
        $newDurationMinutes = $tempStartTime->diffInMinutes($tempEndTime);

        // Cek batas lembur bulanan
        $currentMonthTotal = $this->getCurrentMonthOvertimeTotal($targetUser, Carbon::parse($validatedData['tanggal_lembur']));
        $totalAfterSubmit = $currentMonthTotal + $newDurationMinutes;
        $exceedsLimit = ($totalAfterSubmit > self::MONTHLY_OVERTIME_LIMIT_MINUTES);

        $createData = $validatedData;
        $createData['user_id'] = $userId;
        $createData['status'] = 'pending';

        try {
            Overtime::create($createData); // Model event akan hitung durasi_menit
            if ($exceedsLimit) {
                $hoursOver = round(($totalAfterSubmit - self::MONTHLY_OVERTIME_LIMIT_MINUTES) / 60, 1);
                Alert::success('Berhasil Diajukan', 'Pengajuan lembur berhasil disimpan.')
                    ->persistent(true)
                    ->warning('Perhatian!', 'Total jam lembur bulan ini telah melebihi batas 54 jam (Sekitar ' . $hoursOver . ' jam lebih).');
            } else {
                Alert::success('Berhasil Diajukan', 'Pengajuan lembur berhasil disimpan.');
            }
            return redirect()->route('overtimes.index');
        } catch (\Exception $e) {
            Log::error("Error creating Overtime: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal menyimpan data lembur.');
            return redirect()->back()->withInput();
        }
    }

    public function edit(Overtime $overtime)
    {
        // $this->authorize('update', $overtime);
        $users = [];
        if (Auth::user()->role === 'admin') {
            $users = User::orderBy('name')->pluck('name', 'id');
        }
        return view('overtimes.edit', compact('overtime', 'users'));
    }

    public function update(Request $request, Overtime $overtime)
    {
        // $this->authorize('update', $overtime);
        $validatedData = $request->validate([
            'tanggal_lembur' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i', // Perlu validasi after yg benar jika edit
            'uraian_pekerjaan' => 'required|string|max:1000',
        ]);

        // Cek batas lembur sebelum update
        $targetUser = $overtime->user()->with('vendor')->first(); // Load vendor
        $tempStartTime = Carbon::parse($validatedData['jam_mulai']);
        $tempEndTime = Carbon::parse($validatedData['jam_selesai']);
        if ($tempEndTime->lessThanOrEqualTo($tempStartTime)) {
            $tempEndTime->addDay();
        }
        $newDurationMinutes = $tempStartTime->diffInMinutes($tempEndTime);

        $currentMonthTotal = $this->getCurrentMonthOvertimeTotal($targetUser, Carbon::parse($validatedData['tanggal_lembur']), $overtime->id);
        $totalAfterUpdate = $currentMonthTotal + $newDurationMinutes;
        $exceedsLimit = ($totalAfterUpdate > self::MONTHLY_OVERTIME_LIMIT_MINUTES);

        $updateData = $validatedData;
        // Reset approval jika data diubah?
        // $updateData['status'] = 'pending';
        // ... reset approval fields ...

        try {
            $overtime->update($updateData);
            if ($exceedsLimit) {
                $hoursOver = round(($totalAfterUpdate - self::MONTHLY_OVERTIME_LIMIT_MINUTES) / 60, 1);
                Alert::success('Berhasil Diperbarui', 'Data lembur berhasil diperbarui.')
                    ->persistent(true)
                    ->warning('Perhatian!', 'Total jam lembur bulan ini telah melebihi batas 54 jam (Sekitar ' . $hoursOver . ' jam lebih).');
            } else {
                Alert::success('Berhasil Diperbarui', 'Data lembur berhasil diperbarui.');
            }
            return redirect()->route('overtimes.index');
        } catch (\Exception $e) {
            Log::error("Error updating Overtime ID {$overtime->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal memperbarui data lembur.');
            return redirect()->back()->withInput();
        }
    }

    public function destroy(Overtime $overtime)
    {
        // $this->authorize('delete', $overtime);
        try {
            $overtime->delete();
            Alert::success('Sukses', 'Data lembur berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error("Error deleting Overtime ID {$overtime->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal menghapus data lembur.');
        }
        return redirect()->route('overtimes.index');
    }

    public function cancel(Overtime $overtime)
    {
        // $this->authorize('cancel', $overtime);
        if (Auth::id() !== $overtime->user_id || !in_array($overtime->status, ['pending', 'approved'])) {
            Alert::error('Gagal', 'Anda tidak dapat membatalkan pengajuan lembur ini.');
            return redirect()->route('overtimes.index');
        }
        // Tidak perlu cek tanggal mulai untuk lembur? Atau perlu? Asumsi tidak perlu.

        try {
            $overtime->status = 'cancelled';
            $overtime->save();
            Alert::success('Berhasil', 'Pengajuan lembur telah dibatalkan.');
        } catch (\Exception $e) {
            Log::error("Error cancelling Overtime ID {$overtime->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal membatalkan pengajuan lembur.');
        }
        return redirect()->route('overtimes.index');
    }


    // --- Method Approval Lembur ---

    /**
     * Menampilkan daftar lembur yang menunggu persetujuan Asisten Manager.
     */
    public function listForAsisten()
    {
        // Otorisasi akses halaman sudah dihandle middleware 'role:manajemen'
        $user = Auth::user(); // Asisten yang login
        $perPage = 50;

        $query = Overtime::where('status', 'pending')
            ->whereNull('approved_by_asisten_id')
            ->with(['user:id,name,jabatan', 'user.vendor:id,name']); // Eager load user & vendor

        // Filter berdasarkan jabatan Asisten Manager yang login
        if ($user->jabatan === 'asisten manager analis') {
            $query->whereHas('user', fn($q) => $q->whereIn('jabatan', ['analis', 'admin']));
        } elseif ($user->jabatan === 'asisten manager preparator') {
            $query->whereHas('user', fn($q) => $q->whereIn('jabatan', ['preparator', 'mekanik', 'admin']));
        } else {
            // Jika bukan Asisten yg relevan, jangan tampilkan apa-apa
            $query->whereRaw('1 = 0');
        }

        $pendingOvertimes = $query->orderBy('created_at', 'asc')->paginate($perPage);

        // Ambil total lembur bulanan untuk warning (opsional)
        $monthlyTotals = [];
        if ($pendingOvertimes->isNotEmpty()) {
            $userIds = $pendingOvertimes->pluck('user_id')->unique()->toArray();
            $usersOnPage = User::whereIn('id', $userIds)->with('vendor')->get()->keyBy('id');
            foreach ($usersOnPage as $listUser) {
                $monthlyTotals[$listUser->id] = $this->getCurrentMonthOvertimeTotal($listUser);
            }
        }

        // Return view approval asisten
        return view('overtimes.approval.asisten_list', compact('pendingOvertimes', 'monthlyTotals'));
    }

    /**
     * Menyetujui pengajuan lembur (Level 1 - Asisten Manager).
     */
    public function approveAsisten(Overtime $overtime) // Gunakan Route Model Binding
    {
        // Otorisasi menggunakan Policy (contoh)
        // $this->authorize('approveAsisten', $overtime);

        // Otorisasi Manual Sederhana (jika belum pakai policy)
        $approver = Auth::user();
        if ($approver->role !== 'manajemen' || $overtime->status !== 'pending') {
            Alert::error('Akses Ditolak', 'Aksi tidak diizinkan.');
            return redirect()->back();
        }
        $pengajuJabatan = $overtime->user->jabatan;
        $canApprove = false;
        if ($approver->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) $canApprove = true;
        elseif ($approver->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin'])) $canApprove = true;
        if (!$canApprove) {
            Alert::error('Akses Ditolak', 'Anda tidak berwenang menyetujui pengajuan ini.');
            return redirect()->back();
        }
        // --- Akhir Otorisasi Manual ---

        try {
            $overtime->approved_by_asisten_id = $approver->id;
            $overtime->approved_at_asisten = now();
            $overtime->status = 'pending_manager_approval'; // Lanjut ke L2
            $overtime->save();

            // TODO: Notifikasi ke Manager?

            Alert::success('Berhasil', 'Pengajuan lembur disetujui & menunggu persetujuan Manager.');
        } catch (\Exception $e) {
            Log::error("Error approving L1 Overtime ID {$overtime->id} by user {$approver->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal memproses persetujuan L1');
        }
        // Pastikan nama route benar
        return redirect()->route('overtimes.approval.asisten.list');
    }

    /**
     * Menampilkan daftar lembur yang menunggu persetujuan Manager (Level 2).
     */
    public function listForManager()
    {
        // Otorisasi akses halaman sudah dihandle middleware 'role:manajemen'
        // Tambahan cek jabatan manager jika perlu
        if (Auth::user()->jabatan !== 'manager') {
            Alert::error('Akses Ditolak', 'Hanya Manager yang dapat mengakses.');
            return redirect()->route('dashboard.index');
        }

        $perPage = 50;
        $pendingOvertimesManager = Overtime::where('status', 'pending_manager_approval')
            ->whereNull('approved_by_manager_id')->whereNull('rejected_by_id')
            ->with(['user:id,name,jabatan', 'approverAsisten:id,name']) // Load user & approver L1
            ->orderBy('approved_at_asisten', 'asc')
            ->paginate($perPage);

        // Ambil total lembur bulanan untuk warning (opsional)
        $monthlyTotals = [];
        if ($pendingOvertimesManager->isNotEmpty()) {
            $userIds = $pendingOvertimesManager->pluck('user_id')->unique()->toArray();
            $usersOnPage = User::whereIn('id', $userIds)->with('vendor')->get()->keyBy('id');
            foreach ($usersOnPage as $listUser) {
                $monthlyTotals[$listUser->id] = $this->getCurrentMonthOvertimeTotal($listUser);
            }
        }

        // Return view approval manager
        return view('overtimes.approval.manager_list', compact('pendingOvertimesManager', 'monthlyTotals'));
    }

    /**
     * Menyetujui pengajuan lembur (Level 2 - Manager Final).
     */
    public function approveManager(Overtime $overtime)
    {
        // Otorisasi menggunakan Policy (contoh)
        // $this->authorize('approveManager', $overtime);

        // Otorisasi Manual Sederhana
        $approver = Auth::user();
        if ($approver->role !== 'manajemen' || $approver->jabatan !== 'manager' || $overtime->status !== 'pending_manager_approval') {
            Alert::error('Akses Ditolak', 'Aksi tidak diizinkan.');
            return redirect()->back();
        }
        // --- Akhir Otorisasi Manual ---

        DB::beginTransaction(); // Lembur tidak ubah kuota, tapi transaksi tetap baik
        try {
            // Update Status Lembur
            $overtime->approved_by_manager_id = $approver->id;
            $overtime->approved_at_manager = now();
            $overtime->status = 'approved'; // Status Final
            $overtime->rejected_by_id = null; // Reset rejection
            $overtime->rejected_at = null;
            $overtime->notes = null;
            $overtime->save();

            DB::commit();

            // TODO: Notifikasi ke Pengaju?

            Alert::success('Berhasil', 'Pengajuan lembur untuk ' . $overtime->user->name . ' telah disetujui.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error approving L2 Overtime ID {$overtime->id} by manager {$approver->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal memproses persetujuan L2.');
        }
        // Pastikan nama route benar
        return redirect()->route('overtimes.approval.manager.list');
    }

    /**
     * Menolak pengajuan lembur.
     */
    public function reject(Request $request, Overtime $overtime)
    {
        // Otorisasi menggunakan Policy (contoh)
        // $this->authorize('reject', $overtime);

        $rejecter = Auth::user();
        $validated = $request->validate(['notes' => 'required|string|max:500']);

        // --- Otorisasi Manual ---
        if ($rejecter->role !== 'manajemen') {
            Alert::error('Akses Ditolak');
            return redirect()->back();
        }
        $canReject = false;
        if ($overtime->status === 'pending') {
            $pengajuJabatan = $overtime->user->jabatan;
            if (($rejecter->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) ||
                ($rejecter->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin']))
            ) {
                $canReject = true;
            }
        } elseif ($overtime->status === 'pending_manager_approval') {
            if ($rejecter->jabatan === 'manager') {
                $canReject = true;
            }
        }
        if (!$canReject || !in_array($overtime->status, ['pending', 'pending_manager_approval'])) {
            Alert::error('Akses Ditolak', 'Anda tidak dapat menolak pengajuan ini.');
            return redirect()->back();
        }
        // --- Akhir Otorisasi Manual ---

        try {
            $overtime->rejected_by_id = $rejecter->id;
            $overtime->rejected_at = now();
            $overtime->status = 'rejected';
            $overtime->notes = $validated['notes'];
            if ($rejecter->jabatan === 'manager') { // Reset L1 jika direject L2
                $overtime->approved_by_asisten_id = null;
                $overtime->approved_at_asisten = null;
            }
            $overtime->save();
            Alert::success('Berhasil', 'Pengajuan lembur telah ditolak.');
        } catch (\Exception $e) {
            Log::error("Error rejecting Overtime ID {$overtime->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal memproses penolakan.');
        }

        if ($rejecter->jabatan === 'manager') return redirect()->route('overtimes.approval.manager.list');
        else return redirect()->route('overtimes.approval.asisten.list');
    }

    public function bulkApprove(Request $request)
    {
        // Validasi input dasar
        $validated = $request->validate([
            'selected_ids'   => 'required|array', // Pastikan array ID dikirim
            'selected_ids.*' => 'required|integer|exists:overtimes,id', // Setiap ID harus ada di tabel overtimes
            'approval_level' => 'required|in:asisten,manager', // Pastikan level approval valid
        ]);

        $selectedIds = $validated['selected_ids'];
        $approvalLevel = $validated['approval_level'];
        $approver = Auth::user();

        $successCount = 0;
        $failCount = 0;
        $failedDetails = []; // Simpan detail kegagalan

        // Ambil semua data lembur yang dipilih sekaligus
        $overtimesToProcess = Overtime::with('user:id,name,jabatan') // Load user untuk cek jabatan
            ->whereIn('id', $selectedIds)
            ->get();

        DB::beginTransaction();
        try {
            foreach ($overtimesToProcess as $overtime) {
                $canProcess = false;
                $errorMessage = null;

                // Lakukan otorisasi dan validasi per item
                if ($approvalLevel === 'asisten') {
                    // Otorisasi Level 1
                    if ($overtime->status === 'pending') {
                        $pengajuJabatan = $overtime->user->jabatan;
                        if (($approver->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) ||
                            ($approver->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin']))
                        ) {
                            $canProcess = true;
                        } else {
                            $errorMessage = "Tidak berwenang untuk jabatan pengaju.";
                        }
                    } else {
                        $errorMessage = "Status bukan 'pending'.";
                    }

                    // Jika lolos, update ke pending L2
                    if ($canProcess) {
                        $overtime->approved_by_asisten_id = $approver->id;
                        $overtime->approved_at_asisten = now();
                        $overtime->status = 'pending_manager_approval';
                        $overtime->save();
                        $successCount++;
                    }
                } elseif ($approvalLevel === 'manager') {
                    // Otorisasi Level 2
                    if ($approver->jabatan === 'manager' && $overtime->status === 'pending_manager_approval') {
                        $canProcess = true;
                    } else if ($approver->jabatan !== 'manager') {
                        $errorMessage = "Hanya Manager yang bisa approve L2.";
                    } else {
                        $errorMessage = "Status bukan 'pending_manager_approval'.";
                    }

                    // Jika lolos, update ke approved
                    if ($canProcess) {
                        $overtime->approved_by_manager_id = $approver->id;
                        $overtime->approved_at_manager = now();
                        $overtime->status = 'approved';
                        $overtime->rejected_by_id = null; // Reset reject jika ada
                        $overtime->rejected_at = null;
                        $overtime->notes = null;
                        $overtime->save();
                        $successCount++;
                    }
                }

                // Catat jika gagal
                if (!$canProcess) {
                    $failCount++;
                    $failedDetails[] = "ID {$overtime->id} ({$overtime->user->name}): " . ($errorMessage ?? 'Gagal otorisasi/validasi.');
                    Log::warning("Bulk Approve Overtime Failed: ID {$overtime->id}. Reason: " . ($errorMessage ?? 'Authorization/Validation failed') . ". Approver: {$approver->id}");
                }
            } // End foreach

            DB::commit(); // Simpan semua perubahan jika tidak ada error fatal

            // Siapkan notifikasi
            if ($failCount > 0) {
                $errorList = implode("\n", $failedDetails);
                Alert::success('Proses Selesai', "{$successCount} pengajuan berhasil disetujui.")
                    ->persistent(true) // Tampilkan lebih lama
                    ->warning('Gagal Memproses', "{$failCount} pengajuan gagal diproses:\n" . $errorList);
            } else {
                Alert::success('Berhasil', "{$successCount} pengajuan lembur berhasil disetujui.");
            }
        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan semua jika ada error
            Log::error("Error during bulk approve overtime: " . $e->getMessage());
            Alert::error('Gagal Total', 'Terjadi kesalahan sistem saat memproses persetujuan massal.');
        }

        // Redirect kembali ke halaman approval yang sesuai
        if ($approvalLevel === 'manager') {
            return redirect()->route('overtimes.approval.manager.list');
        } else {
            return redirect()->route('overtimes.approval.asisten.list');
        }
    }


    // --- Helper Method untuk Hitung Total Lembur Bulanan ---
    private function getCurrentMonthOvertimeTotal(User $user, ?Carbon $targetDate = null, ?int $excludeOvertimeId = null): int
    {
        $targetDate = $targetDate ?? Carbon::today();
        // Pastikan relasi vendor sudah di-load atau load di sini
        $userVendorName = $user->vendor->name ?? null;
        if (!$user->relationLoaded('vendor')) {
            $user->load('vendor:id,name'); // Load jika belum
            $userVendorName = $user->vendor->name ?? null;
        }

        $periodStartDate = null;
        $periodEndDate = null;

        if ($userVendorName === 'PT Cakra Satya Internusa') { // Sesuaikan nama
            if ($targetDate->day >= 16) {
                $periodStartDate = $targetDate->copy()->day(16);
                $periodEndDate = $targetDate->copy()->addMonthNoOverflow()->day(15); // Hindari overflow bulan
            } else {
                $periodStartDate = $targetDate->copy()->subMonthNoOverflow()->day(16);
                $periodEndDate = $targetDate->copy()->day(15);
            }
        } else { // Default / Vendor lain
            $periodStartDate = $targetDate->copy()->startOfMonth();
            $periodEndDate = $targetDate->copy()->endOfMonth();
        }

        $query = Overtime::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereBetween('tanggal_lembur', [$periodStartDate->toDateString(), $periodEndDate->toDateString()]);

        if ($excludeOvertimeId) {
            $query->where('id', '!=', $excludeOvertimeId);
        }

        return (int) $query->sum('durasi_menit');
    }

    public function downloadOvertimePdf(Overtime $overtime) // Route Model Binding
    {
        // --- Otorisasi: Siapa yang boleh download? ---
        // Anda bisa gunakan Policy jika sudah dibuat: $this->authorize('downloadPdf', $overtime);
        // Contoh Otorisasi Manual: Hanya Admin atau Pemilik (jika sudah approved?)
        $user = Auth::user();
        if (!($user->role === 'admin' || $user->id === $overtime->user_id)) {
            Alert::error('Akses Ditolak', 'Anda tidak berhak mengunduh PDF lembur ini.');
            return redirect()->back();
        }
        // --- Akhir Otorisasi ---

        // --- Eager load relasi yang dibutuhkan ---
        $overtime->load([
            // Muat user, vendor, signature user
            'user' => function ($query) {
                $query->select('id', 'name', 'jabatan', 'signature_path', 'vendor_id')
                    ->with('vendor:id,name,logo_path');
            },
            // Muat approver dan signature mereka
            'approverAsisten:id,name,jabatan,signature_path',
            'approverManager:id,name,jabatan,signature_path',
            'rejecter:id,name' // Jika perlu tampilkan info rejecter
        ]);
        // --- Akhir Eager load ---

        // Buat nama file
        $filename = 'lembur_'
            . str_replace(' ', '_', $overtime->user->name ?? 'user') . '_'
            . ($overtime->tanggal_lembur ? $overtime->tanggal_lembur->format('Ymd') : 'nodate')
            . '.pdf';

        // Load view Blade khusus untuk PDF lembur, kirim data $overtime
        try {
            $pdf = Pdf::loadView('overtimes.pdf_template', compact('overtime'));
            // Opsi: Set ukuran kertas & orientasi jika perlu
            // $pdf->setPaper('a4', 'portrait');

            // Langsung download file PDF
            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error("Error generating Overtime PDF for ID {$overtime->id}: " . $e->getMessage());
            Alert::error('Gagal Membuat PDF', 'Terjadi kesalahan saat membuat file PDF lembur.');
            return redirect()->back();
        }
    }

    public function bulkDownloadPdf(Request $request)
    {
        // Validasi input
        $validated = $request->validate([
            'selected_ids'   => 'required|array',
            'selected_ids.*' => 'required|integer|exists:overtimes,id',
        ]);

        $selectedIds = $validated['selected_ids'];

        // Otorisasi: Siapa yang boleh bulk download? (Contoh: Hanya Admin)
        if (Auth::user()->role !== 'admin') {
            Alert::error('Akses Ditolak', 'Anda tidak berhak melakukan aksi ini.');
            return redirect()->back();
        }

        // Ambil data lembur yang dipilih lengkap dengan relasi
        $overtimesToExport = Overtime::with([
            'user' => function ($query) {
                $query->select('id', 'name', 'jabatan', 'signature_path', 'vendor_id')
                    ->with('vendor:id,name,logo_path');
            },
            'approverAsisten:id,name,jabatan,signature_path',
            'approverManager:id,name,jabatan,signature_path',
            'rejecter:id,name' // Jika perlu info rejecter di PDF
        ])
            ->whereIn('id', $selectedIds)
            ->get();

        if ($overtimesToExport->isEmpty()) {
            Alert::warning('Tidak Ada Data', 'Tidak ada data lembur yang valid untuk diekspor.');
            return redirect()->back();
        }

        // --- Proses Pembuatan ZIP ---
        $zip = new ZipArchive;
        // Buat nama file zip unik di direktori temporary storage
        $zipFileName = 'rekap_lembur_bulk_' . time() . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName); // Simpan di storage/app/temp

        // Pastikan direktori temp ada
        if (!Storage::exists('temp')) {
            Storage::makeDirectory('temp');
        }

        // Buka file zip untuk ditulis
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($overtimesToExport as $overtime) {
                // Generate nama file PDF individual
                $pdfFileName = 'lembur_'
                    . str_replace(' ', '_', $overtime->user->name ?? 'user') . '_'
                    . ($overtime->tanggal_lembur ? $overtime->tanggal_lembur->format('Ymd') : 'nodate')
                    . '_' . $overtime->id // Tambahkan ID agar unik
                    . '.pdf';

                // Generate konten PDF (gunakan view template yang sama)
                try {
                    $pdf = Pdf::loadView('overtimes.pdf_template', ['cuti' => $overtime]); // Kirim sbg var 'cuti' jika template sama
                    // Atau jika nama variabel di template pdf lembur adalah 'overtime':
                    // $pdf = Pdf::loadView('overtimes.pdf_template', compact('overtime'));

                    // Tambahkan PDF ke ZIP sebagai string
                    $zip->addFromString($pdfFileName, $pdf->output());
                } catch (\Exception $e) {
                    Log::error("Failed to generate PDF for Overtime ID {$overtime->id} during bulk export: " . $e->getMessage());
                    // Lewati file ini jika gagal generate
                }
            }

            // Tutup arsip ZIP setelah semua PDF ditambahkan
            $zip->close();

            // Jika ZIP berhasil dibuat, kirim sebagai download dan hapus file sementara
            if (file_exists($zipPath)) {
                return response()->download($zipPath)->deleteFileAfterSend(true);
            } else {
                Alert::error('Gagal Membuat ZIP', 'File ZIP tidak dapat dibuat.');
                return redirect()->back();
            }
        } else {
            Alert::error('Gagal Membuka ZIP', 'Tidak dapat membuka file ZIP untuk ditulis.');
            return redirect()->back();
        }
        // --- Akhir Proses Pembuatan ZIP ---
    }
} // End Class OvertimeController

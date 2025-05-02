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
use Illuminate\Support\Str;
use App\Mail\OvertimeStatusNotificationMail; // Import mail class
use Illuminate\Support\Facades\Mail; // Import Mail facade
use App\Mail\BulkOvertimeStatusNotificationMail; // Import mail class


class OvertimeController extends Controller
{
    use AuthorizesRequests; // Aktifkan jika pakai Policy

    private const MONTHLY_OVERTIME_LIMIT_MINUTES = 3240;

    // --- Method CRUD (index, create, store, edit, update, destroy) ---
    // ... (Kode method index, create, store, edit, update, destroy yang sudah ada) ...
    // Pastikan method index mengirim $monthlyTotals jika diperlukan untuk warning
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = 50; // Atau sesuai preferensi Anda
        $searchTerm = $request->input('search'); // Quick search

        // --- Ambil Data untuk Filter Dropdown ---
        $users = collect();
        $vendors = collect();
        if (in_array($user->role, ['admin', 'manajemen'])) {
            $users = User::orderBy('name')->select('id', 'name')->get();
            $vendors = Vendor::orderBy('name')->select('id', 'name')->get();
        }

        // --- Ambil Nilai Filter ---
        $selectedUserId = $request->input('filter_user_id');
        $selectedVendorId = $request->input('filter_vendor_id');
        $selectedStatus = $request->input('filter_status');
        // Default tanggal: bulan ini, tapi bisa kosong jika tidak difilter
        $startDate = $request->input('filter_start_date');
        $endDate = $request->input('filter_end_date');

        // --- Query Dasar ---
        $query = Overtime::with(['user:id,name,jabatan,vendor_id', 'user.vendor:id,name', 'rejecter:id,name']);

        // --- Terapkan Filter ---
        // Filter berdasarkan role (selalu diterapkan)
        if ($user->role === 'personil') {
            $query->where('user_id', $user->id);
        }

        // Filter berdasarkan tanggal (jika kedua tanggal diisi)
        if ($startDate && $endDate) {
            try {
                $query->whereBetween('tanggal_lembur', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()]);
            } catch (\Exception $e) {
                // Abaikan filter tanggal jika format tidak valid
                Log::warning('Invalid date format received for overtime filter: ' . $e->getMessage());
            }
        }

        // Filter berdasarkan status (jika dipilih)
        if ($selectedStatus) {
            $query->where('status', $selectedStatus);
        }

        // Filter berdasarkan user (hanya untuk admin/manajemen)
        if ($selectedUserId && in_array($user->role, ['admin', 'manajemen'])) {
            $query->where('user_id', $selectedUserId);
        }

        // Filter berdasarkan vendor (hanya untuk admin/manajemen)
        if ($selectedVendorId && in_array($user->role, ['admin', 'manajemen'])) {
            if ($selectedVendorId === 'is_null') { // Handle internal
                $query->whereHas('user', fn($q) => $q->whereNull('vendor_id'));
            } else {
                $query->whereHas('user', fn($q) => $q->where('vendor_id', $selectedVendorId));
            }
        }

        // Filter berdasarkan Quick Search (jika ada dan bukan personil)
        if ($searchTerm && $user->role !== 'personil') {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('uraian_pekerjaan', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', '%' . $searchTerm . '%'));
            });
        }
        // --- Akhir Terapkan Filter ---

        // Lakukan pagination
        $overtimes = $query->orderBy('tanggal_lembur', 'desc')
            ->orderBy('created_at', 'desc') // Urutan kedua berdasarkan waktu dibuat
            ->paginate($perPage);

        // --- Append Filter ke Pagination Links ---
        $overtimes->appends($request->except('page')); // Append semua query string kecuali 'page'

        // Siapkan data total lembur bulanan (opsional, bisa membebani jika data banyak)
        // Pertimbangkan untuk menghapus ini dari index jika tidak terlalu krusial
        $monthlyTotals = [];
        // ... (logika ambil monthlyTotals seperti sebelumnya jika masih diperlukan) ...

        return view('overtimes.index', compact(
            'overtimes',
            'monthlyTotals',
            'users', // Kirim data user untuk filter
            'vendors', // Kirim data vendor untuk filter
            'selectedUserId', // Kirim nilai filter terpilih
            'selectedVendorId',
            'selectedStatus',
            'startDate',
            'endDate'
        ));
    }

    public function create()
    {
        $this->authorize('create', Overtime::class);
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
        $this->authorize('create', Overtime::class);
        $validatedData = $request->validate([
            'user_id' => Auth::user()->role === 'admin' ? 'required|exists:users,id' : 'nullable',
            'tanggal_lembur' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i', // Validasi 'after' dihandle di logika/model
            'uraian_pekerjaan' => 'required|string|max:1000',
        ]);

        $userId = Auth::user()->role === 'admin' ? $validatedData['user_id'] : Auth::id();
        $targetUser = User::with('vendor')->find($userId);
        $tanggalLembur = Carbon::parse($validatedData['tanggal_lembur']); // Gunakan tanggal ini untuk cek overlap

        // --- VALIDASI OVERLAP (TAMBAHKAN DI SINI) ---
        if ($this->checkOverlap($userId, $tanggalLembur, $tanggalLembur)) { // Cek overlap pada tanggal yg diajukan
            Alert::error('Tanggal Bertabrakan', 'Anda sudah memiliki pengajuan lembur lain (pending/approved) pada tanggal ' . $tanggalLembur->format('d/m/Y') . '.');
            return back()->withInput();
        }
        // --- AKHIR VALIDASI OVERLAP ---


        // Hitung durasi sementara
        $tempStartTime = Carbon::parse($validatedData['jam_mulai']);
        $tempEndTime = Carbon::parse($validatedData['jam_selesai']);
        if ($tempEndTime->lessThanOrEqualTo($tempStartTime)) {
            $tempEndTime->addDay();
        }
        $newDurationMinutes = $tempStartTime->diffInMinutes($tempEndTime);

        // Cek batas lembur bulanan
        $currentMonthTotal = $this->getCurrentMonthOvertimeTotal($targetUser, $tanggalLembur);
        $totalAfterSubmit = $currentMonthTotal + $newDurationMinutes;
        $exceedsLimit = ($totalAfterSubmit > self::MONTHLY_OVERTIME_LIMIT_MINUTES);

        // Siapkan data untuk disimpan
        $createData = $validatedData;
        $createData['user_id'] = $userId;
        $createData['status'] = 'pending';

        try {
            Overtime::create($createData); // Model event akan hitung durasi_menit

            // Beri notifikasi sukses, tambahkan warning jika batas terlewati
            if ($exceedsLimit) {
                $hoursOver = round(($totalAfterSubmit - self::MONTHLY_OVERTIME_LIMIT_MINUTES) / 60, 1);
                Alert::success('Berhasil Diajukan', 'Pengajuan lembur berhasil disimpan.')
                    ->persistent(true)
                    ->warning('Perhatian!', 'Total jam lembur bulan ini telah melebihi batas 54 jam (Sekitar ' . $hoursOver . ' jam lebih).');
            } else {
                Alert::success('Berhasil Diajukan', 'Pengajuan lembur berhasil disimpan.');
            }

            return redirect()->route('overtimes.index'); // Sesuaikan nama route jika berbeda

        } catch (\Exception $e) {
            Log::error("Error creating Overtime: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal menyimpan data lembur.');
            return redirect()->back()->withInput();
        }
    }

    public function edit(Overtime $overtime)
    {
        $this->authorize('update', $overtime);
        $users = [];
        if (Auth::user()->role === 'admin') {
            $users = User::orderBy('name')->pluck('name', 'id');
        }
        return view('overtimes.edit', compact('overtime', 'users'));
    }

    public function update(Request $request, Overtime $overtime)
    {
        $this->authorize('update', $overtime);


        // Validasi input
        $validatedData = $request->validate([
            'tanggal_lembur' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i', // Validasi 'after' lebih kompleks jika lewat hari, dihandle di model
            'uraian_pekerjaan' => 'required|string|max:1000',
            // Tidak ada validasi status di sini karena akan direset
        ]);

        $startDate = Carbon::parse($validatedData['tanggal_lembur']); // Tanggal lembur baru
        $startTime = Carbon::parse($validatedData['jam_mulai']);
        $endTime = Carbon::parse($validatedData['jam_selesai']);
        $userId = $overtime->user_id; // User ID tetap sama
        $targetUser = $overtime->user()->with('vendor')->first(); // Ambil user dengan vendor

        // --- HITUNG ULANG DURASI (sementara, final di model event) ---
        $tempEndTimeForCalc = $endTime->copy(); // Salin agar tidak mengubah objek asli
        if ($tempEndTimeForCalc->lessThanOrEqualTo($startTime)) {
            $tempEndTimeForCalc->addDay();
        }
        $newLamaMenit = $startTime->diffInMinutes($tempEndTimeForCalc);
        // --- AKHIR HITUNG ULANG DURASI ---

        // --- VALIDASI ULANG BATAS LEMBUR ---
        $currentMonthTotal = $this->getCurrentMonthOvertimeTotal($targetUser, $startDate, $overtime->id); // Kecualikan ID ini
        $totalAfterUpdate = $currentMonthTotal + $newLamaMenit;
        $exceedsLimit = ($totalAfterUpdate > self::MONTHLY_OVERTIME_LIMIT_MINUTES);
        // --- AKHIR VALIDASI BATAS LEMBUR ---

        // --- VALIDASI ULANG OVERLAP ---
        if ($this->checkOverlap($userId, $startDate, $startDate, $overtime->id)) { // Cek overlap hanya pada tanggal lembur baru
            Alert::error('Tanggal Bertabrakan', 'Tanggal lembur yang Anda ajukan bertabrakan dengan pengajuan lain.');
            return back()->withInput();
        }
        // --- AKHIR VALIDASI OVERLAP ---


        // --- PROSES UPDATE DATA ---
        DB::beginTransaction();
        try {
            // Siapkan data untuk diupdate dari hasil validasi
            $updateData = $validatedData;

            // !! BAGIAN PENTING: RESET STATUS DAN APPROVAL !!
            $updateData['status'] = 'pending'; // Kembalikan ke pending
            $updateData['notes'] = null;       // Hapus catatan reject sebelumnya
            $updateData['approved_by_asisten_id'] = null;
            $updateData['approved_at_asisten'] = null;
            $updateData['approved_by_manager_id'] = null;
            $updateData['approved_at_manager'] = null;
            $updateData['rejected_by_id'] = null;
            $updateData['rejected_at'] = null;
            $updateData['last_reminder_sent_at'] = null; // Reset reminder juga

            // Lakukan update (Model event 'saving' akan menghitung ulang durasi_menit)
            $overtime->update($updateData);

            DB::commit(); // Konfirmasi transaksi

            // Beri notifikasi
            if ($exceedsLimit) {
                $hoursOver = round(($totalAfterUpdate - self::MONTHLY_OVERTIME_LIMIT_MINUTES) / 60, 1);
                Alert::success('Berhasil Diperbarui', 'Pengajuan lembur berhasil diperbarui dan diajukan ulang.')
                    ->persistent(true)
                    ->warning('Perhatian!', 'Total jam lembur bulan ini telah melebihi batas 54 jam (Sekitar ' . $hoursOver . ' jam lebih).');
            } else {
                Alert::success('Berhasil Diperbarui', 'Pengajuan lembur berhasil diperbarui dan diajukan ulang.');
            }
            return redirect()->route('overtimes.index'); // Sesuaikan nama route

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error updating Overtime ID {$overtime->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal menyimpan perubahan pengajuan lembur.');
            return back()->withInput();
        }
    }

    public function destroy(Overtime $overtime)
    {
        $this->authorize('delete', $overtime);
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
        $this->authorize('cancel', $overtime);
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
        $this->authorize('approveAsisten', $overtime);

        try {
            $overtime->approved_by_asisten_id = Auth::id();
            $overtime->approved_at_asisten = now();
            $overtime->status = 'pending_manager_approval';
            $overtime->save();
            Alert::success('Berhasil', 'Pengajuan lembur disetujui (L1)...');
        } catch (\Exception $e) {
            Log::error("Error approving L1 Overtime ID {$overtime->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal memproses persetujuan L1.');
        }
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
        $this->authorize('approveManager', $overtime);
        $approver = Auth::user();

        DB::beginTransaction(); // Lembur tidak ubah kuota, tapi transaksi tetap baik
        try {
            $overtime->approved_by_manager_id = Auth::id();
            $overtime->approved_at_manager = now();
            $overtime->status = 'approved';
            $overtime->rejected_by_id = null;
            $overtime->rejected_at = null;
            $overtime->notes = null;
            $overtime->save();
            DB::commit();

            // --- KIRIM EMAIL NOTIFIKASI APPROVAL ---
            try {
                $pengaju = $overtime->user()->first();
                if ($pengaju && $pengaju->email) {
                    Mail::to($pengaju->email)->queue(new OvertimeStatusNotificationMail($overtime, 'approved', $approver));
                    Log::info("Overtime approval notification sent to {$pengaju->email} for Overtime ID {$overtime->id}");
                } else {
                    Log::warning("Cannot send overtime approval notification: User or email not found for Overtime ID {$overtime->id}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to send overtime approval email for Overtime ID {$overtime->id}: " . $e->getMessage());
            }
            // --- AKHIR KIRIM EMAIL ---
            Alert::success('Berhasil', 'Pengajuan lembur ... telah disetujui.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error approving L2 Overtime ID {$overtime->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal memproses persetujuan L2.');
        }
        return redirect()->route('overtimes.approval.manager.list');
    }

    /**
     * Menolak pengajuan lembur.
     */
    public function reject(Request $request, Overtime $overtime)
    {
        // Otorisasi menggunakan Policy (contoh)
        $this->authorize('reject', $overtime);
        $rejecter = Auth::user();

        $validated = $request->validate(['notes' => 'required|string|max:500']);

        try {
            $overtime->rejected_by_id = Auth::id();
            $overtime->rejected_at = now();
            $overtime->status = 'rejected';
            $overtime->notes = $validated['notes'];
            if (Auth::user()->jabatan === 'manager') { // Reset L1 jika L2 reject
                $overtime->approved_by_asisten_id = null;
                $overtime->approved_at_asisten = null;
            }
            $overtime->save();
            // --- KIRIM EMAIL NOTIFIKASI REJECTION ---
            // 2. Kode ini dijalankan SETELAH save() berhasil
            try {
                $pengaju = $overtime->user()->first();
                if ($pengaju && $pengaju->email) {
                    // Mengirim Mailable dengan status 'rejected' dan data $rejecter
                    Mail::to($pengaju->email)->queue(new OvertimeStatusNotificationMail($overtime, 'rejected', $rejecter));
                    Log::info("Overtime rejection notification sent to {$pengaju->email} for Overtime ID {$overtime->id}");
                } else {
                    Log::warning("Cannot send overtime rejection notification: User or email not found for Overtime ID {$overtime->id}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to send overtime rejection email for Overtime ID {$overtime->id}: " . $e->getMessage());
            }
            // --- AKHIR KIRIM EMAIL ---
            Alert::success('Berhasil', 'Pengajuan lembur telah ditolak.');
        } catch (\Exception $e) {
            Log::error("Error rejecting Overtime ID {$overtime->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal memproses penolakan.');
        }

        if (Auth::user()->jabatan === 'manager') return redirect()->route('overtimes.approval.manager.list');
        else return redirect()->route('overtimes.approval.asisten.list');
    }

    public function bulkApprove(Request $request)
    {
        // Cek policy umum untuk bulk approve (jika pakai policy)
        // $this->authorize('bulkApprove', Overtime::class);

        // Validasi input dasar
        $validated = $request->validate([
            'selected_ids'   => 'required|array',
            'selected_ids.*' => 'required|integer|exists:overtimes,id',
            'approval_level' => 'required|in:asisten,manager',
        ]);

        $selectedIds = $validated['selected_ids'];
        $approvalLevel = $validated['approval_level'];
        $approver = Auth::user();


        $successCount = 0;
        $failCount = 0;
        $emailFailCount = 0;
        $failedDetails = [];
        $approvedRequestsByUser = []; // <-- Array untuk kelompokkan hasil approve per user

        // Ambil data lembur yang dipilih lengkap dengan relasi user
        $overtimesToProcess = Overtime::with(['user:id,name,jabatan,email']) // Perlu email user
            ->whereIn('id', $selectedIds)
            ->get();

        DB::beginTransaction();
        try {
            foreach ($overtimesToProcess as $overtime) {
                $canProcess = false;
                $errorMessage = null;
                $newStatus = null;

                // Lakukan otorisasi dan validasi per item
                if ($approvalLevel === 'asisten') {
                    // ... (Logika otorisasi & update status Asisten) ...
                    if ($overtime->status === 'pending') {
                        $pengajuJabatan = $overtime->user->jabatan;
                        if (($approver->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) ||
                            ($approver->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin']))
                        ) {
                            $canProcess = true;
                            $newStatus = 'pending_manager_approval';
                        } else {
                            $errorMessage = "Tidak berwenang.";
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
                        // Tidak ada email di L1
                    }
                } elseif ($approvalLevel === 'manager') {
                    // ... (Logika otorisasi Manager) ...
                    if ($approver->jabatan === 'manager' && $overtime->status === 'pending_manager_approval') {
                        $canProcess = true;
                        $newStatus = 'approved';
                    } else if ($approver->jabatan !== 'manager') {
                        $errorMessage = "Hanya Manager.";
                    } else {
                        $errorMessage = "Status bukan 'pending_manager_approval'.";
                    }

                    if ($canProcess) {
                        // ... (Update status & data approval Manager) ...
                        $overtime->approved_by_manager_id = $approver->id;
                        $overtime->approved_at_manager = now();
                        $overtime->status = $newStatus;
                        $overtime->rejected_by_id = null;
                        $overtime->rejected_at = null;
                        $overtime->notes = null;
                        $overtime->save();
                        $successCount++;

                        // --- KUMPULKAN UNTUK EMAIL BULK ---
                        $pengaju = $overtime->user;
                        if ($pengaju) {
                            // Gunakan user_id sebagai key
                            $approvedRequestsByUser[$pengaju->id]['user'] = $pengaju; // Simpan objek user
                            $approvedRequestsByUser[$pengaju->id]['requests'][] = $overtime; // Tambahkan overtime ke list
                        }
                        // --- AKHIR KUMPULKAN ---
                    }
                }

                // Catat jika gagal proses approval/validasi item ini
                if (!$canProcess) {
                    $failCount++;
                    $failedDetails[] = "ID {$overtime->id} ({$overtime->user->name}): " . ($errorMessage ?? 'Gagal.');
                    Log::warning("Bulk Approve Overtime Failed: ID {$overtime->id}. Reason: " . ($errorMessage ?? 'Failed') . ". Approver: {$approver->id}");
                }
            } // End foreach

            DB::commit(); // Simpan semua perubahan DB

            // --- KIRIM EMAIL RINGKASAN SETELAH COMMIT ---
            if ($approvalLevel === 'manager' && !empty($approvedRequestsByUser)) {
                Log::info("Mengirim email notifikasi ringkasan...");
                foreach ($approvedRequestsByUser as $userId => $userData) {
                    $pengajuUser = $userData['user'];
                    $approvedList = collect($userData['requests']); // Jadikan collection

                    if ($pengajuUser && $pengajuUser->email && $approvedList->isNotEmpty()) {
                        try {
                            // Gunakan Mailable baru
                            Mail::to($pengajuUser->email)->queue(new BulkOvertimeStatusNotificationMail($approvedList, $approver, $pengajuUser));
                            Log::info("Bulk Overtime approval notification queued for {$pengajuUser->email} for " . $approvedList->count() . " requests.");
                        } catch (\Exception $e) {
                            $emailFailCount++;
                            Log::error("Failed to queue bulk overtime approval email for User ID {$userId}: " . $e->getMessage());
                        }
                    } else {
                        Log::warning("Skipping bulk email for User ID {$userId} due to missing user/email/requests.");
                        if ($approvedList->isNotEmpty()) $emailFailCount += $approvedList->count(); // Hitung sbg gagal jika ada request
                    }
                }
            }
            // --- AKHIR KIRIM EMAIL RINGKASAN ---


            // Siapkan notifikasi SweetAlert (tetap sama)
            $successMessage = "{$successCount} pengajuan berhasil diproses.";
            if ($failCount > 0) {
                $errorList = implode("\n", $failedDetails);
                Alert::success('Proses Selesai', $successMessage)
                    ->persistent(true)
                    ->warning('Gagal Memproses', "{$failCount} pengajuan gagal diproses:\n" . $errorList);
            } else {
                Alert::success('Berhasil', $successMessage);
            }
            if ($emailFailCount > 0) {
                Alert::info('Info Tambahan', "{$emailFailCount} notifikasi email gagal dikirim/diantrikan. Silakan cek log.");
            }
        } catch (\Exception $e) {
            DB::rollBack();
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
        $this->authorize('downloadPdf', $overtime);


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

        $tanggalLemburFormatted = $overtime->tanggal_lembur ? $overtime->tanggal_lembur->format('dmY') : 'nodate';
        // Ganti spasi di nama user dengan underscore, buat lowercase (opsional)
        $namaPengajuFormatted = Str::slug($overtime->user->name ?? 'user', '_');

        $filename =  $tanggalLemburFormatted . '_lembur' . '_' . $namaPengajuFormatted . '.pdf';

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

        // Otorisasi: Hanya Admin
        if (Auth::user()->role !== 'admin') {
            Alert::error('Akses Ditolak', 'Anda tidak berhak melakukan aksi ini.');
            return redirect()->back();
        }

        // Ambil data lembur yang dipilih
        $overtimesToExport = Overtime::with([
            'user' => fn($q) => $q->select('id', 'name', 'jabatan', 'signature_path', 'vendor_id')->with('vendor:id,name,logo_path'),
            'approverAsisten:id,name,jabatan,signature_path',
            'approverManager:id,name,jabatan,signature_path',
            'rejecter:id,name'
        ])->whereIn('id', $selectedIds)->get();

        if ($overtimesToExport->isEmpty()) {
            Alert::warning('Tidak Ada Data', 'Tidak ada data lembur valid untuk diekspor.');
            return redirect()->back();
        }

        // --- Proses Pembuatan ZIP ---
        $zip = new ZipArchive;
        $zipFileName = 'lembur_' . date('dmY_His') . '.zip';
        $tempDir = storage_path('app/temp'); // Direktori temporary
        $zipPath = $tempDir . '/' . $zipFileName;

        // Pastikan direktori temp ada dan writable
        if (!Storage::exists('temp')) {
            Storage::makeDirectory('temp');
        }
        if (!is_writable($tempDir)) {
            Log::error("Temporary directory not writable: " . $tempDir);
            Alert::error('Error Server', 'Direktori penyimpanan sementara tidak dapat ditulis.');
            return redirect()->back();
        }

        // Buka file zip
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            Log::error("Cannot open ZIP file for writing: " . $zipPath);
            Alert::error('Gagal Membuka ZIP', 'Tidak dapat membuka file ZIP untuk ditulis.');
            return redirect()->back();
        }

        $pdfGenerationErrors = 0;
        foreach ($overtimesToExport as $overtime) {
            // --- Format Nama File PDF Baru (di dalam loop) ---
            $tanggalLemburFormatted = $overtime->tanggal_lembur ? $overtime->tanggal_lembur->format('dmy_His') : 'nodate';
            // Ganti spasi di nama user dengan underscore, buat lowercase (opsional)
            $namaPengajuFormatted = Str::slug($overtime->user->name ?? 'user', '_');

            $pdfFileName =  $tanggalLemburFormatted . '_lembur' . '_' . $namaPengajuFormatted . $overtime->id . '.pdf';

            try {
                $pdf = Pdf::loadView('overtimes.pdf_template', compact('overtime'));
                // Tambahkan PDF ke ZIP
                $zip->addFromString($pdfFileName, $pdf->output());
            } catch (\Exception $e) {
                $pdfGenerationErrors++;
                Log::error("Failed PDF generation (Overtime ID {$overtime->id}) in bulk: " . $e->getMessage());
                // Lanjutkan ke PDF berikutnya
            }
        }

        // Tutup arsip ZIP
        $zip->close();

        // Jika tidak ada PDF yang berhasil dibuat ATAU file zip tidak ada
        if ($pdfGenerationErrors === $overtimesToExport->count() || !file_exists($zipPath)) {
            Alert::error('Gagal Membuat PDF', 'Tidak ada file PDF yang berhasil dibuat untuk dimasukkan ke dalam ZIP.');
            if (file_exists($zipPath)) unlink($zipPath); // Hapus file zip kosong jika terbuat
            return redirect()->back();
        }

        // Kirim ZIP untuk download dan hapus file sementara
        return response()->download($zipPath)->deleteFileAfterSend(true);
        // --- Akhir Proses Pembuatan ZIP ---
    }

    private function checkOverlap(int $userId, Carbon $startDate, Carbon $endDate, ?int $excludeOvertimeId = null): bool
    {
        // Untuk lembur, kita hanya perlu cek apakah ada lembur lain di tanggal yang sama
        // Atau mungkin cek overlap jam? Untuk simpel, cek tanggal dulu.
        $query = Overtime::where('user_id', $userId)
            ->where('tanggal_lembur', $startDate->toDateString()) // Cek di tanggal yg sama
            ->whereIn('status', ['pending', 'pending_manager_approval', 'approved']); // Cek status yg relevan

        if ($excludeOvertimeId) {
            $query->where('id', '!=', $excludeOvertimeId);
        }

        return $query->exists(); // Cukup cek apakah ada record lain di tanggal itu
    }
} // End Class OvertimeController

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cuti;
use App\Models\CutiQuota;
use App\Models\JenisCuti;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use RealRashid\SweetAlert\Facades\Alert;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException; // Import untuk catch
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Mail\LeaveStatusNotificationMail;
use Illuminate\Support\Facades\Mail;

class CutiController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     * Otorisasi viewAny biasanya tidak dicek eksplisit di index,
     * karena filtering data sudah dilakukan berdasarkan role.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = 15;
        $searchTerm = $request->input('search');
        $query = Cuti::with(['user:id,name,jabatan', 'jenisCuti:id,nama_cuti', 'rejecter:id,name']);

        if ($user->role === 'personil') {
            $query->where('user_id', $user->id);
        } elseif ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('keperluan', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                        $userQuery->where('name', 'like', '%' . $searchTerm . '%');
                    })
                    ->orWhereHas('jenisCuti', function ($jenisCutiQuery) use ($searchTerm) {
                        $jenisCutiQuery->where('nama_cuti', 'like', '%' . $searchTerm . '%');
                    });
            });
        }
        $cuti = $query->orderBy('updated_at', 'desc')->paginate($perPage);
        if ($searchTerm) {
            $cuti->appends(['search' => $searchTerm]);
        }
        $cutiQuota = CutiQuota::where('user_id', $user->id)->get()->keyBy('jenis_cuti_id');

        return view('cuti.index', compact('cuti', 'cutiQuota'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Gunakan policy untuk cek hak akses create
        $this->authorize('create', Cuti::class);

        $jenisCuti = JenisCuti::orderBy('nama_cuti')->get();
        $currentKuota = CutiQuota::where('user_id', Auth::id())->pluck('durasi_cuti', 'jenis_cuti_id');
        return view('cuti.create', compact('jenisCuti', 'currentKuota'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Otorisasi create
        $this->authorize('create', Cuti::class);

        $validatedData = $request->validate([
            'jenis_cuti_id' => 'required|exists:jenis_cuti,id',
            'mulai_cuti' => 'required|date',
            'selesai_cuti' => 'required|date|after_or_equal:mulai_cuti',
            'keperluan' => 'required|string|max:1000',
            'alamat_selama_cuti' => 'required|string|max:255',
            'surat_sakit' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ], [
            'surat_sakit.required' => 'Surat sakit wajib diunggah untuk cuti sakit 2 hari kerja atau lebih.'
        ]);

        $startDate = Carbon::parse($validatedData['mulai_cuti']);
        $endDate = Carbon::parse($validatedData['selesai_cuti']);
        $jenisCutiId = $validatedData['jenis_cuti_id'];
        $userId = Auth::id();

        // --- PERHITUNGAN HARI KERJA EFEKTIF ---
        $lamaCuti = $this->calculateWorkdays($startDate, $endDate);
        if ($lamaCuti === false) { // Handle error dari calculateWorkdays
            Alert::error('Gagal', 'Terjadi kesalahan saat memproses data hari libur.');
            return back()->withInput();
        }
        // --- AKHIR PERHITUNGAN HARI KERJA ---

        // --- VALIDASI KUOTA & ATURAN CUTI SAKIT ---
        $jenisCuti = JenisCuti::find($jenisCutiId); // Sudah divalidasi exists
        $isCutiSakit = (strtolower($jenisCuti->nama_cuti) === 'cuti sakit');
        if (!$isCutiSakit) {
            $cutiQuota = CutiQuota::where('user_id', $userId)->where('jenis_cuti_id', $jenisCutiId)->first();
            if ($lamaCuti > 0 && (!$cutiQuota || $cutiQuota->durasi_cuti < $lamaCuti)) {
                Alert::error('Kuota Tidak Cukup', 'Sisa kuota cuti (' . ($cutiQuota->durasi_cuti ?? 0) . ' hari) tidak mencukupi.');
                return back()->withInput();
            }
        }
        if ($isCutiSakit && $lamaCuti >= 2 && !$request->hasFile('surat_sakit')) {
            return back()->withInput()->withErrors(['surat_sakit' => 'Surat sakit wajib diunggah.']);
        }
        // --- AKHIR VALIDASI KUOTA & ATURAN CUTI SAKIT ---

        // --- VALIDASI OVERLAP ---
        if ($this->checkOverlap($userId, $startDate, $endDate)) {
            Alert::error('Tanggal Bertabrakan', 'Tanggal cuti yang Anda ajukan bertabrakan dengan pengajuan lain.');
            return back()->withInput();
        }
        // --- AKHIR VALIDASI OVERLAP ---

        // --- SIMPAN PENGAJUAN CUTI ---
        $cutiData = $validatedData; // Ambil data yg sudah divalidasi
        $cutiData['user_id'] = $userId;
        $cutiData['lama_cuti'] = $lamaCuti;
        $cutiData['status'] = 'pending';

        if ($request->hasFile('surat_sakit')) {
            try {
                $cutiData['surat_sakit'] = $request->file('surat_sakit')->store('surat_sakit', 'public');
            } catch (\Exception $e) {
                Log::error("File Upload Error (Store): " . $e->getMessage());
                Alert::error('Gagal', 'Gagal mengunggah file surat sakit.');
                return back()->withInput();
            }
        }

        Cuti::create($cutiData);
        Alert::success('Berhasil', 'Pengajuan cuti (' . $lamaCuti . ' hari kerja) berhasil diajukan.');
        return redirect()->route('cuti.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cuti $cuti)
    {
        // Gunakan policy untuk cek hak akses update
        $this->authorize('update', $cuti);

        $jenisCuti = JenisCuti::orderBy('nama_cuti')->get();
        return view('cuti.edit', compact('cuti', 'jenisCuti'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cuti $cuti)
    {
        // Gunakan policy untuk cek hak akses update
        $this->authorize('update', $cuti);

        // Validasi input
        $validatedData = $request->validate([
            'jenis_cuti_id' => 'required|exists:jenis_cuti,id',
            'mulai_cuti' => 'required|date',
            'selesai_cuti' => 'required|date|after_or_equal:mulai_cuti',
            'keperluan' => 'required|string|max:1000',
            'alamat_selama_cuti' => 'required|string|max:255',
            'surat_sakit' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $startDate = Carbon::parse($validatedData['mulai_cuti']);
        $endDate = Carbon::parse($validatedData['selesai_cuti']);
        $jenisCutiId = $validatedData['jenis_cuti_id'];
        $userId = $cuti->user_id; // User ID tetap dari data cuti yg diedit

        // --- HITUNG ULANG HARI KERJA ---
        $lamaCuti = $this->calculateWorkdays($startDate, $endDate);
        if ($lamaCuti === false) {
            Alert::error('Gagal', 'Gagal menghitung ulang durasi hari kerja.');
            return back()->withInput();
        }
        // --- AKHIR HITUNG ULANG HARI KERJA ---

        // --- VALIDASI ULANG KUOTA & CUTI SAKIT ---
        $jenisCuti = JenisCuti::find($jenisCutiId);
        $isCutiSakit = (strtolower($jenisCuti->nama_cuti) === 'cuti sakit');
        if (!$isCutiSakit) {
            $cutiQuota = CutiQuota::where('user_id', $userId)->where('jenis_cuti_id', $jenisCutiId)->first();
            if ($lamaCuti > 0 && (!$cutiQuota || $cutiQuota->durasi_cuti < $lamaCuti)) {
                Alert::error('Kuota Tidak Cukup', 'Sisa kuota cuti (' . ($cutiQuota->durasi_cuti ?? 0) . ' hari) tidak mencukupi.');
                return back()->withInput();
            }
        }
        $hasExistingSurat = !empty($cuti->surat_sakit);
        if ($isCutiSakit && $lamaCuti >= 2 && !$request->hasFile('surat_sakit') && !$hasExistingSurat) {
            return back()->withInput()->withErrors(['surat_sakit' => 'Surat sakit wajib diunggah.']);
        }
        // --- AKHIR VALIDASI ULANG ---

        // --- VALIDASI ULANG OVERLAP ---
        if ($this->checkOverlap($userId, $startDate, $endDate, $cuti->id)) { // Kirim ID cuti yg diedit
            Alert::error('Tanggal Bertabrakan', 'Tanggal cuti yang Anda ajukan bertabrakan.');
            return back()->withInput();
        }
        // --- AKHIR VALIDASI OVERLAP ---

        // --- PROSES UPDATE DATA ---
        DB::beginTransaction();
        try {
            $updateData = $validatedData; // Mulai dengan data tervalidasi
            $updateData['lama_cuti'] = $lamaCuti; // Set lama cuti baru
            $updateData['status'] = 'pending'; // Reset status
            $updateData['notes'] = null; // Reset notes
            $updateData['approved_by_asisten_id'] = null;
            $updateData['approved_at_asisten'] = null;
            $updateData['approved_by_manager_id'] = null;
            $updateData['approved_at_manager'] = null;
            $updateData['rejected_by_id'] = null;
            $updateData['rejected_at'] = null;

            // Penanganan File Surat Sakit
            if ($request->hasFile('surat_sakit')) {
                if ($cuti->surat_sakit) {
                    Storage::disk('public')->delete($cuti->surat_sakit);
                }
                $updateData['surat_sakit'] = $request->file('surat_sakit')->store('surat_sakit', 'public');
            } else {
                // Jika tidak ada file baru, pastikan tidak menghapus path lama
                unset($updateData['surat_sakit']); // Hapus dari array update jika tidak ada file baru
            }

            $cuti->update($updateData); // Lakukan update

            DB::commit();
            Alert::success('Berhasil', 'Pengajuan cuti berhasil diperbarui...');
            return redirect()->route('cuti.index');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error updating Cuti ID {$cuti->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal menyimpan perubahan pengajuan cuti.');
            return back()->withInput();
        }
        // --- AKHIR PROSES UPDATE ---
    }

    /**
     * Cancel the specified resource from storage.
     */
    public function cancel(Cuti $cuti)
    {
        // Gunakan policy untuk cek hak akses cancel
        $this->authorize('cancel', $cuti);

        DB::beginTransaction();
        try {
            $wasApproved = ($cuti->status === 'approved');
            $cuti->status = 'cancelled';
            $cuti->save();

            // Kembalikan kuota jika sebelumnya approved
            if ($wasApproved && $cuti->lama_cuti > 0) {
                $jenisCuti = $cuti->jenisCuti;
                if ($jenisCuti && strtolower($jenisCuti->nama_cuti) !== 'cuti sakit') {
                    $quota = CutiQuota::where('user_id', $cuti->user_id)
                        ->where('jenis_cuti_id', $cuti->jenis_cuti_id)
                        ->first();
                    if ($quota) {
                        $quota->increment('durasi_cuti', $cuti->lama_cuti);
                    } else {
                        Log::warning("CutiQuota not found during cancellation restore for Cuti ID {$cuti->id}.");
                    }
                }
            }
            DB::commit();
            Alert::success('Berhasil', 'Pengajuan cuti telah berhasil dibatalkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error cancelling cuti ID {$cuti->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal membatalkan pengajuan cuti.');
        }
        return redirect()->route('cuti.index');
    }

    /**
     * Display a listing of the resource for Asisten approval.
     * (Otorisasi akses halaman dihandle oleh middleware)
     */
    public function listForAsisten()
    {
        $user = Auth::user(); // Ambil user yg login (asisten)
        $perPage = 15;
        $query = Cuti::where('status', 'pending')->whereNull('approved_by_asisten_id')
            ->with(['user:id,name,jabatan', 'jenisCuti:id,nama_cuti']);

        if ($user->jabatan === 'asisten manager analis') {
            $query->whereHas('user', fn($q) => $q->whereIn('jabatan', ['analis', 'admin']));
        } elseif ($user->jabatan === 'asisten manager preparator') {
            $query->whereHas('user', fn($q) => $q->whereIn('jabatan', ['preparator', 'mekanik', 'admin']));
        } else {
            $query->whereRaw('1 = 0'); // Tidak ada yg ditampilkan jika bukan asisten yg tepat
        }
        $pendingCuti = $query->orderBy('created_at', 'asc')->paginate($perPage);

        // Ambil Kuota Relevan
        $relevantQuotas = $this->getRelevantQuotas($pendingCuti);

        return view('cuti.approval.asisten_list', compact('pendingCuti', 'relevantQuotas'));
    }

    /**
     * Approve Level 1 (Asisten Manager).
     */
    public function approveAsisten(Cuti $cuti)
    {
        // Gunakan policy untuk cek hak akses approveAsisten
        $this->authorize('approveAsisten', $cuti);

        try {
            $cuti->approved_by_asisten_id = Auth::id();
            $cuti->approved_at_asisten = now();
            $cuti->status = 'pending_manager_approval';
            $cuti->save();
            Alert::success('Berhasil', 'Pengajuan cuti disetujui');
        } catch (\Exception $e) {
            Log::error("Error approving L1 cuti ID {$cuti->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal memproses persetujuan L1.');
        }
        return redirect()->route('cuti.approval.asisten.list');
    }

    /**
     * Display a listing of the resource for Manager approval.
     * (Otorisasi akses halaman dihandle oleh middleware)
     */
    public function listForManager()
    {
        // Tidak perlu cek role/jabatan manager lagi di sini
        $perPage = 15;
        $pendingCutiManager = Cuti::where('status', 'pending_manager_approval')
            ->whereNull('approved_by_manager_id')->whereNull('rejected_by_id')
            ->with(['user:id,name,jabatan', 'jenisCuti:id,nama_cuti', 'approverAsisten:id,name'])
            ->orderBy('approved_at_asisten', 'asc')->paginate($perPage);

        // Ambil Kuota Relevan
        $relevantQuotas = $this->getRelevantQuotas($pendingCutiManager);

        return view('cuti.approval.manager_list', compact('pendingCutiManager', 'relevantQuotas'));
    }

    /**
     * Approve Level 2 (Manager Final).
     */
    public function approveManager(Cuti $cuti)
    {
        // Gunakan policy untuk cek hak akses approveManager
        $this->authorize('approveManager', $cuti);
        $approver = Auth::user();

        DB::beginTransaction();
        try {
            $jenisCuti = $cuti->jenisCuti;
            $lamaCutiHariKerja = $cuti->lama_cuti;

            // Pengurangan Kuota
            if ($jenisCuti && strtolower($jenisCuti->nama_cuti) !== 'cuti sakit' && $lamaCutiHariKerja > 0) {
                $quota = CutiQuota::where('user_id', $cuti->user_id)
                    ->where('jenis_cuti_id', $cuti->jenis_cuti_id)
                    ->lockForUpdate()->first();
                if (!$quota || $quota->durasi_cuti < $lamaCutiHariKerja) {
                    DB::rollBack();
                    Alert::error('Gagal', 'Kuota cuti pengguna tidak mencukupi.');
                    return redirect()->route('cuti.approval.manager.list');
                }
                $quota->decrement('durasi_cuti', $lamaCutiHariKerja);
            }

            // Update Status Cuti
            $cuti->approved_by_manager_id = Auth::id();
            $cuti->approved_at_manager = now();
            $cuti->status = 'approved';
            $cuti->rejected_by_id = null; // Reset rejection fields
            $cuti->rejected_at = null;
            $cuti->notes = null;
            $cuti->save();

            DB::commit();

            // --- KIRIM EMAIL NOTIFIKASI APPROVAL ---
            try {
                // Pastikan relasi user sudah di-load atau load di sini jika perlu
                $pengaju = $cuti->user()->first(); // Ambil objek user pengaju
                if ($pengaju && $pengaju->email) {
                    Mail::to($pengaju->email)->queue(new LeaveStatusNotificationMail($cuti, 'approved', $approver));
                    Log::info("Leave approval notification sent to {$pengaju->email} for Cuti ID {$cuti->id}");
                } else {
                    Log::warning("Cannot send leave approval notification: User or email not found for Cuti ID {$cuti->id}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to send leave approval email for Cuti ID {$cuti->id}: " . $e->getMessage());
                // Jangan gagalkan proses utama hanya karena email gagal
            }
            // --- AKHIR KIRIM EMAIL ---
            Alert::success('Berhasil', 'Pengajuan cuti untuk ' . $cuti->user->name . ' telah disetujui.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error approving L2 cuti ID {$cuti->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal memproses persetujuan L2.');
        }
        return redirect()->route('cuti.approval.manager.list');
    }

    /**
     * Reject the specified resource from storage.
     */
    public function reject(Request $request, Cuti $cuti)
    {
        // Gunakan policy untuk cek hak akses reject
        $this->authorize('reject', $cuti);
        $rejecter = Auth::user();

        $validated = $request->validate(['notes' => 'required|string|max:500']);

        try {
            $cuti->rejected_by_id = Auth::id();
            $cuti->rejected_at = now();
            $cuti->status = 'rejected';
            $cuti->notes = $validated['notes'];
            // Reset approval L1 jika direject oleh Manager
            if (Auth::user()->jabatan === 'manager') {
                $cuti->approved_by_asisten_id = null;
                $cuti->approved_at_asisten = null;
            }
            $cuti->save();
            // --- KIRIM EMAIL NOTIFIKASI APPROVAL ---
            try {
                // Pastikan relasi user sudah di-load atau load di sini jika perlu
                $pengaju = $cuti->user()->first(); // Ambil objek user pengaju
                if ($pengaju && $pengaju->email) {
                    Mail::to($pengaju->email)->queue(new LeaveStatusNotificationMail($cuti, 'rejected', $rejecter));
                    Log::info("Leave rejection notification sent to {$pengaju->email} for Cuti ID {$cuti->id}");
                } else {
                    Log::warning("Cannot send leave rejection notification: User or email not found for Cuti ID {$cuti->id}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to send leave rejection email for Cuti ID {$cuti->id}: " . $e->getMessage());
                // Jangan gagalkan proses utama hanya karena email gagal
            }
            // --- AKHIR KIRIM EMAIL ---
            Alert::success('Berhasil', 'Pengajuan cuti telah ditolak.');
        } catch (\Exception $e) {
            Log::error("Error rejecting cuti ID {$cuti->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal memproses penolakan.');
        }

        // Redirect ke list yang sesuai
        if (Auth::user()->jabatan === 'manager') return redirect()->route('cuti.approval.manager.list');
        else return redirect()->route('cuti.approval.asisten.list');
    }

    /**
     * Generate and download PDF for the specified Cuti.
     */
    public function downloadPdf(Cuti $cuti)
    {
        // Gunakan policy untuk cek hak akses downloadPdf
        $this->authorize('downloadPdf', $cuti);

        $cuti->load([
            'user' => function ($query) {
                $query->select('id', 'name', 'jabatan', 'tanggal_mulai_bekerja', 'signature_path', 'vendor_id')->with('vendor:id,name,logo_path');
            },
            'jenisCuti:id,nama_cuti',
            'approverAsisten:id,name,jabatan,signature_path',
            'approverManager:id,name,jabatan,signature_path',
            'rejecter:id,name'
        ]);

        $filename = 'cuti_' . str_replace(' ', '_', $cuti->user->name) . '_' . $cuti->mulai_cuti->format('Ymd') . '.pdf';
        $pdf = Pdf::loadView('cuti.pdf_template', compact('cuti'));
        return $pdf->download($filename);
    }

    /**
     * Get remaining leave quota via AJAX.
     */
    public function getQuota(Request $request)
    {
        $jenisCutiId = $request->validate(['jenis_cuti_id' => 'required|exists:jenis_cuti,id'])['jenis_cuti_id'];
        $cutiQuota = CutiQuota::where('user_id', Auth::id())
            ->where('jenis_cuti_id', $jenisCutiId)->first();
        return response()->json(['durasi_cuti' => $cutiQuota ? $cutiQuota->durasi_cuti : 0]);
    }

    // --- Helper Methods ---

    /**
     * Calculate effective workdays between two dates, excluding weekends and holidays.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return int|false Number of workdays or false on error.
     */
    private function calculateWorkdays(Carbon $startDate, Carbon $endDate): int|false
    {
        $workDays = 0;
        try {
            $holidayDates = Holiday::whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()])
                ->pluck('tanggal')
                ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
                ->toArray();
            $period = CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $date) {
                if (!$date->isWeekend() && !in_array($date->format('Y-m-d'), $holidayDates)) {
                    $workDays++;
                }
            }
            return $workDays;
        } catch (\Exception $e) {
            Log::error("Helper calculateWorkdays Error: " . $e->getMessage());
            return false; // Return false jika gagal
        }
    }

    /**
     * Check for overlapping leave requests.
     *
     * @param int $userId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int|null $excludeCutiId ID of the current cuti being edited (optional)
     * @return bool True if overlap exists, false otherwise.
     */
    private function checkOverlap(int $userId, Carbon $startDate, Carbon $endDate, ?int $excludeCutiId = null): bool
    {
        $query = Cuti::where('user_id', $userId)
            ->whereIn('status', ['pending', 'pending_manager_approval', 'approved']);

        // Kecualikan ID cuti saat ini jika sedang diedit
        if ($excludeCutiId) {
            $query->where('id', '!=', $excludeCutiId);
        }

        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->where(function ($sub) use ($startDate, $endDate) {
                $sub->where('mulai_cuti', '>=', $startDate)->where('mulai_cuti', '<=', $endDate);
            })
                ->orWhere(function ($sub) use ($startDate, $endDate) {
                    $sub->where('selesai_cuti', '>=', $startDate)->where('selesai_cuti', '<=', $endDate);
                })
                ->orWhere(function ($sub) use ($startDate, $endDate) {
                    $sub->where('mulai_cuti', '<=', $startDate)->where('selesai_cuti', '>=', $endDate);
                });
        })->exists();
    }

    /**
     * Helper to get relevant quotas for a collection of leave requests.
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginatedCuti
     * @return \Illuminate\Support\Collection
     */
    private function getRelevantQuotas($paginatedCuti): \Illuminate\Support\Collection
    {
        $relevantQuotas = collect();
        if ($paginatedCuti->isNotEmpty()) {
            $quotaQuery = CutiQuota::query();
            foreach ($paginatedCuti->items() as $cuti) {
                $quotaQuery->orWhere(function ($q) use ($cuti) {
                    $q->where('user_id', $cuti->user_id)
                        ->where('jenis_cuti_id', $cuti->jenis_cuti_id);
                });
            }
            $relevantQuotas = $quotaQuery->get()->keyBy(fn($item) => $item->user_id . '_' . $item->jenis_cuti_id);
        }
        return $relevantQuotas;
    }
} // End Class CutiController

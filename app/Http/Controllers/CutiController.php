<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cuti;
use App\Models\CutiQuota;
use App\Models\JenisCuti;
use App\Models\Holiday; // <-- Import model Holiday
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // <-- Import Log
use Carbon\Carbon;
use Carbon\CarbonPeriod; // <-- Import CarbonPeriod
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\DB;

class CutiController extends Controller
{
    /**
     * Menampilkan daftar pengajuan cuti.
     */
    public function index()
    {
        $user = Auth::user();
        $perPage = 15; // Jumlah item per halaman

        if ($user->role === 'admin' || $user->role === 'manajemen') {
            // Admin & Manajemen: lihat semua, paginated
            $cuti = Cuti::with('user', 'jenisCuti')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        } else {
            // Personil: lihat milik sendiri, paginated
            $cuti = Cuti::where('user_id', $user->id)
                ->with('jenisCuti')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        }

        // Ambil kuota cuti user yang login (untuk info di view index jika perlu)
        $cutiQuota = CutiQuota::where('user_id', $user->id)
            ->get()
            ->keyBy('jenis_cuti_id');

        return view('cuti.index', compact('cuti', 'cutiQuota'));
    }

    /**
     * Menampilkan form pengajuan cuti.
     */
    public function create()
    {
        // Ambil semua jenis cuti yang aktif (jika ada flag aktif)
        $jenisCuti = JenisCuti::orderBy('nama_cuti')->get();
        // Ambil sisa kuota user saat ini untuk ditampilkan via JS nanti
        $currentKuota = CutiQuota::where('user_id', Auth::id())->pluck('durasi_cuti', 'jenis_cuti_id');

        return view('cuti.create', compact('jenisCuti', 'currentKuota'));
    }

    /**
     * Menyimpan pengajuan cuti baru.
     */
    public function store(Request $request)
    {
        // Validasi dasar (biarkan Laravel handle redirect otomatis jika gagal)
        $validatedData = $request->validate([
            'jenis_cuti_id' => 'required|exists:jenis_cuti,id',
            'mulai_cuti' => 'required|date',
            'selesai_cuti' => 'required|date|after_or_equal:mulai_cuti',
            'keperluan' => 'required|string|max:1000',
            'alamat_selama_cuti' => 'required|string|max:255',
            'surat_sakit' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ], [
            // Anda bisa tambahkan custom message di sini jika perlu
            'surat_sakit.required' => 'Surat sakit wajib diunggah untuk cuti sakit 2 hari kerja atau lebih.' // Custom message jika validasi required gagal
        ]);

        $startDate = Carbon::parse($validatedData['mulai_cuti']);
        $endDate = Carbon::parse($validatedData['selesai_cuti']);
        $jenisCutiId = $validatedData['jenis_cuti_id'];
        $userId = Auth::id();

        // --- PERHITUNGAN HARI KERJA EFEKTIF ---
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
        } catch (\Exception $e) {
            Log::error("Holiday Calculation Error: " . $e->getMessage());
            // Gunakan SweetAlert untuk error ini
            alert()->error('Gagal', 'Terjadi kesalahan saat memproses data hari libur. Silakan coba lagi.');
            return back()->withInput(); // Redirect kembali dengan input lama
        }
        $lamaCuti = $workDays;
        // --- AKHIR PERHITUNGAN HARI KERJA ---


        // --- VALIDASI KUOTA & ATURAN CUTI SAKIT ---
        $jenisCuti = JenisCuti::find($jenisCutiId);
        if (!$jenisCuti) {
            // Error jika jenis cuti tidak ditemukan (seharusnya tidak terjadi)
            alert()->error('Gagal', 'Jenis cuti yang dipilih tidak valid.');
            return back()->withInput();
        }
        $isCutiSakit = (strtolower($jenisCuti->nama_cuti) === 'cuti sakit');

        // 1. Validasi Kuota (Hanya jika BUKAN Cuti Sakit)
        if (!$isCutiSakit) {
            $cutiQuota = CutiQuota::where('user_id', $userId)
                ->where('jenis_cuti_id', $jenisCutiId)
                ->first();
            if ($lamaCuti > 0 && (!$cutiQuota || $cutiQuota->durasi_cuti < $lamaCuti)) {
                // Gunakan SweetAlert untuk error kuota
                $errorMessage = 'Sisa kuota cuti (' . ($cutiQuota->durasi_cuti ?? 0) . ' hari) tidak mencukupi untuk permintaan ' . $lamaCuti . ' hari kerja.';
                alert()->error('Kuota Tidak Cukup', $errorMessage);
                return back()->withInput();
            }
        }

        // 2. Validasi Surat Sakit (Hanya jika Cuti Sakit >= 2 hari kerja)
        //    Validasi ini lebih baik ditangani oleh $request->validate() di atas
        //    jika kita bisa membuatnya kondisional, atau kita cek manual di sini.
        //    Kita akan tambahkan validasi manual di sini jika validasi Laravel sulit dibuat kondisional.
        if ($isCutiSakit && $lamaCuti >= 2 && !$request->hasFile('surat_sakit')) {
            // Error spesifik untuk field 'surat_sakit' biasanya lebih baik
            // ditangani oleh Laravel validation agar pesan muncul di bawah field.
            // Jika ingin pakai SweetAlert juga:
            // alert()->warning('Perhatian', 'Surat sakit wajib diunggah untuk cuti sakit ' . $lamaCuti . ' hari kerja atau lebih.');
            // return back()->withInput()->withErrors(['surat_sakit' => 'Surat sakit wajib diunggah.']); // Tetap kirim error spesifik field
            // Kita biarkan validasi Laravel yang menangani ini via aturan di atas untuk UX yang lebih baik.
        }
        // --- AKHIR VALIDASI KUOTA & ATURAN CUTI SAKIT ---


        // --- VALIDASI OVERLAP ---
        $overlappingCuti = Cuti::where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->where('mulai_cuti', '>=', $startDate)->where('mulai_cuti', '<=', $endDate);
                })->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->where('selesai_cuti', '>=', $startDate)->where('selesai_cuti', '<=', $endDate);
                })->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->where('mulai_cuti', '<=', $startDate)->where('selesai_cuti', '>=', $endDate);
                });
            })
            ->exists();

        if ($overlappingCuti) {
            // Gunakan SweetAlert untuk error overlap
            $errorMessage = 'Tanggal cuti yang Anda ajukan (' . $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y') . ') bertabrakan dengan pengajuan cuti lain yang sudah ada (pending/approved).';
            alert()->error('Tanggal Bertabrakan', $errorMessage);
            return back()->withInput();
        }
        // --- AKHIR VALIDASI OVERLAP ---


        // --- SIMPAN PENGAJUAN CUTI ---
        $cutiData = [
            'user_id'           => $userId,
            'jenis_cuti_id'     => $jenisCutiId,
            'mulai_cuti'        => $validatedData['mulai_cuti'],
            'selesai_cuti'      => $validatedData['selesai_cuti'],
            'lama_cuti'         => $lamaCuti,
            'keperluan'         => $validatedData['keperluan'],
            'alamat_selama_cuti' => $validatedData['alamat_selama_cuti'],
            'status'            => 'pending',
        ];

        if ($request->hasFile('surat_sakit')) {
            try {
                $cutiData['surat_sakit'] = $request->file('surat_sakit')->store('surat_sakit', 'public');
            } catch (\Exception $e) {
                Log::error("File Upload Error: " . $e->getMessage());
                alert()->error('Gagal', 'Terjadi kesalahan saat mengunggah file surat sakit.');
                return back()->withInput();
            }
        }

        Cuti::create($cutiData);

        // Gunakan SweetAlert untuk pesan sukses
        alert()->success('Berhasil', 'Pengajuan cuti (' . $lamaCuti . ' hari kerja) telah berhasil diajukan.');

        return redirect()->route('cuti.index'); // Redirect TANPA ->with() lagi
    }

    public function cancel(Cuti $cuti) // Route Model Binding
    {
        $user = Auth::user();

        // --- Otorisasi & Validasi Kondisi ---
        // 1. Hanya pemilik pengajuan
        if ($user->id !== $cuti->user_id) {
            Alert::error('Akses Ditolak', 'Anda tidak berhak membatalkan pengajuan ini.');
            return redirect()->route('cuti.index');
        }

        // 2. Hanya status 'pending' atau 'approved' yang bisa dibatalkan
        if (!in_array($cuti->status, ['pending', 'approved'])) {
            Alert::error('Gagal', 'Pengajuan cuti dengan status ini tidak dapat dibatalkan.');
            return redirect()->route('cuti.index');
        }

        // 3. Hanya bisa dibatalkan SEBELUM tanggal mulai cuti
        if (Carbon::today()->gte($cuti->mulai_cuti)) { // gte = Greater Than or Equal To
            Alert::error('Gagal', 'Anda tidak dapat membatalkan pengajuan cuti yang sudah dimulai atau sudah lewat tanggal mulainya.');
            return redirect()->route('cuti.index');
        }
        // --- Akhir Otorisasi & Validasi Kondisi ---


        // Gunakan Transaksi Database untuk keamanan
        DB::beginTransaction();
        try {

            // Flag untuk menandai status sebelum diubah
            $wasApproved = ($cuti->status === 'approved');

            // Ubah status utama
            $cuti->status = 'cancelled';
            // Opsional: isi kolom notes, cancelled_by, cancelled_at jika ada
            // $cuti->notes = $request->input('cancellation_reason'); // Jika ada input alasan
            // $cuti->cancelled_by_id = $user->id;
            // $cuti->cancelled_at = now();
            $cuti->save(); // Simpan perubahan status cuti

            // Jika cuti yang dibatalkan statusnya SUDAH 'approved', KEMBALIKAN KUOTA
            if ($wasApproved && $cuti->lama_cuti > 0) {
                // Cari kuota yang relevan (kecuali cuti sakit)
                $jenisCuti = $cuti->jenisCuti; // Ambil dari relasi yg mungkin sudah di-load
                if ($jenisCuti && strtolower($jenisCuti->nama_cuti) !== 'cuti sakit') {
                    $quota = CutiQuota::where('user_id', $cuti->user_id)
                        ->where('jenis_cuti_id', $cuti->jenis_cuti_id)
                        ->first();

                    if ($quota) {
                        // Tambahkan kembali kuota sejumlah lama_cuti (hari kerja)
                        // Gunakan increment untuk operasi atomik
                        $quota->increment('durasi_cuti', $cuti->lama_cuti);
                        // $quota->durasi_cuti = $quota->durasi_cuti + $cuti->lama_cuti;
                        // $quota->save(); // Tidak perlu save jika pakai increment
                    } else {
                        // Kasus aneh jika kuota tidak ditemukan saat restore, log error
                        Log::warning("CutiQuota not found for user {$cuti->user_id}, jenis_cuti {$cuti->jenis_cuti_id} during cancellation restore for Cuti ID {$cuti->id}.");
                        // Mungkin perlu membuat kuota baru dengan nilai lama_cuti? Tergantung aturan bisnis.
                        // CutiQuota::create(['user_id' => $cuti->user_id, 'jenis_cuti_id' => $cuti->jenis_cuti_id, 'durasi_cuti' => $cuti->lama_cuti]);
                    }
                }
            }

            DB::commit(); // Konfirmasi semua perubahan jika sukses

            Alert::success('Berhasil', 'Pengajuan cuti telah berhasil dibatalkan.');
        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan semua perubahan jika ada error
            Log::error("Error cancelling (updating status) cuti ID {$cuti->id} by user {$user->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan saat membatalkan pengajuan cuti.');
        }

        return redirect()->route('cuti.index');
    }

    public function listForAsisten()
    {
        $user = Auth::user();
        $perPage = 15;

        if ($user->role !== 'manajemen') {
            Alert::error('Akses Ditolak', 'Anda tidak memiliki hak akses ke halaman ini.');
            return redirect()->route('dashboard.index');
        }

        $query = Cuti::where('status', 'pending')
            ->whereNull('approved_by_asisten_id')
            ->with(['user:id,name,jabatan', 'jenisCuti:id,nama_cuti']);

        // Filter berdasarkan jabatan Asisten Manager
        if ($user->jabatan === 'asisten manager analis') {
            $query->whereHas('user', function ($q) {
                $q->whereIn('jabatan', ['analis', 'admin']); // Pakai whereIn lebih bersih
            });
        } elseif ($user->jabatan === 'asisten manager preparator') {
            $query->whereHas('user', function ($q) {
                $q->whereIn('jabatan', ['preparator', 'mekanik', 'admin']); // Pakai whereIn
            });
        } else {
            Alert::info('Info', 'Tidak ada pengajuan cuti yang menunggu persetujuan Anda sebagai Asisten Manager.');
            $query->whereRaw('1 = 0');
        }

        $pendingCuti = $query->orderBy('created_at', 'asc')->paginate($perPage);

        // --- AMBIL DATA KUOTA YANG RELEVAN ---
        $relevantQuotas = collect(); // Default collection kosong
        if ($pendingCuti->isNotEmpty()) {
            $quotaQuery = CutiQuota::query();
            // Buat query OR WHERE untuk setiap kombinasi user & jenis cuti yang tampil
            foreach ($pendingCuti->items() as $cuti) {
                $quotaQuery->orWhere(function ($q) use ($cuti) {
                    $q->where('user_id', $cuti->user_id)
                        ->where('jenis_cuti_id', $cuti->jenis_cuti_id);
                });
            }
            // Jalankan query dan buat key unik 'user_id'_'jenis_cuti_id'
            $relevantQuotas = $quotaQuery->get()->keyBy(function ($item) {
                return $item->user_id . '_' . $item->jenis_cuti_id;
            });
        }
        // --- AKHIR PENGAMBILAN DATA KUOTA ---


        // Kirim $pendingCuti (paginator) dan $relevantQuotas (keyed collection) ke view
        return view('cuti.approval.asisten_list', compact('pendingCuti', 'relevantQuotas'));
    }

    /**
     * Menyetujui pengajuan cuti (Level 1 - Asisten Manager).
     */
    public function approveAsisten(Cuti $cuti) // Gunakan Route Model Binding
    {
        $approver = Auth::user();

        // --- Otorisasi ---
        // 1. Pastikan approver adalah manajemen
        if ($approver->role !== 'manajemen') {
            Alert::error('Akses Ditolak', 'Anda tidak berhak melakukan aksi ini.');
            return redirect()->back();
        }

        // 2. Pastikan cuti masih 'pending' dan belum diapprove L1
        if ($cuti->status !== 'pending' || $cuti->approved_by_asisten_id !== null) {
            Alert::warning('Info', 'Pengajuan cuti ini sudah diproses atau tidak lagi menunggu persetujuan Anda.');
            return redirect()->route('cuti.approval.asisten.list'); // Kembali ke list
        }

        // 3. Pastikan approver adalah Asisten Manager yang TEPAT
        $pengajuJabatan = $cuti->user->jabatan;
        $canApprove = false;
        if ($approver->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) {
            $canApprove = true;
        } elseif ($approver->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin'])) {
            $canApprove = true;
        }

        if (!$canApprove) {
            Alert::error('Akses Ditolak', 'Anda tidak berhak menyetujui pengajuan cuti untuk jabatan ini.');
            // Redirect ke list approval atau dashboard
            return redirect()->route('cuti.approval.asisten.list');
        }
        // --- Akhir Otorisasi ---


        // --- Update Status Cuti ---
        try {
            $cuti->approved_by_asisten_id = $approver->id;
            $cuti->approved_at_asisten = now();
            $cuti->status = 'pending_manager_approval'; // Ubah status menunggu L2
            $cuti->save();

            // TODO: Notifikasi ke Manager? (Opsional)

            Alert::success('Berhasil', 'Pengajuan cuti berhasil disetujui (Level 1) dan menunggu persetujuan Manager.');
        } catch (\Exception $e) {
            Log::error("Error approving L1 cuti ID {$cuti->id} by user {$approver->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan saat memproses persetujuan.');
        }

        return redirect()->route('cuti.approval.asisten.list');
    }

    public function listForManager()
    {
        $user = Auth::user();
        $perPage = 15;

        // Otorisasi: Hanya user dengan jabatan 'manager'
        if ($user->role !== 'manajemen' || $user->jabatan !== 'manager') {
            Alert::error('Akses Ditolak', 'Hanya Manager yang dapat mengakses halaman ini.');
            return redirect()->route('dashboard.index');
        }

        // Ambil cuti yang statusnya 'pending_manager_approval'
        $pendingCutiManager = Cuti::where('status', 'pending_manager_approval')
            ->whereNull('approved_by_manager_id')
            ->whereNull('rejected_by_id')
            ->with([
                'user:id,name,jabatan',
                'jenisCuti:id,nama_cuti',
                'approverAsisten:id,name' // Eager load approver L1
            ])
            ->orderBy('approved_at_asisten', 'asc')
            ->paginate($perPage);

        // --- AMBIL DATA KUOTA YANG RELEVAN --- (Sama seperti di listForAsisten)
        $relevantQuotas = collect();
        if ($pendingCutiManager->isNotEmpty()) {
            $quotaQuery = CutiQuota::query();
            foreach ($pendingCutiManager->items() as $cuti) {
                $quotaQuery->orWhere(function ($q) use ($cuti) {
                    $q->where('user_id', $cuti->user_id)
                        ->where('jenis_cuti_id', $cuti->jenis_cuti_id);
                });
            }
            $relevantQuotas = $quotaQuery->get()->keyBy(fn($item) => $item->user_id . '_' . $item->jenis_cuti_id);
        }
        // --- AKHIR PENGAMBILAN DATA KUOTA ---

        // Kirim data cuti dan data kuota ke view
        return view('cuti.approval.manager_list', compact('pendingCutiManager', 'relevantQuotas'));
    }

    /**
     * Menyetujui pengajuan cuti (Level 2 - Manager Final).
     * Termasuk pengurangan kuota cuti.
     */
    public function approveManager(Cuti $cuti) // Route Model Binding
    {
        $approver = Auth::user();

        // --- Otorisasi ---
        // 1. Pastikan approver adalah 'manager'
        if ($approver->role !== 'manajemen' || $approver->jabatan !== 'manager') {
            Alert::error('Akses Ditolak', 'Hanya Manager yang dapat melakukan aksi ini.');
            return redirect()->back(); // Kembali ke halaman sebelumnya
        }

        // 2. Pastikan status cuti adalah 'pending_manager_approval'
        if ($cuti->status !== 'pending_manager_approval') {
            Alert::warning('Info', 'Pengajuan cuti ini tidak dalam status menunggu persetujuan Manager.');
            return redirect()->route('cuti.approval.manager.list'); // Kembali ke list manager
        }
        // --- Akhir Otorisasi ---

        // Gunakan Transaksi Database
        DB::beginTransaction();
        try {
            $jenisCuti = $cuti->jenisCuti; // Ambil jenis cuti terkait
            $lamaCutiHariKerja = $cuti->lama_cuti; // Ambil durasi hari kerja

            // --- Pengurangan Kuota (jika perlu) ---
            // Cek apakah jenis cuti ini memotong kuota (bukan cuti sakit)
            if ($jenisCuti && strtolower($jenisCuti->nama_cuti) !== 'cuti sakit' && $lamaCutiHariKerja > 0) {
                $quota = CutiQuota::where('user_id', $cuti->user_id)
                    ->where('jenis_cuti_id', $cuti->jenis_cuti_id)
                    ->lockForUpdate() // Kunci baris untuk mencegah race condition saat update
                    ->first();

                if (!$quota || $quota->durasi_cuti < $lamaCutiHariKerja) {
                    // Kuota tidak ditemukan ATAU tiba-tiba tidak cukup (misal ada proses lain)
                    DB::rollBack(); // Batalkan transaksi
                    Alert::error('Gagal', 'Kuota cuti pengguna tidak mencukupi (' . ($quota->durasi_cuti ?? 0) . ' hari) untuk durasi ' . $lamaCutiHariKerja . ' hari kerja.');
                    return redirect()->route('cuti.approval.manager.list');
                }

                // Kurangi kuota menggunakan decrement (lebih aman dari race condition)
                $quota->decrement('durasi_cuti', $lamaCutiHariKerja);
                // $quota->durasi_cuti -= $lamaCutiHariKerja; // Alternatif jika tdk pakai decrement
                // $quota->save(); // Tidak perlu jika pakai decrement
            }
            // --- Akhir Pengurangan Kuota ---


            // --- Update Status Cuti ---
            $cuti->approved_by_manager_id = $approver->id;
            $cuti->approved_at_manager = now();
            $cuti->status = 'approved'; // Status Final: Disetujui
            // Kosongkan field reject jika sebelumnya mungkin pernah diisi (jarang terjadi)
            $cuti->rejected_by_id = null;
            $cuti->rejected_at = null;
            $cuti->notes = null; // Hapus notes jika ada sisa dari proses reject sebelumnya (jarang)
            $cuti->save();
            // --- Akhir Update Status Cuti ---

            DB::commit(); // Simpan semua perubahan ke database jika tidak ada error

            // TODO: Notifikasi ke Pengaju & Asisten Manager? (Opsional)

            Alert::success('Berhasil', 'Pengajuan cuti untuk ' . $cuti->user->name . ' telah disetujui.');
        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan semua jika ada error
            Log::error("Error approving L2 cuti ID {$cuti->id} by manager {$approver->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan sistem saat memproses persetujuan.');
        }

        return redirect()->route('cuti.approval.manager.list'); // Kembali ke list manager
    }

    /**
     * Menolak pengajuan cuti (Bisa oleh Asisten atau Manager).
     * Pastikan otorisasi sudah benar.
     */
    public function reject(Request $request, Cuti $cuti)
    {
        $rejecter = Auth::user();

        // Validasi alasan penolakan
        $validated = $request->validate([
            'notes' => 'required|string|max:500'
        ]);

        // --- Otorisasi ---
        if ($rejecter->role !== 'manajemen') {
            Alert::error('Akses Ditolak');
            return redirect()->back();
        }

        $canReject = false;
        // Cek jika rejecter adalah Asisten yang tepat DAN status masih pending L1
        if ($cuti->status === 'pending') {
            $pengajuJabatan = $cuti->user->jabatan;
            if (($rejecter->jabatan === 'asisten manager analis' && in_array($pengajuJabatan, ['analis', 'admin'])) ||
                ($rejecter->jabatan === 'asisten manager preparator' && in_array($pengajuJabatan, ['preparator', 'mekanik', 'admin']))
            ) {
                $canReject = true;
            }
        }
        // Cek jika rejecter adalah Manager DAN status menunggu L2
        elseif ($cuti->status === 'pending_manager_approval') {
            if ($rejecter->jabatan === 'manager') {
                $canReject = true;
            }
        }

        if (!$canReject || !in_array($cuti->status, ['pending', 'pending_manager_approval'])) {
            Alert::error('Akses Ditolak', 'Anda tidak dapat menolak pengajuan ini pada status saat ini atau Anda tidak berwenang.');
            // Redirect lebih baik ke dashboard atau list yg relevan
            if ($rejecter->jabatan === 'manager') return redirect()->route('cuti.approval.manager.list');
            else return redirect()->route('cuti.approval.asisten.list');
        }
        // --- Akhir Otorisasi ---

        try {
            $cuti->rejected_by_id = $rejecter->id;
            $cuti->rejected_at = now();
            $cuti->status = 'rejected';
            $cuti->notes = $validated['notes'];
            // Kosongkan approval L1 jika direject oleh Manager setelah L1 approve
            if ($rejecter->jabatan === 'manager') {
                $cuti->approved_by_asisten_id = null;
                $cuti->approved_at_asisten = null;
            }
            $cuti->save();

            Alert::success('Berhasil', 'Pengajuan cuti telah ditolak.');
        } catch (\Exception $e) {
            Log::error("Error rejecting cuti ID {$cuti->id} by user {$rejecter->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan saat memproses penolakan.');
        }

        // Redirect ke list yang sesuai
        if ($rejecter->jabatan === 'manager') return redirect()->route('cuti.approval.manager.list');
        else return redirect()->route('cuti.approval.asisten.list');
    }






    /**
     * Mengambil sisa kuota cuti via AJAX.
     */
    public function getQuota(Request $request)
    {
        $jenisCutiId = $request->validate(['jenis_cuti_id' => 'required|exists:jenis_cuti,id'])['jenis_cuti_id'];

        $cutiQuota = CutiQuota::where('user_id', Auth::id())
            ->where('jenis_cuti_id', $jenisCutiId)
            ->first();

        return response()->json([
            'durasi_cuti' => $cutiQuota ? $cutiQuota->durasi_cuti : 0,
        ]);
    }
}

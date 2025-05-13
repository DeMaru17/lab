<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cuti;
use App\Models\CutiQuota;
use App\Models\JenisCuti;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Digunakan untuk transaksi database
use Illuminate\Support\Facades\Log; // Untuk logging error dan informasi
use Illuminate\Support\Facades\Storage; // Untuk manajemen file (surat sakit)
use Carbon\Carbon;
use Carbon\CarbonPeriod; // Untuk iterasi dalam rentang tanggal (calculateWorkdays)
use RealRashid\SweetAlert\Facades\Alert; // Untuk notifikasi SweetAlert
use Barryvdh\DomPDF\Facade\Pdf; // Untuk generate PDF
use Illuminate\Auth\Access\AuthorizationException; // Untuk menangkap exception otorisasi jika diperlukan
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Trait untuk menggunakan $this->authorize()
use App\Mail\LeaveStatusNotificationMail; // Mailable untuk notifikasi status cuti
use Illuminate\Support\Facades\Mail; // Facade untuk mengirim email
use Illuminate\Support\Str; // Untuk manipulasi string (generate unique filename)

/**
 * Class CutiController
 *
 * Mengelola semua operasi yang berkaitan dengan pengajuan, persetujuan,
 * pembatalan, dan pelaporan data cuti karyawan. Controller ini juga
 * menangani validasi kuota, pengecekan overlap, dan interaksi dengan
 * model-model terkait seperti JenisCuti, CutiQuota, dan Holiday.
 *
 * @package App\Http\Controllers
 */
class CutiController extends Controller
{
    use AuthorizesRequests; // Mengaktifkan penggunaan method authorize() dari Laravel Policy

    /**
     * Menampilkan daftar pengajuan cuti.
     * - Untuk pengguna dengan peran 'personil', hanya menampilkan daftar cuti miliknya sendiri.
     * - Untuk pengguna dengan peran 'admin' atau 'manajemen', dapat menampilkan semua pengajuan
     * dan mendukung fitur pencarian berdasarkan keperluan, nama pengguna, atau jenis cuti.
     * Data dipaginasi untuk tampilan yang lebih baik.
     *
     * @param  \Illuminate\Http\Request  $request Data request yang mungkin berisi parameter pencarian.
     * @return \Illuminate\View\View Mengembalikan view 'cuti.index' dengan data cuti dan kuota cuti pengguna.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user Pengguna yang sedang login. */
        $user = Auth::user();
        $perPage = 15; // Jumlah item per halaman untuk pagination
        $searchTerm = $request->input('search'); // Kata kunci pencarian dari input

        // Query dasar untuk mengambil data Cuti beserta relasi yang dibutuhkan
        $query = Cuti::with(['user:id,name,jabatan', 'jenisCuti:id,nama_cuti', 'rejecter:id,name']);

        if ($user->role === 'personil') {
            // Jika pengguna adalah personil, filter hanya cuti milik pengguna tersebut
            $query->where('user_id', $user->id);
        } elseif ($searchTerm) {
            // Jika ada kata kunci pencarian (untuk admin/manajemen)
            $query->where(function ($q) use ($searchTerm) {
                $q->where('keperluan', 'like', '%' . $searchTerm . '%') // Cari berdasarkan kolom 'keperluan'
                    ->orWhereHas('user', function ($userQuery) use ($searchTerm) { // Cari berdasarkan nama pengguna
                        $userQuery->where('name', 'like', '%' . $searchTerm . '%');
                    })
                    ->orWhereHas('jenisCuti', function ($jenisCutiQuery) use ($searchTerm) { // Cari berdasarkan nama jenis cuti
                        $jenisCutiQuery->where('nama_cuti', 'like', '%' . $searchTerm . '%');
                    });
            });
        }

        // Mengambil data cuti dengan urutan berdasarkan tanggal update terbaru, lalu dipaginasi
        $cuti = $query->orderBy('updated_at', 'desc')->paginate($perPage);

        // Jika ada pencarian, sertakan parameter pencarian ke link pagination
        if ($searchTerm) {
            $cuti->appends(['search' => $searchTerm]);
        }

        // Mengambil data kuota cuti pengguna yang login untuk ditampilkan di view
        $cutiQuota = CutiQuota::where('user_id', $user->id)->get()->keyBy('jenis_cuti_id');

        return view('cuti.index', compact('cuti', 'cutiQuota'));
    }

    /**
     * Menampilkan form untuk membuat pengajuan cuti baru.
     * Memerlukan otorisasi 'create' dari CutiPolicy.
     *
     * @return \Illuminate\View\View Mengembalikan view 'cuti.create' dengan data jenis cuti dan kuota saat ini.
     */
    public function create()
    {
        // Otorisasi: Memastikan pengguna berhak membuat pengajuan cuti baru.
        // Akan memanggil method 'create' di CutiPolicy.
        $this->authorize('create', Cuti::class);

        // Mengambil semua jenis cuti yang tersedia untuk ditampilkan di dropdown form
        $jenisCuti = JenisCuti::orderBy('nama_cuti')->get();
        // Mengambil kuota cuti saat ini milik pengguna yang login
        $currentKuota = CutiQuota::where('user_id', Auth::id())->pluck('durasi_cuti', 'jenis_cuti_id');

        return view('cuti.create', compact('jenisCuti', 'currentKuota'));
    }

    /**
     * Menyimpan pengajuan cuti baru ke database setelah validasi.
     * Method ini melakukan perhitungan hari kerja efektif, validasi kuota,
     * validasi aturan cuti sakit (lampiran surat sakit), dan pengecekan overlap tanggal.
     * Jika ada file surat sakit, file tersebut akan disimpan.
     *
     * @param  \Illuminate\Http\Request  $request Data dari form pengajuan cuti.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke halaman daftar cuti dengan pesan status.
     */
    public function store(Request $request)
    {
        // Otorisasi: Memastikan pengguna berhak menyimpan pengajuan cuti baru.
        $this->authorize('create', Cuti::class);

        // Validasi data input dari form
        $validatedData = $request->validate([
            'jenis_cuti_id' => 'required|exists:jenis_cuti,id', // jenis_cuti_id harus ada dan valid
            'mulai_cuti' => 'required|date',
            'selesai_cuti' => 'required|date|after_or_equal:mulai_cuti', // Tanggal selesai tidak boleh sebelum tanggal mulai
            'keperluan' => 'required|string|max:1000',
            'alamat_selama_cuti' => 'required|string|max:255',
            'surat_sakit' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048', // File opsional, maks 2MB
        ], [
            // Pesan error kustom untuk validasi
            'surat_sakit.required' => 'Surat sakit wajib diunggah untuk cuti sakit 2 hari kerja atau lebih.'
        ]);

        $startDate = Carbon::parse($validatedData['mulai_cuti']);
        $endDate = Carbon::parse($validatedData['selesai_cuti']);
        $jenisCutiId = $validatedData['jenis_cuti_id'];
        $userId = Auth::id(); // ID pengguna yang mengajukan

        // --- PERHITUNGAN HARI KERJA EFEKTIF ---
        $lamaCuti = $this->calculateWorkdays($startDate, $endDate);
        if ($lamaCuti === false) { // Jika terjadi error saat kalkulasi (misal, data Holiday tidak bisa diakses)
            Alert::error('Gagal Kalkulasi', 'Terjadi kesalahan saat memproses data hari libur.');
            return back()->withInput();
        }
        // --- AKHIR PERHITUNGAN HARI KERJA ---

        // --- VALIDASI KUOTA & ATURAN CUTI SAKIT ---
        $jenisCuti = JenisCuti::find($jenisCutiId); // Ambil model JenisCuti
        $isCutiSakit = (strtolower($jenisCuti->nama_cuti) === 'cuti sakit'); // Cek apakah jenis cuti adalah Cuti Sakit

        if (!$isCutiSakit) { // Jika bukan Cuti Sakit, cek kuota
            $cutiQuota = CutiQuota::where('user_id', $userId)->where('jenis_cuti_id', $jenisCutiId)->first();
            // Jika lama cuti (hari kerja) > 0 DAN (kuota tidak ada ATAU kuota tidak cukup)
            if ($lamaCuti > 0 && (!$cutiQuota || $cutiQuota->durasi_cuti < $lamaCuti)) {
                Alert::error('Kuota Tidak Cukup', 'Sisa kuota cuti (' . ($cutiQuota->durasi_cuti ?? 0) . ' hari) tidak mencukupi untuk ' . $lamaCuti . ' hari kerja yang diajukan.');
                return back()->withInput();
            }
        }
        // Jika Cuti Sakit dan durasinya >= 2 hari kerja, surat sakit wajib diunggah
        if ($isCutiSakit && $lamaCuti >= 2 && !$request->hasFile('surat_sakit')) {
            return back()->withInput()->withErrors(['surat_sakit' => 'Surat sakit wajib diunggah untuk cuti sakit 2 hari kerja atau lebih.']);
        }
        // --- AKHIR VALIDASI KUOTA & ATURAN CUTI SAKIT ---

        // --- VALIDASI OVERLAP TANGGAL CUTI ---
        // Memeriksa apakah tanggal yang diajukan bertabrakan dengan pengajuan cuti lain yang sudah ada (pending/approved)
        if ($this->checkOverlap($userId, $startDate, $endDate)) {
            Alert::error('Tanggal Bertabrakan', 'Tanggal cuti yang Anda ajukan bertabrakan dengan pengajuan lain yang sudah ada (status pending atau disetujui).');
            return back()->withInput();
        }
        // --- AKHIR VALIDASI OVERLAP ---

        // --- MENYIAPKAN DAN MENYIMPAN DATA PENGAJUAN CUTI ---
        $cutiData = $validatedData; // Ambil data yang sudah divalidasi
        $cutiData['user_id'] = $userId;
        $cutiData['lama_cuti'] = $lamaCuti; // Simpan durasi hari kerja efektif
        $cutiData['status'] = 'pending';   // Status awal pengajuan

        // Jika ada file surat sakit yang diunggah
        if ($request->hasFile('surat_sakit')) {
            try {
                // Simpan file ke disk 'public' di dalam direktori 'surat_sakit'
                // Path yang disimpan adalah relatif terhadap direktori 'storage/app/public/'
                $cutiData['surat_sakit'] = $request->file('surat_sakit')->store('surat_sakit', 'public');
            } catch (\Exception $e) {
                Log::error("File Upload Error (Store Cuti - Surat Sakit): " . $e->getMessage());
                Alert::error('Gagal Upload', 'Gagal mengunggah file surat sakit.');
                return back()->withInput();
            }
        }

        Cuti::create($cutiData); // Membuat record baru di tabel 'cuti'
        Alert::success('Berhasil Diajukan', 'Pengajuan cuti (' . $lamaCuti . ' hari kerja) berhasil diajukan dan menunggu persetujuan.');
        return redirect()->route('cuti.index'); // Mengarahkan kembali ke halaman daftar cuti
    }

    /**
     * Menampilkan form untuk mengedit pengajuan cuti yang sudah ada.
     * Memerlukan otorisasi 'update' dari CutiPolicy.
     *
     * @param  \App\Models\Cuti  $cuti Instance Cuti yang akan diedit (via Route Model Binding).
     * @return \Illuminate\View\View Mengembalikan view 'cuti.edit' dengan data cuti dan jenis cuti.
     */
    public function edit(Cuti $cuti)
    {
        // Otorisasi: Memastikan pengguna berhak mengedit pengajuan ini.
        $this->authorize('update', $cuti);

        $jenisCuti = JenisCuti::orderBy('nama_cuti')->get(); // Daftar jenis cuti untuk dropdown
        return view('cuti.edit', compact('cuti', 'jenisCuti'));
    }

    /**
     * Memperbarui data pengajuan cuti yang sudah ada di database.
     * Setelah diupdate, status pengajuan akan direset menjadi 'pending' dan
     * semua data approval sebelumnya akan dihapus untuk memulai alur persetujuan dari awal.
     *
     * @param  \Illuminate\Http\Request  $request Data dari form edit cuti.
     * @param  \App\Models\Cuti  $cuti Instance Cuti yang akan diupdate.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali dengan pesan status.
     */
    public function update(Request $request, Cuti $cuti)
    {
        // Otorisasi: Memastikan pengguna berhak memperbarui pengajuan ini.
        $this->authorize('update', $cuti);

        // Validasi tambahan: Hanya bisa edit jika status 'pending' atau 'rejected'
        if (!in_array($cuti->status, ['pending', 'rejected'])) {
            Alert::error('Tidak Dapat Diedit', 'Pengajuan cuti ini tidak dapat diedit karena statusnya bukan pending atau rejected.');
            return redirect()->route('cuti.index');
        }

        // Validasi data input (mirip dengan store)
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
        $userId = $cuti->user_id; // User ID tetap dari data cuti yang sedang diedit

        // Hitung ulang hari kerja efektif
        $lamaCuti = $this->calculateWorkdays($startDate, $endDate);
        if ($lamaCuti === false) {
            Alert::error('Gagal Kalkulasi', 'Gagal menghitung ulang durasi hari kerja.');
            return back()->withInput();
        }

        // Validasi ulang kuota dan aturan cuti sakit
        $jenisCutiModel = JenisCuti::find($jenisCutiId);
        $isCutiSakit = (strtolower($jenisCutiModel->nama_cuti) === 'cuti sakit');
        if (!$isCutiSakit) {
            $cutiQuota = CutiQuota::where('user_id', $userId)->where('jenis_cuti_id', $jenisCutiId)->first();
            if ($lamaCuti > 0 && (!$cutiQuota || $cutiQuota->durasi_cuti < $lamaCuti)) {
                Alert::error('Kuota Tidak Cukup', 'Sisa kuota cuti (' . ($cutiQuota->durasi_cuti ?? 0) . ' hari) tidak mencukupi.');
                return back()->withInput();
            }
        }
        $hasExistingSurat = !empty($cuti->surat_sakit); // Cek apakah sudah ada surat sakit sebelumnya
        if ($isCutiSakit && $lamaCuti >= 2 && !$request->hasFile('surat_sakit') && !$hasExistingSurat) {
            // Jika cuti sakit >= 2 hari, tidak ada file baru diupload, DAN tidak ada file lama, maka error.
            return back()->withInput()->withErrors(['surat_sakit' => 'Surat sakit wajib diunggah.']);
        }

        // Validasi ulang overlap tanggal, dengan mengecualikan ID cuti yang sedang diedit
        if ($this->checkOverlap($userId, $startDate, $endDate, $cuti->id)) {
            Alert::error('Tanggal Bertabrakan', 'Tanggal cuti yang Anda ajukan bertabrakan dengan pengajuan lain.');
            return back()->withInput();
        }

        // --- PROSES UPDATE DATA ---
        DB::beginTransaction(); // Memulai transaksi
        try {
            $updateData = $validatedData;
            $updateData['lama_cuti'] = $lamaCuti;
            // Saat pengajuan diedit, status kembali ke 'pending' dan semua data approval direset
            $updateData['status'] = 'pending';
            $updateData['notes'] = null;
            $updateData['approved_by_asisten_id'] = null;
            $updateData['approved_at_asisten'] = null;
            $updateData['approved_by_manager_id'] = null;
            $updateData['approved_at_manager'] = null;
            $updateData['rejected_by_id'] = null;
            $updateData['rejected_at'] = null;
            $updateData['last_reminder_sent_at'] = null; // Reset juga timestamp reminder

            // Penanganan file surat sakit saat update
            if ($request->hasFile('surat_sakit')) {
                // Jika ada file surat sakit lama, hapus terlebih dahulu
                if ($cuti->surat_sakit && Storage::disk('public')->exists($cuti->surat_sakit)) {
                    Storage::disk('public')->delete($cuti->surat_sakit);
                }
                // Simpan file surat sakit yang baru
                $updateData['surat_sakit'] = $request->file('surat_sakit')->store('surat_sakit', 'public');
            } else {
                // Jika tidak ada file baru yang diunggah, jangan ubah field 'surat_sakit' di database
                // (biarkan path file lama tetap ada, kecuali jika ada opsi untuk menghapusnya secara eksplisit).
                // Dengan `unset`, jika tidak ada file baru, kolom `surat_sakit` tidak akan diupdate.
                unset($updateData['surat_sakit']);
            }

            $cuti->update($updateData); // Melakukan update pada record cuti
            DB::commit(); // Simpan perubahan jika semua berhasil

            Alert::success('Berhasil Diperbarui', 'Pengajuan cuti berhasil diperbarui dan diajukan ulang untuk persetujuan.');
            return redirect()->route('cuti.index');
        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan transaksi jika terjadi error
            Log::error("Error saat memperbarui Cuti ID {$cuti->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Update', 'Gagal menyimpan perubahan pengajuan cuti.');
            return back()->withInput();
        }
        // --- AKHIR PROSES UPDATE ---
    }

    /**
     * Membatalkan pengajuan cuti yang sudah ada.
     * Memerlukan otorisasi 'cancel' dari CutiPolicy.
     * Jika cuti yang dibatalkan sudah 'approved', kuota cuti akan dikembalikan.
     *
     * @param  \App\Models\Cuti  $cuti Instance Cuti yang akan dibatalkan.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar cuti dengan pesan status.
     */
    public function cancel(Cuti $cuti)
    {
        // Otorisasi: Memastikan pengguna berhak membatalkan pengajuan ini.
        $this->authorize('cancel', $cuti);

        // Validasi tambahan: Pengguna hanya bisa membatalkan jika tanggal mulai cuti belum lewat
        if (Carbon::parse($cuti->mulai_cuti)->isPast() && !$cuti->mulai_cuti->isToday()) {
            Alert::error('Tidak Dapat Dibatalkan', 'Pengajuan cuti yang sudah lewat tanggal mulainya tidak dapat dibatalkan.');
            return redirect()->route('cuti.index');
        }

        DB::beginTransaction();
        try {
            $wasApproved = ($cuti->status === 'approved'); // Cek apakah status sebelumnya adalah 'approved'
            $originalWorkdays = $cuti->lama_cuti; // Simpan durasi hari kerja efektif

            $cuti->status = 'cancelled'; // Ubah status menjadi 'cancelled'
            $cuti->save();

            // Jika cuti yang dibatalkan sebelumnya sudah 'approved' dan memiliki durasi hari kerja,
            // maka kuota cuti dikembalikan.
            if ($wasApproved && $originalWorkdays > 0) {
                $jenisCuti = $cuti->jenisCuti; // Ambil model JenisCuti terkait
                // Jangan kembalikan kuota untuk 'Cuti Sakit'
                if ($jenisCuti && strtolower($jenisCuti->nama_cuti) !== 'cuti sakit') {
                    // Gunakan lockForUpdate untuk mencegah race condition saat update kuota
                    $quota = CutiQuota::where('user_id', $cuti->user_id)
                        ->where('jenis_cuti_id', $cuti->jenis_cuti_id)
                        ->lockForUpdate()->first();
                    if ($quota) {
                        $quota->increment('durasi_cuti', $originalWorkdays); // Tambah kembali kuota
                        Log::info("Kuota cuti dikembalikan untuk User ID {$cuti->user_id}, Jenis Cuti ID {$cuti->jenis_cuti_id} sebanyak {$originalWorkdays} hari karena pembatalan Cuti ID {$cuti->id}.");
                    } else {
                        // Jika record kuota tidak ditemukan (seharusnya jarang terjadi jika sistem konsisten),
                        // buat record kuota baru dengan nilai yang dikembalikan.
                        CutiQuota::create([
                            'user_id' => $cuti->user_id,
                            'jenis_cuti_id' => $cuti->jenis_cuti_id,
                            'durasi_cuti' => $originalWorkdays
                        ]);
                        Log::warning("CutiQuota tidak ditemukan, dibuat baru saat pembatalan Cuti ID {$cuti->id}. User ID {$cuti->user_id}, Jenis Cuti ID {$cuti->jenis_cuti_id}, Kuota Dikembalikan: {$originalWorkdays} hari.");
                    }
                }
            }
            DB::commit();
            Alert::success('Berhasil Dibatalkan', 'Pengajuan cuti telah berhasil dibatalkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat membatalkan Cuti ID {$cuti->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Membatalkan', 'Gagal membatalkan pengajuan cuti.');
        }
        return redirect()->route('cuti.index');
    }

    /**
     * Menampilkan daftar pengajuan cuti yang menunggu persetujuan Asisten Manager.
     * Daftar difilter berdasarkan scope jabatan Asisten Manager yang login.
     *
     * @return \Illuminate\View\View Mengembalikan view 'cuti.approval.asisten_list'.
     */
    public function listForAsisten()
    {
        // Otorisasi untuk melihat halaman ini biasanya dihandle oleh middleware 'role:manajemen'
        // dan/atau method 'viewAssistantApprovalList' di CutiPolicy.
        $this->authorize('viewAsistenApprovalList', Cuti::class); // Memanggil CutiPolicy

        /** @var \App\Models\User $user Asisten Manager yang sedang login. */
        $user = Auth::user();
        $perPage = 15;

        // Query mengambil cuti yang statusnya 'pending' dan belum ada approval L1
        $query = Cuti::where('status', 'pending')->whereNull('approved_by_asisten_id')
            ->with(['user:id,name,jabatan', 'jenisCuti:id,nama_cuti']); // Eager load data user & jenis cuti

        // Filter berdasarkan scope jabatan Asisten Manager
        if ($user->jabatan === 'asisten manager analis') {
            $query->whereHas('user', fn($q) => $q->whereIn('jabatan', ['analis', 'admin']));
        } elseif ($user->jabatan === 'asisten manager preparator') {
            $query->whereHas('user', fn($q) => $q->whereIn('jabatan', ['preparator', 'mekanik', 'admin']));
        } else {
            // Jika jabatan Asisten tidak sesuai (seharusnya dicegah oleh policy), jangan tampilkan apa-apa
            $query->whereRaw('1 = 0');
        }
        $pendingCuti = $query->orderBy('created_at', 'asc')->paginate($perPage); // Urutkan berdasarkan pengajuan terlama

        // Mengambil data kuota yang relevan untuk ditampilkan di daftar approval
        $relevantQuotas = $this->getRelevantQuotas($pendingCuti);

        return view('cuti.approval.asisten_list', compact('pendingCuti', 'relevantQuotas'));
    }

    /**
     * Memproses persetujuan pengajuan cuti oleh Asisten Manager (Level 1).
     *
     * @param  \App\Models\Cuti  $cuti Instance Cuti yang akan disetujui.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar approval Asisten.
     */
    public function approveAsisten(Cuti $cuti)
    {
        // Otorisasi: Memastikan Asisten Manager berhak menyetujui pengajuan ini.
        $this->authorize('approveAsisten', $cuti); // Memanggil CutiPolicy@approveAsisten
        /** @var \App\Models\User $assistant Asisten Manager yang melakukan approval. */
        $assistant = Auth::user();

        // Validasi status sebelum diproses
        if ($cuti->status !== 'pending') {
            Alert::warning('Sudah Diproses', 'Pengajuan ini sudah diproses atau statusnya tidak lagi pending.');
            return redirect()->route('cuti.approval.asisten.list');
        }

        DB::beginTransaction();
        try {
            $cuti->approved_by_asisten_id = $assistant->id;
            $cuti->approved_at_asisten = now();
            $cuti->status = 'pending_manager_approval'; // Status berikutnya menunggu approval Manager
            $cuti->save();
            DB::commit();

            // Opsional: Kirim notifikasi email ke Manager bahwa ada cuti menunggu approval L2.
            // ... (logika pengiriman email ke Manager) ...

            Alert::success('Berhasil Disetujui (L1)', 'Pengajuan cuti telah disetujui dan diteruskan ke Manager.');
            Log::info("Cuti ID {$cuti->id} disetujui (L1) oleh Asisten ID {$assistant->id}. Status diubah menjadi 'pending_manager_approval'.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat menyetujui L1 Cuti ID {$cuti->id} oleh Asisten {$assistant->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Approve', 'Gagal memproses persetujuan level 1.');
        }
        return redirect()->route('cuti.approval.asisten.list');
    }

    /**
     * Menampilkan daftar pengajuan cuti yang menunggu persetujuan final dari Manager.
     *
     * @return \Illuminate\View\View Mengembalikan view 'cuti.approval.manager_list'.
     */
    public function listForManager()
    {
        // Otorisasi: Memastikan Manager berhak melihat halaman ini.
        $this->authorize('viewManagerApprovalList', Cuti::class); // Memanggil CutiPolicy
        /** @var \App\Models\User $user Manager yang sedang login. */
        $user = Auth::user();

        // Validasi tambahan (meskipun policy seharusnya sudah menangani)
        if ($user->jabatan !== 'manager') {
            Alert::error('Akses Ditolak', 'Hanya Manager yang dapat mengakses halaman persetujuan ini.');
            return redirect()->route('dashboard.index'); // Atau halaman lain yang sesuai
        }

        $perPage = 15;
        // Query mengambil cuti yang statusnya 'pending_manager_approval' (atau 'pending_manager' sesuai konsistensi Anda)
        // dan belum ada approval final dari Manager atau penolakan.
        $pendingCutiManager = Cuti::where('status', 'pending_manager_approval')
            ->whereNull('approved_by_manager_id')->whereNull('rejected_by_id')
            ->with(['user:id,name,jabatan', 'jenisCuti:id,nama_cuti', 'approverAsisten:id,name']) // Eager load data terkait
            ->orderBy('approved_at_asisten', 'asc') // Urutkan berdasarkan approval Asisten terlama
            ->paginate($perPage);

        // Mengambil data kuota yang relevan untuk ditampilkan
        $relevantQuotas = $this->getRelevantQuotas($pendingCutiManager);

        return view('cuti.approval.manager_list', compact('pendingCutiManager', 'relevantQuotas'));
    }

    /**
     * Memproses persetujuan final pengajuan cuti oleh Manager (Level 2).
     * Jika disetujui, kuota cuti karyawan akan dikurangi.
     * Notifikasi email akan dikirim kepada pengaju.
     *
     * @param  \App\Models\Cuti  $cuti Instance Cuti yang akan disetujui.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar approval Manager.
     */
    public function approveManager(Cuti $cuti)
    {
        // Otorisasi: Memastikan Manager berhak menyetujui pengajuan ini.
        $this->authorize('approveManager', $cuti); // Memanggil CutiPolicy@approveManager
        /** @var \App\Models\User $approver Manager yang melakukan approval. */
        $approver = Auth::user();

        // Validasi status sebelum diproses
        if ($cuti->status !== 'pending_manager_approval') { // Pastikan status sesuai
            Alert::warning('Sudah Diproses', 'Pengajuan ini sudah diproses atau statusnya tidak lagi menunggu persetujuan Manager.');
            return redirect()->route('cuti.approval.manager.list');
        }

        DB::beginTransaction();
        try {
            $jenisCuti = $cuti->jenisCuti; // Ambil model JenisCuti terkait
            $lamaCutiHariKerja = $cuti->lama_cuti; // Durasi hari kerja efektif

            // Pengurangan Kuota Cuti (hanya jika bukan Cuti Sakit dan ada hari kerja yang diambil)
            if ($jenisCuti && strtolower($jenisCuti->nama_cuti) !== 'cuti sakit' && $lamaCutiHariKerja > 0) {
                // Gunakan lockForUpdate untuk mencegah race condition saat update kuota
                $quota = CutiQuota::where('user_id', $cuti->user_id)
                    ->where('jenis_cuti_id', $cuti->jenis_cuti_id)
                    ->lockForUpdate()->first();

                // Validasi ulang kuota sebelum pengurangan
                if (!$quota || $quota->durasi_cuti < $lamaCutiHariKerja) {
                    DB::rollBack();
                    Alert::error('Gagal Approve', 'Kuota cuti pengguna (' . ($quota->durasi_cuti ?? 0) . ' hari) tidak mencukupi untuk ' . $lamaCutiHariKerja . ' hari kerja.');
                    return redirect()->route('cuti.approval.manager.list');
                }
                $quota->decrement('durasi_cuti', $lamaCutiHariKerja); // Kurangi kuota
                Log::info("Kuota cuti dikurangi untuk User ID {$cuti->user_id}, Jenis Cuti ID {$cuti->jenis_cuti_id} sebanyak {$lamaCutiHariKerja} hari untuk Cuti ID {$cuti->id}.");
            }

            // Update Status Pengajuan Cuti
            $cuti->approved_by_manager_id = $approver->id;
            $cuti->approved_at_manager = now();
            $cuti->status = 'approved'; // Status final: disetujui
            $cuti->rejected_by_id = null; // Hapus data rejecter jika sebelumnya pernah ditolak
            $cuti->rejected_at = null;
            $cuti->notes = $cuti->notes ? $cuti->notes . ' | Approved by Manager.' : 'Approved by Manager.'; // Tambahkan catatan approval
            $cuti->save();

            DB::commit(); // Simpan semua perubahan jika berhasil

            // --- KIRIM EMAIL NOTIFIKASI APPROVAL FINAL KE PENGAJU ---
            try {
                /** @var \App\Models\User $applicant Pengguna yang mengajukan cuti. */
                $applicant = $cuti->user()->first(); // Ambil objek User pengaju
                if ($applicant && $applicant->email) {
                    // Mengirim Mailable dengan status 'approved' dan data approver (Manager)
                    Mail::to($applicant->email)->queue(new LeaveStatusNotificationMail($cuti, 'approved', $approver));
                    Log::info("Notifikasi persetujuan final cuti (ID: {$cuti->id}) telah diantrikan untuk {$applicant->email}");
                } else {
                    Log::warning("Tidak dapat mengirim notifikasi persetujuan final cuti: User atau email pengaju tidak ditemukan untuk Cuti ID {$cuti->id}");
                }
            } catch (\Exception $e) {
                Log::error("Gagal mengirim email notifikasi persetujuan final cuti untuk Cuti ID {$cuti->id}: " . $e->getMessage());
                // Kegagalan pengiriman email tidak seharusnya menggagalkan proses utama.
            }
            // --- AKHIR KIRIM EMAIL ---

            Alert::success('Berhasil Disetujui Final', 'Pengajuan cuti untuk ' . ($cuti->user?->name ?? 'N/A') . ' telah disetujui.');
            Log::info("Cuti ID {$cuti->id} disetujui (Final) oleh Manager ID {$approver->id}.");
        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan semua perubahan jika terjadi error
            Log::error("Error saat menyetujui L2 Cuti ID {$cuti->id} oleh Manager {$approver->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Approve Final', 'Gagal memproses persetujuan final.');
        }
        return redirect()->route('cuti.approval.manager.list');
    }

    /**
     * Menolak pengajuan cuti. Dapat dilakukan oleh Asisten Manager atau Manager.
     *
     * @param  \Illuminate\Http\Request  $request Data request yang berisi alasan penolakan.
     * @param  \App\Models\Cuti  $cuti Instance Cuti yang akan ditolak.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar approval yang relevan.
     */
    public function reject(Request $request, Cuti $cuti)
    {
        // Otorisasi: Memastikan pengguna berhak menolak pengajuan ini.
        $this->authorize('reject', $cuti); // Memanggil CutiPolicy@reject
        /** @var \App\Models\User $rejecter Pengguna yang melakukan penolakan. */
        $rejecter = Auth::user();

        // Validasi input alasan penolakan
        $validated = $request->validate(['notes' => 'required|string|min:5|max:500'], [
            'notes.required' => 'Alasan penolakan wajib diisi.',
            'notes.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        // Validasi status sebelum diproses
        if (!in_array($cuti->status, ['pending', 'pending_manager_approval'])) {
            Alert::warning('Tidak Dapat Diproses', 'Pengajuan ini tidak dalam status yang bisa ditolak oleh Anda saat ini.');
            return redirect()->back();
        }

        DB::beginTransaction();
        try {
            $cuti->rejected_by_id = $rejecter->id;
            $cuti->rejected_at = now();
            $cuti->status = 'rejected'; // Status diubah menjadi ditolak
            $cuti->notes = $validated['notes']; // Simpan alasan penolakan

            // Jika Manager yang menolak pengajuan yang sudah diapprove Asisten,
            // reset data approval Asisten.
            if ($rejecter->jabatan === 'manager' && $cuti->approved_by_asisten_id) {
                $cuti->approved_by_asisten_id = null;
                $cuti->approved_at_asisten = null;
            }
            $cuti->save();
            DB::commit();

            // --- KIRIM EMAIL NOTIFIKASI PENOLAKAN KE PENGAJU ---
            try {
                /** @var \App\Models\User $applicant Pengguna yang mengajukan cuti. */
                $applicant = $cuti->user()->first();
                if ($applicant && $applicant->email) {
                    // Mengirim Mailable dengan status 'rejected' dan data rejecter
                    Mail::to($applicant->email)->queue(new LeaveStatusNotificationMail($cuti, 'rejected', $rejecter));
                    Log::info("Notifikasi penolakan cuti (ID: {$cuti->id}) telah diantrikan untuk {$applicant->email}");
                } else {
                    Log::warning("Tidak dapat mengirim notifikasi penolakan cuti: User atau email pengaju tidak ditemukan untuk Cuti ID {$cuti->id}");
                }
            } catch (\Exception $e) {
                Log::error("Gagal mengirim email notifikasi penolakan cuti untuk Cuti ID {$cuti->id}: " . $e->getMessage());
            }
            // --- AKHIR KIRIM EMAIL ---

            Alert::success('Berhasil Ditolak', 'Pengajuan cuti telah berhasil ditolak.');
            Log::info("Cuti ID {$cuti->id} ditolak oleh User ID {$rejecter->id}. Alasan: {$validated['notes']}");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat menolak Cuti ID {$cuti->id} oleh User {$rejecter->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Menolak', 'Gagal memproses penolakan.');
        }

        // Redirect kembali ke halaman daftar approval yang sesuai dengan peran penolak
        if ($rejecter->jabatan === 'manager') {
            return redirect()->route('cuti.approval.manager.list');
        } else { // Asisten Manager
            return redirect()->route('cuti.approval.asisten.list');
        }
    }

    /**
     * Mengunduh formulir cuti yang sudah disetujui dalam format PDF.
     * Memerlukan otorisasi 'downloadPdf' dari CutiPolicy.
     *
     * @param  \App\Models\Cuti  $cuti Instance Cuti yang akan diunduh PDF-nya.
     * @return \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\RedirectResponse File PDF atau redirect jika gagal.
     */
    public function downloadPdf(Cuti $cuti)
    {
        // Otorisasi: Memastikan pengguna berhak mengunduh PDF ini.
        $this->authorize('downloadPdf', $cuti); // Memanggil CutiPolicy@downloadPdf

        // Validasi tambahan: Hanya cuti yang sudah 'approved' yang bisa diunduh PDF-nya.
        if ($cuti->status !== 'approved') {
            Alert::error('Belum Disetujui', 'Formulir PDF hanya bisa diunduh untuk pengajuan cuti yang sudah disetujui.');
            return redirect()->back();
        }

        // Eager load relasi yang dibutuhkan untuk template PDF
        $cuti->load([
            'user' => function ($query) {
                // Memilih kolom spesifik untuk efisiensi dan memuat relasi vendor beserta logonya
                $query->select('id', 'name', 'jabatan', 'tanggal_mulai_bekerja', 'signature_path', 'vendor_id')
                    ->with('vendor:id,name,logo_path');
            },
            'jenisCuti:id,nama_cuti', // Nama jenis cuti
            'approverAsisten:id,name,jabatan,signature_path', // Data Asisten Manager yang menyetujui
            'approverManager:id,name,jabatan,signature_path', // Data Manager yang menyetujui
            // 'rejecter:id,name' // Mungkin tidak perlu untuk PDF cuti yang sudah approved
        ]);

        // Membuat nama file PDF yang unik dan deskriptif
        $userName = $cuti->user ? Str::slug($cuti->user->name, '_') : 'user';
        $startDate = $cuti->mulai_cuti ? Carbon::parse($cuti->mulai_cuti)->format('Ymd') : 'nodate';
        $filename = 'form_cuti_' . $userName . '_' . $startDate . '.pdf'; // Contoh format nama file

        // Menggunakan view 'cuti.pdf_template' untuk generate PDF
        // Pastikan view ini sudah ada dan menerima variabel $cuti
        try {
            $pdf = Pdf::loadView('cuti.pdf_template', compact('cuti'));
            // Opsi: $pdf->setPaper('a4', 'portrait'); // Atur ukuran dan orientasi kertas jika perlu
            return $pdf->download($filename); // Langsung download file PDF
        } catch (\Exception $e) {
            Log::error("Error saat generate PDF Cuti untuk ID {$cuti->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Membuat PDF', 'Terjadi kesalahan saat membuat file PDF formulir cuti. Silakan coba lagi.');
            return redirect()->back();
        }
    }

    /**
     * Mengambil sisa kuota cuti pengguna yang sedang login via AJAX.
     * Digunakan oleh form pengajuan cuti untuk menampilkan sisa kuota secara dinamis
     * ketika jenis cuti dipilih.
     *
     * @param  \Illuminate\Http\Request  $request Data request yang berisi 'jenis_cuti_id'.
     * @return \Illuminate\Http\JsonResponse Respons JSON berisi sisa kuota cuti.
     */
    public function getQuota(Request $request)
    {
        // Validasi input jenis_cuti_id
        $validated = $request->validate(['jenis_cuti_id' => 'required|exists:jenis_cuti,id']);
        $jenisCutiId = $validated['jenis_cuti_id'];

        // Ambil kuota cuti pengguna yang login untuk jenis cuti yang dipilih
        $cutiQuota = CutiQuota::where('user_id', Auth::id())
            ->where('jenis_cuti_id', $jenisCutiId)->first();

        // Kembalikan sisa kuota dalam format JSON
        return response()->json(['durasi_cuti' => $cutiQuota ? $cutiQuota->durasi_cuti : 0]);
    }

    // --- Helper Methods ---

    /**
     * Menghitung jumlah hari kerja efektif antara dua tanggal (inklusif).
     * Hari kerja efektif tidak termasuk akhir pekan (Sabtu dan Minggu) dan
     * hari libur nasional yang terdaftar di tabel 'holidays'.
     *
     * @param  \Carbon\Carbon  $startDate Tanggal mulai periode (objek Carbon).
     * @param  \Carbon\Carbon  $endDate Tanggal selesai periode (objek Carbon).
     * @return int|false Jumlah hari kerja efektif dalam rentang tanggal tersebut, atau false jika terjadi error.
     */
    private function calculateWorkdays(Carbon $startDate, Carbon $endDate): int|false
    {
        // Jika tanggal mulai lebih besar dari tanggal selesai, tidak ada hari kerja.
        if ($startDate->gt($endDate)) {
            return 0;
        }
        $workDays = 0;
        try {
            // Ambil daftar hari libur dalam rentang tanggal yang diberikan
            $holidayDates = Holiday::whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()])
                ->pluck('tanggal') // Ambil hanya kolom tanggal
                ->map(fn($date) => Carbon::parse($date)->format('Y-m-d')) // Format ke Y-m-d untuk pencocokan
                ->toArray(); // Konversi ke array

            // Membuat objek CarbonPeriod untuk iterasi setiap hari dalam rentang tanggal
            $period = CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $date) {
                // Cek apakah tanggal saat ini BUKAN akhir pekan (Sabtu/Minggu)
                // DAN BUKAN merupakan salah satu hari libur yang ada di $holidayDates.
                if (!$date->isWeekend() && !in_array($date->format('Y-m-d'), $holidayDates)) {
                    $workDays++; // Tambah counter hari kerja
                }
            }
            return $workDays;
        } catch (\Exception $e) {
            Log::error("Helper calculateWorkdays Error (CutiController): " . $e->getMessage());
            return false; // Kembalikan false jika terjadi error saat mengambil data hari libur
        }
    }

    /**
     * Memeriksa apakah ada pengajuan cuti lain yang tumpang tindih (overlap)
     * untuk pengguna tertentu pada rentang tanggal yang diberikan.
     * Pengecekan dilakukan terhadap pengajuan yang statusnya 'pending', 'pending_manager_approval', atau 'approved'.
     *
     * @param  int  $userId ID pengguna yang akan dicek.
     * @param  \Carbon\Carbon  $startDate Tanggal mulai cuti baru/yang diedit.
     * @param  \Carbon\Carbon  $endDate Tanggal selesai cuti baru/yang diedit.
     * @param  int|null  $excludeCutiId ID pengajuan cuti yang sedang diedit (opsional),
     * untuk dikecualikan dari pengecekan overlap.
     * @return bool True jika ada tumpang tindih, false jika tidak.
     */
    private function checkOverlap(int $userId, Carbon $startDate, Carbon $endDate, ?int $excludeCutiId = null): bool
    {
        // Query dasar untuk mencari cuti milik pengguna dengan status yang relevan
        $query = Cuti::where('user_id', $userId)
            ->whereIn('status', ['pending', 'pending_manager_approval', 'approved']); // Hanya cek yang masih aktif/pending

        // Jika sedang mengupdate ($excludeCutiId ada nilainya),
        // kecualikan record cuti yang sedang diedit dari pengecekan overlap.
        if ($excludeCutiId) {
            $query->where('id', '!=', $excludeCutiId);
        }

        // Logika pengecekan overlap:
        // Sebuah overlap terjadi jika:
        // 1. Tanggal mulai cuti baru berada di dalam rentang cuti lama.
        // 2. Tanggal selesai cuti baru berada di dalam rentang cuti lama.
        // 3. Rentang cuti baru mencakup (mengurung) seluruh rentang cuti lama.
        // 4. Rentang cuti lama mencakup (mengurung) seluruh rentang cuti baru (ini sudah tercover oleh 1 & 2).
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->where(function ($sub) use ($startDate, $endDate) {
                // Cuti yang ada dimulai sebelum atau sama dengan akhir cuti baru, DAN
                // Cuti yang ada berakhir setelah atau sama dengan awal cuti baru.
                // Ini mencakup semua skenario overlap.
                $sub->where('mulai_cuti', '<=', $endDate)
                    ->where('selesai_cuti', '>=', $startDate);
            });
        })->exists(); // Mengembalikan true jika ada setidaknya satu record yang cocok (overlap)
    }

    /**
     * Helper method untuk mengambil data kuota yang relevan (sisa kuota)
     * untuk daftar pengajuan cuti yang dipaginasi.
     * Digunakan di halaman daftar approval untuk menampilkan sisa kuota pengaju.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $paginatedCuti Koleksi Cuti yang dipaginasi.
     * @return \Illuminate\Support\Collection Koleksi CutiQuota yang di-key berdasarkan "user_id_jenis_cuti_id".
     */
    private function getRelevantQuotas($paginatedCuti): \Illuminate\Support\Collection
    {
        $relevantQuotas = collect(); // Inisialisasi koleksi kosong
        if ($paginatedCuti->isNotEmpty()) {
            // Kumpulkan semua user_id dan jenis_cuti_id yang unik dari daftar cuti
            $userIds = collect($paginatedCuti->items())->pluck('user_id')->unique()->toArray();
            $jenisCutiIds = collect($paginatedCuti->items())->pluck('jenis_cuti_id')->unique()->toArray();

            // Ambil semua CutiQuota yang relevan dalam satu query
            if (!empty($userIds) && !empty($jenisCutiIds)) {
                $relevantQuotas = CutiQuota::whereIn('user_id', $userIds)
                    ->whereIn('jenis_cuti_id', $jenisCutiIds)
                    ->get()
                    // KeyBy untuk memudahkan pencarian di view: 'USERID_JENISCUTIID' => CutiQuotaObject
                    ->keyBy(fn($item) => $item->user_id . '_' . $item->jenis_cuti_id);
            }
        }
        return $relevantQuotas;
    }
}

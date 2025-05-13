<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Shift; // Diperlukan untuk dropdown shift di form create
use App\Models\User; // Meskipun tidak selalu digunakan langsung, ini adalah model yang relevan
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Validator; // Tidak digunakan secara eksplisit, validasi via $request->validate()
use Carbon\Carbon;
use RealRashid\SweetAlert\Facades\Alert; // Untuk notifikasi ke pengguna
use Illuminate\Support\Facades\Log; // Untuk logging error dan informasi
use App\Jobs\RecalculateAttendanceStatus; // Job untuk menghitung ulang status absensi
use Illuminate\Support\Facades\DB; // Untuk transaksi database
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Untuk otorisasi via Policy
use Illuminate\Support\Facades\Mail; // Untuk mengirim email notifikasi
use App\Mail\AttendanceCorrectionStatusMail; // Mailable untuk notifikasi status koreksi

/**
 * Class AttendanceCorrectionController
 *
 * Mengelola semua logika yang berkaitan dengan pengajuan dan persetujuan koreksi absensi.
 * Ini mencakup pembuatan pengajuan oleh pengguna, daftar pengajuan,
 * serta proses persetujuan atau penolakan oleh pihak yang berwenang (Asisten Manager).
 *
 * @package App\Http\Controllers
 */
class AttendanceCorrectionController extends Controller
{
    use AuthorizesRequests; // Mengaktifkan penggunaan Policy untuk otorisasi

    /**
     * Menampilkan daftar pengajuan koreksi absensi milik pengguna yang sedang login.
     * Pengguna dapat melihat histori pengajuan koreksi yang telah mereka buat beserta statusnya.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View Mengembalikan view 'attendance_corrections.index' dengan data koreksi pengguna.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user Pengguna yang sedang login. */
        $user = Auth::user();
        $perPage = 15; // Jumlah item per halaman untuk pagination

        // Mengambil hanya data koreksi absensi milik pengguna yang sedang login
        $query = AttendanceCorrection::where('user_id', $user->id)
            ->with(['processor:id,name', 'requestedShift:id,name']) // Eager load data approver/rejecter dan shift yang diajukan
            ->orderBy('correction_date', 'desc') // Urutkan berdasarkan tanggal koreksi terbaru
            ->orderBy('created_at', 'desc'); // Lalu berdasarkan tanggal pengajuan terbaru

        // Menerapkan filter berdasarkan status jika ada input dari request
        if ($request->filled('filter_status')) {
            $query->where('status', $request->filter_status);
        }

        $userCorrections = $query->paginate($perPage);

        // Menyertakan parameter filter ke link pagination agar filter tetap aktif saat berpindah halaman
        if ($request->has('filter_status')) {
            $userCorrections->appends(['filter_status' => $request->filter_status]);
        }

        return view('attendance_corrections.index', compact('userCorrections'));
    }

    /**
     * Menampilkan form untuk membuat pengajuan koreksi absensi baru.
     * Dapat menerima parameter tanggal opsional untuk pra-mengisi form
     * jika koreksi diajukan dari konteks tanggal tertentu.
     *
     * @param  string|null $attendance_date Tanggal absensi yang ingin dikoreksi (format YYYY-MM-DD, opsional).
     * @return \Illuminate\View\View Mengembalikan view 'attendance_corrections.create' dengan data yang diperlukan.
     */
    public function create($attendance_date = null)
    {
        // Otorisasi: Memeriksa apakah pengguna yang login berhak membuat pengajuan koreksi.
        // Ini akan memanggil method 'create' di AttendanceCorrectionPolicy.
        $this->authorize('create', AttendanceCorrection::class);

        /** @var \App\Models\User $user Pengguna yang sedang login. */
        $user = Auth::user();
        $correctionDate = null; // Tanggal koreksi yang akan di-prefill di form
        $originalAttendance = null; // Data absensi asli jika ditemukan untuk tanggal tersebut

        if ($attendance_date) {
            try {
                // Parsing dan format ulang tanggal untuk keamanan dan konsistensi
                $correctionDate = Carbon::parse($attendance_date)->format('Y-m-d');
                // Mencoba mengambil data absensi asli pengguna untuk tanggal yang diberikan
                $originalAttendance = Attendance::where('user_id', $user->id)
                    ->where('attendance_date', $correctionDate)
                    ->first();
            } catch (\Exception $e) {
                // Jika format tanggal salah atau terjadi error saat parsing,
                // biarkan $correctionDate null dan proses dilanjutkan tanpa pre-fill tanggal.
                Log::warning("Gagal parse tanggal untuk form koreksi: {$attendance_date}. Error: " . $e->getMessage());
            }
        }

        // Mengambil daftar shift yang aktif untuk ditampilkan di dropdown pilihan shift
        // Filter berdasarkan jenis kelamin pengguna bisa ditambahkan jika relevan
        $shifts = Shift::where('is_active', true)
            // ->whereIn('applicable_gender', ['Semua', $user->jenis_kelamin]) // Contoh filter gender
            ->orderBy('name') // Urutkan berdasarkan nama shift
            ->get(['id', 'name', 'start_time', 'end_time']); // Ambil hanya kolom yang dibutuhkan

        return view('attendance_corrections.create', compact(
            'user',
            'correctionDate',
            'originalAttendance',
            'shifts'
        ));
    }

    /**
     * Menyimpan pengajuan koreksi absensi baru ke database.
     *
     * @param  \Illuminate\Http\Request  $request Data dari form pengajuan koreksi.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali dengan pesan status.
     */
    public function store(Request $request)
    {
        // Otorisasi: Memeriksa apakah pengguna berhak menyimpan pengajuan koreksi.
        $this->authorize('create', AttendanceCorrection::class);

        /** @var \App\Models\User $user Pengguna yang sedang login. */
        $user = Auth::user();

        // Validasi data input dari form
        $validatedData = $request->validate([
            'correction_date' => 'required|date_format:Y-m-d', // Tanggal koreksi wajib dan format YYYY-MM-DD
            // Jam masuk atau jam keluar harus diisi salah satunya
            'requested_clock_in' => 'nullable|required_without:requested_clock_out|date_format:H:i',
            'requested_clock_out' => 'nullable|required_without:requested_clock_in|date_format:H:i',
            'requested_shift_id' => 'nullable|exists:shifts,id', // Jika diisi, harus ada di tabel shifts
            'reason' => 'required|string|min:10|max:1000', // Alasan koreksi wajib diisi
            'original_attendance_id' => 'nullable|sometimes|exists:attendances,id', // ID absensi asli (jika ada)
        ], [
            // Pesan error kustom untuk validasi
            'requested_clock_in.required_without' => 'Jam Masuk atau Jam Keluar harus diisi salah satunya.',
            'requested_clock_out.required_without' => 'Jam Masuk atau Jam Keluar harus diisi salah satunya.',
            'reason.min' => 'Alasan koreksi minimal harus 10 karakter.',
        ]);

        // Validasi tambahan: Jam keluar tidak boleh lebih awal dari jam masuk untuk shift normal (non-cross midnight)
        if ($validatedData['requested_clock_in'] && $validatedData['requested_clock_out']) {
            $shiftForValidation = $validatedData['requested_shift_id'] ? Shift::find($validatedData['requested_shift_id']) : null;
            $isCrossMidnight = $shiftForValidation ? $shiftForValidation->crosses_midnight : false;

            if (!$isCrossMidnight && $validatedData['requested_clock_out'] < $validatedData['requested_clock_in']) {
                return back()->withErrors(['requested_clock_out' => 'Jam Keluar tidak boleh lebih awal dari Jam Masuk untuk shift normal.'])->withInput();
            }
        }

        // Mencegah duplikasi pengajuan koreksi yang masih pending untuk tanggal dan user yang sama
        $existingPendingCorrection = AttendanceCorrection::where('user_id', $user->id)
            ->where('correction_date', $validatedData['correction_date'])
            ->where('status', 'pending')
            ->exists();

        if ($existingPendingCorrection) {
            Alert::error('Gagal Mengajukan', 'Anda sudah memiliki pengajuan koreksi yang sedang diproses untuk tanggal ini.');
            return back()->withInput();
        }

        DB::beginTransaction(); // Memulai transaksi database
        try {
            // Membuat record baru di tabel attendance_corrections
            AttendanceCorrection::create([
                'user_id' => $user->id,
                'attendance_id' => $request->input('original_attendance_id'), // Bisa null jika koreksi untuk hari Alpha murni
                'correction_date' => $validatedData['correction_date'],
                'requested_clock_in' => $validatedData['requested_clock_in'],
                'requested_clock_out' => $validatedData['requested_clock_out'],
                'requested_shift_id' => $validatedData['requested_shift_id'],
                'reason' => $validatedData['reason'],
                'status' => 'pending', // Status awal pengajuan adalah 'pending'
            ]);

            DB::commit(); // Simpan perubahan jika berhasil
            Alert::success('Berhasil Diajukan', 'Pengajuan koreksi absensi Anda telah berhasil dikirim dan menunggu persetujuan.');
            // Mengarahkan ke halaman daftar pengajuan koreksi milik pengguna
            return redirect()->route('attendance_corrections.index');
        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan transaksi jika terjadi error
            Log::error("Gagal menyimpan pengajuan koreksi absensi untuk User ID {$user->id} pada tanggal {$validatedData['correction_date']}: " . $e->getMessage());
            Alert::error('Gagal Sistem', 'Terjadi kesalahan saat mengirim pengajuan koreksi Anda. Silakan coba lagi.');
            return back()->withInput();
        }
    }

    /**
     * Menampilkan daftar pengajuan koreksi absensi yang menunggu persetujuan
     * untuk Asisten Manager yang sedang login.
     * Daftar difilter berdasarkan scope jabatan Asisten Manager.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View Mengembalikan view 'attendance_corrections.approval_list'.
     */
    public function listForApproval(Request $request)
    {
        // Otorisasi: Memeriksa apakah pengguna berhak melihat halaman daftar approval.
        // Akan memanggil method 'viewApprovalList' di AttendanceCorrectionPolicy.
        $this->authorize('viewApprovalList', AttendanceCorrection::class);

        /** @var \App\Models\User $user Approver (Asisten Manager) yang sedang login. */
        $user = Auth::user();
        $perPage = 15;

        // Query dasar untuk mengambil koreksi yang statusnya 'pending'
        $query = AttendanceCorrection::where('status', 'pending')
            ->with(['requester:id,name,jabatan', 'originalAttendance', 'requestedShift:id,name']); // Eager load data terkait

        // Menerapkan filter berdasarkan scope jabatan Asisten Manager
        // Asisten Manager hanya bisa melihat pengajuan dari karyawan di bawah tanggung jawabnya.
        if ($user->jabatan === 'asisten manager analis') {
            $query->whereHas('requester', fn($q) => $q->whereIn('jabatan', ['analis', 'admin']));
        } elseif ($user->jabatan === 'asisten manager preparator') {
            $query->whereHas('requester', fn($q) => $q->whereIn('jabatan', ['preparator', 'mekanik', 'admin']));
        } else {
            // Jika peran/jabatan tidak sesuai (misalnya Manager mengakses halaman ini),
            // jangan tampilkan data apa pun. Seharusnya dicegah oleh policy.
            $query->whereRaw('1 = 0'); // Query yang tidak akan menghasilkan apa-apa
        }

        // Filter pencarian berdasarkan nama pengaju atau tanggal koreksi
        $searchTerm = $request->input('search');
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->whereHas('requester', fn($uq) => $uq->where('name', 'like', '%' . $searchTerm . '%'))
                    ->orWhere('correction_date', 'like', '%' . $searchTerm . '%'); // Pencarian tanggal mungkin perlu format YYYY-MM-DD
            });
        }

        $pendingCorrections = $query->orderBy('created_at', 'asc')->paginate($perPage); // Urutkan berdasarkan tanggal pengajuan terlama

        // Menyertakan parameter filter ke link pagination
        if ($searchTerm) {
            $pendingCorrections->appends(['search' => $searchTerm]);
        }

        return view('attendance_corrections.approval_list', compact('pendingCorrections'));
    }

    /**
     * Menyetujui pengajuan koreksi absensi.
     * Method ini akan mengubah status koreksi menjadi 'approved', memperbarui data absensi asli
     * (atau membuat baru jika belum ada), dan memicu job untuk menghitung ulang status kehadiran.
     *
     * @param  \App\Models\AttendanceCorrection  $correction Instance koreksi yang akan disetujui (via Route Model Binding).
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar approval.
     */
    public function approve(AttendanceCorrection $correction)
    {
        // Otorisasi: Memeriksa apakah pengguna berhak menyetujui koreksi ini.
        $this->authorize('approve', $correction); // Akan memanggil AttendanceCorrectionPolicy@approve

        $correction->loadMissing('requester:id,name,email'); // Eager load data pengaju untuk notifikasi
        $requester = $correction->requester;
        $viewUrl = route('attendance_corrections.index'); // URL untuk link di email notifikasi
        /** @var \App\Models\User $approver Pengguna (Asisten Manager) yang melakukan approval. */
        $approver = Auth::user();

        DB::beginTransaction(); // Memulai transaksi
        try {
            // 1. Update status pengajuan koreksi
            $correction->status = 'approved';
            $correction->processed_by = $approver->id; // Simpan ID approver
            $correction->processed_at = now(); // Simpan timestamp approval
            $correction->reject_reason = null; // Hapus alasan penolakan jika sebelumnya ditolak lalu diapprove
            $correction->save();

            // 2. Persiapkan data untuk diupdate atau dibuat di tabel 'attendances'
            $attendanceData = [
                'user_id' => $correction->user_id,
                'attendance_date' => $correction->correction_date,
                'shift_id' => $correction->requested_shift_id, // Gunakan shift yang diajukan dalam koreksi
                // Gabungkan tanggal koreksi dengan jam yang diajukan, konversi ke format datetime
                'clock_in_time' => $correction->requested_clock_in ? Carbon::parse($correction->correction_date->format('Y-m-d') . ' ' . $correction->requested_clock_in)->toDateTimeString() : null,
                'clock_out_time' => $correction->requested_clock_out ? Carbon::parse($correction->correction_date->format('Y-m-d') . ' ' . $correction->requested_clock_out)->toDateTimeString() : null,
                'is_corrected' => true, // Tandai bahwa data absensi ini adalah hasil koreksi
                // Kolom lokasi dan foto tidak diubah oleh proses koreksi ini,
                // karena koreksi biasanya fokus pada waktu dan shift.
                // Jika absensi asli tidak ada, kolom lokasi/foto akan null.
            ];

            // Logika tambahan untuk menangani shift yang melewati tengah malam (cross midnight)
            // Jika jam keluar lebih awal dari jam masuk DAN shiftnya cross midnight, tambahkan 1 hari ke jam keluar.
            if ($attendanceData['clock_in_time'] && $attendanceData['clock_out_time']) {
                $shift = $correction->requestedShift()->first(); // Ambil model shift yang diajukan
                if ($shift && $shift->crosses_midnight) {
                    $clockInCarbon = Carbon::parse($attendanceData['clock_in_time']);
                    $clockOutCarbon = Carbon::parse($attendanceData['clock_out_time']);
                    if ($clockOutCarbon->lt($clockInCarbon)) { // Jika jam keluar < jam masuk
                        $attendanceData['clock_out_time'] = $clockOutCarbon->addDay()->toDateTimeString();
                    }
                }
            }

            // Gunakan updateOrCreate untuk memperbarui data absensi yang ada
            // atau membuat data baru jika belum ada (misalnya, koreksi untuk hari 'Alpha').
            // Kunci pencarian adalah user_id dan attendance_date.
            $updatedAttendance = Attendance::updateOrCreate(
                [
                    'user_id' => $correction->user_id,
                    'attendance_date' => $correction->correction_date,
                ],
                $attendanceData // Data yang akan di-update atau di-create
            );

            // 3. Memicu job untuk menghitung ulang status kehadiran berdasarkan data yang baru diupdate.
            // Ini penting agar status seperti 'Hadir', 'Terlambat', dll., menjadi akurat.
            RecalculateAttendanceStatus::dispatch($updatedAttendance);
            Log::info("Job RecalculateAttendanceStatus telah di-dispatch untuk Attendance ID: {$updatedAttendance->id} setelah koreksi disetujui.");

            // 4. Kirim email notifikasi ke pengguna bahwa koreksinya disetujui.
            if ($requester && $requester->email) {
                try {
                    Mail::to($requester->email)->queue(new AttendanceCorrectionStatusMail($correction, $approver, $viewUrl));
                    Log::info("Email notifikasi persetujuan koreksi absensi telah diantrikan untuk {$requester->email} (Correction ID: {$correction->id})");
                } catch (\Exception $e) {
                    Log::error("Gagal mengantrikan email notifikasi persetujuan koreksi untuk {$requester->email}: " . $e->getMessage());
                    // Kegagalan pengiriman email tidak seharusnya menggagalkan proses utama.
                }
            } else {
                Log::warning("Tidak dapat mengirim email notifikasi persetujuan: Pengaju atau email pengaju tidak ditemukan untuk Correction ID {$correction->id}.");
            }

            DB::commit(); // Simpan semua perubahan jika tidak ada error
            Alert::success('Berhasil Disetujui', 'Pengajuan koreksi absensi telah berhasil disetujui dan data absensi diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan semua perubahan jika terjadi error
            Log::error("Gagal menyetujui koreksi absensi ID {$correction->id} oleh User ID {$approver->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Sistem', 'Terjadi kesalahan saat memproses persetujuan koreksi. Silakan coba lagi.');
        }

        return redirect()->route('attendance_corrections.approval.list');
    }

    /**
     * Menolak pengajuan koreksi absensi.
     * Method ini akan mengubah status koreksi menjadi 'rejected' dan menyimpan alasan penolakan.
     *
     * @param  \Illuminate\Http\Request  $request Data request yang berisi alasan penolakan.
     * @param  \App\Models\AttendanceCorrection  $correction Instance koreksi yang akan ditolak.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke daftar approval.
     */
    public function reject(Request $request, AttendanceCorrection $correction)
    {
        // Otorisasi: Memeriksa apakah pengguna berhak menolak koreksi ini.
        $this->authorize('reject', $correction); // Akan memanggil AttendanceCorrectionPolicy@reject

        // Validasi input alasan penolakan
        $validated = $request->validate([
            'reject_reason' => 'required|string|min:5|max:500',
        ], [
            'reject_reason.required' => 'Alasan penolakan wajib diisi.',
            'reject_reason.min' => 'Alasan penolakan minimal harus 5 karakter.',
        ]);

        /** @var \App\Models\User $rejecter Pengguna (Asisten Manager) yang melakukan penolakan. */
        $rejecter = Auth::user();
        $correction->loadMissing('requester:id,name,email'); // Eager load data pengaju
        $requester = $correction->requester;
        $viewUrl = route('attendance_corrections.index'); // URL untuk link di email notifikasi

        DB::beginTransaction();
        try {
            // Update status pengajuan koreksi
            $correction->status = 'rejected';
            $correction->processed_by = $rejecter->id; // Simpan ID penolak
            $correction->processed_at = now(); // Simpan timestamp penolakan
            $correction->reject_reason = $validated['reject_reason']; // Simpan alasan penolakan
            $correction->save();

            DB::commit();

            // Kirim email notifikasi ke pengguna bahwa koreksinya ditolak.
            if ($requester && $requester->email) {
                try {
                    Mail::to($requester->email)->queue(new AttendanceCorrectionStatusMail($correction, $rejecter, $viewUrl));
                    Log::info("Email notifikasi penolakan koreksi absensi telah diantrikan untuk {$requester->email} (Correction ID: {$correction->id})");
                } catch (\Exception $e) {
                    Log::error("Gagal mengantrikan email notifikasi penolakan koreksi untuk {$requester->email}: " . $e->getMessage());
                }
            } else {
                Log::warning("Tidak dapat mengirim email notifikasi penolakan: Pengaju atau email pengaju tidak ditemukan untuk Correction ID {$correction->id}.");
            }

            Alert::success('Berhasil Ditolak', 'Pengajuan koreksi absensi telah berhasil ditolak.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal menolak koreksi absensi ID {$correction->id} oleh User ID {$rejecter->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Sistem', 'Terjadi kesalahan saat memproses penolakan koreksi.');
        }

        return redirect()->route('attendance_corrections.approval.list');
    }

    /**
     * Mengambil data absensi asli berdasarkan tanggal untuk pengguna yang sedang login.
     * Method ini biasanya dipanggil via AJAX dari form pembuatan koreksi absensi
     * untuk pra-mengisi data absensi yang sudah ada pada tanggal tersebut.
     *
     * @param  string $date Tanggal absensi yang ingin diambil datanya (format YYYY-MM-DD).
     * @return \Illuminate\Http\JsonResponse Respons JSON berisi data absensi asli atau null jika tidak ditemukan.
     */
    public function getOriginalData(string $date)
    {
        /** @var \App\Models\User $user Pengguna yang sedang login. */
        $user = Auth::user();

        try {
            // Validasi dan format ulang tanggal untuk keamanan
            $validDate = Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            // Jika format tanggal tidak valid, kembalikan error
            return response()->json(['error' => 'Format tanggal tidak valid. Gunakan format YYYY-MM-DD.'], 400); // HTTP 400 Bad Request
        }

        // Ambil data absensi asli, termasuk nama shift jika ada
        $originalAttendance = Attendance::with('shift:id,name')
            ->where('user_id', $user->id)
            ->where('attendance_date', $validDate)
            ->first();

        if ($originalAttendance) {
            // Format data yang akan dikirim sebagai respons JSON
            $data = [
                'id' => $originalAttendance->id, // ID record absensi asli
                'shift_name' => $originalAttendance->shift?->name ?? 'N/A', // Nama shift, 'N/A' jika tidak ada
                'shift_id' => $originalAttendance->shift_id, // ID shift asli
                'clock_in' => $originalAttendance->clock_in_time ? Carbon::parse($originalAttendance->clock_in_time)->format('H:i:s') : null, // Jam masuk, null jika tidak ada
                'clock_out' => $originalAttendance->clock_out_time ? Carbon::parse($originalAttendance->clock_out_time)->format('H:i:s') : null, // Jam keluar, null jika tidak ada
                'status' => $originalAttendance->attendance_status ?? 'Belum Diproses', // Status absensi
                'notes' => $originalAttendance->notes ?? '-', // Catatan absensi
            ];
            return response()->json($data);
        } else {
            // Jika tidak ada data absensi asli untuk tanggal tersebut, kirim null
            // Frontend bisa menangani ini untuk menampilkan form kosong atau pesan.
            return response()->json(null, 200); // HTTP 200 OK dengan body null
        }
    }
}

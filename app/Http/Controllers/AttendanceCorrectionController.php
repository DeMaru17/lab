<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Shift; // Diperlukan untuk dropdown shift
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator; // Untuk validasi manual jika perlu
use Carbon\Carbon;
use RealRashid\SweetAlert\Facades\Alert; // Untuk notifikasi
use Illuminate\Support\Facades\Log; // Untuk logging error
use App\Jobs\RecalculateAttendanceStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Add this line
use Illuminate\Support\Facades\Mail; // <-- Import Mail
use App\Mail\AttendanceCorrectionStatusMail; // <-- Import Mailable baru

class AttendanceCorrectionController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = 15;

        // Ambil hanya koreksi milik user yang login
        $query = AttendanceCorrection::where('user_id', $user->id)
            ->with(['processor:id,name', 'requestedShift:id,name']) // Eager load processor & shift
            ->orderBy('correction_date', 'desc') // Urutkan berdasarkan tanggal koreksi terbaru
            ->orderBy('created_at', 'desc'); // Lalu tanggal pengajuan terbaru

        // Filter berdasarkan status jika ada input filter (opsional)
        if ($request->filled('filter_status')) {
            $query->where('status', $request->filter_status);
        }

        $userCorrections = $query->paginate($perPage);

        // Append filter ke pagination links jika ada
        if ($request->has('filter_status')) {
            $userCorrections->appends(['filter_status' => $request->filter_status]);
        }

        // Kirim data ke view index (akan dibuat)
        return view('attendance_corrections.index', compact('userCorrections'));
    }

    /**
     * Menampilkan form untuk membuat pengajuan koreksi absensi baru.
     *
     * @param  string|null $attendance_date Tanggal absensi yang ingin dikoreksi (opsional, format YYYY-MM-DD)
     * @return \Illuminate\View\View
     */
    public function create($attendance_date = null)
    {
        // Otorisasi: Siapa yang boleh membuat pengajuan? Biasanya semua personil/admin.
        $this->authorize('create', AttendanceCorrection::class);

        $user = Auth::user();
        $correctionDate = null;
        $originalAttendance = null;

        if ($attendance_date) {
            try {
                $correctionDate = Carbon::parse($attendance_date)->format('Y-m-d');
                // Coba ambil data absensi asli jika ada, untuk pre-fill form
                $originalAttendance = Attendance::where('user_id', $user->id)
                    ->where('attendance_date', $correctionDate)
                    ->first();
            } catch (\Exception $e) {
                // Abaikan jika format tanggal salah, biarkan $correctionDate null
            }
        }

        // Ambil daftar shift yang aktif dan sesuai gender user (jika ada koreksi shift)
        $shifts = Shift::where('is_active', true)
            // ->whereIn('applicable_gender', ['Semua', $user->jenis_kelamin]) // Sesuaikan jika perlu filter gender
            ->orderBy('name')
            ->get(['id', 'name', 'start_time', 'end_time']);

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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // Otorisasi:
        $this->authorize('create', AttendanceCorrection::class);

        $user = Auth::user();

        $validatedData = $request->validate([
            'correction_date' => 'required|date_format:Y-m-d',
            // Jam harus diisi salah satu, atau keduanya. Tidak boleh kosong semua.
            'requested_clock_in' => 'nullable|required_without:requested_clock_out|date_format:H:i', // Format jam:menit
            'requested_clock_out' => 'nullable|required_without:requested_clock_in|date_format:H:i',
            'requested_shift_id' => 'nullable|exists:shifts,id', // Validasi jika shift diisi
            'reason' => 'required|string|min:10|max:1000',
            'original_attendance_id' => 'nullable|sometimes|exists:attendances,id', // ID absensi asli jika ada
        ], [
            'requested_clock_in.required_without' => 'Jam Masuk atau Jam Keluar harus diisi.',
            'requested_clock_out.required_without' => 'Jam Masuk atau Jam Keluar harus diisi.',
        ]);

        // Cek jika jam keluar lebih kecil dari jam masuk dan tidak ada shift yg cross midnight
        // (Validasi ini lebih kompleks jika melibatkan shift cross midnight, perlu hati-hati)
        // Untuk sementara kita sederhanakan.
        if ($validatedData['requested_clock_in'] && $validatedData['requested_clock_out']) {
            $shiftForValidation = $validatedData['requested_shift_id'] ? Shift::find($validatedData['requested_shift_id']) : null;
            $isCrossMidnight = $shiftForValidation ? $shiftForValidation->crosses_midnight : false;

            if (!$isCrossMidnight && $validatedData['requested_clock_out'] < $validatedData['requested_clock_in']) {
                return back()->withErrors(['requested_clock_out' => 'Jam Keluar tidak boleh lebih awal dari Jam Masuk untuk shift normal.'])->withInput();
            }
        }


        // Cek apakah sudah ada pengajuan koreksi PENDING untuk tanggal dan user yang sama
        $existingPendingCorrection = AttendanceCorrection::where('user_id', $user->id)
            ->where('correction_date', $validatedData['correction_date'])
            ->where('status', 'pending')
            ->exists();

        if ($existingPendingCorrection) {
            Alert::error('Gagal', 'Anda sudah memiliki pengajuan koreksi yang sedang diproses untuk tanggal ini.');
            return back()->withInput();
        }


        try {
            AttendanceCorrection::create([
                'user_id' => $user->id,
                'attendance_id' => $request->input('original_attendance_id'), // Bisa null
                'correction_date' => $validatedData['correction_date'],
                'requested_clock_in' => $validatedData['requested_clock_in'],
                'requested_clock_out' => $validatedData['requested_clock_out'],
                'requested_shift_id' => $validatedData['requested_shift_id'],
                'reason' => $validatedData['reason'],
                'status' => 'pending', // Status awal
            ]);

            Alert::success('Berhasil', 'Pengajuan koreksi absensi Anda telah berhasil dikirim dan menunggu persetujuan.');
            // Redirect ke halaman daftar pengajuan koreksi user (akan dibuat nanti)
            // Untuk sementara, redirect ke dashboard atau halaman absensi
            return redirect()->route('attendance_corrections.index');
        } catch (\Exception $e) {
            Log::error("Gagal menyimpan pengajuan koreksi absensi untuk User ID {$user->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan saat mengirim pengajuan koreksi Anda.');
            return back()->withInput();
        }
    }

    public function listForApproval(Request $request)
    {
        // Otorisasi: Cek apakah user boleh melihat halaman approval
        // Kita gunakan metode custom 'viewApprovalList' dari policy
        $this->authorize('viewApprovalList', AttendanceCorrection::class);

        $user = Auth::user(); // Approver (Asisten Manager) yang login
        $perPage = 15; // Jumlah item per halaman

        // Query dasar untuk koreksi yang pending
        $query = AttendanceCorrection::where('status', 'pending')
            ->with(['requester:id,name,jabatan', 'originalAttendance', 'requestedShift:id,name']); // Eager load relasi

        // Filter berdasarkan jabatan approver vs jabatan requester
        if ($user->jabatan === 'asisten manager analis') {
            $query->whereHas('requester', fn($q) => $q->whereIn('jabatan', ['analis', 'admin']));
        } elseif ($user->jabatan === 'asisten manager preparator') {
            $query->whereHas('requester', fn($q) => $q->whereIn('jabatan', ['preparator', 'mekanik', 'admin']));
        } else {
            // Jika bukan asisten yg relevan (misal Manager login ke halaman ini), jangan tampilkan apa-apa
            $query->whereRaw('1 = 0');
        }

        // Tambahkan filter pencarian jika perlu (misal berdasarkan nama requester atau tanggal)
        $searchTerm = $request->input('search');
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->whereHas('requester', fn($uq) => $uq->where('name', 'like', '%' . $searchTerm . '%'))
                    ->orWhere('correction_date', 'like', '%' . $searchTerm . '%');
            });
        }

        $pendingCorrections = $query->orderBy('created_at', 'asc')->paginate($perPage);

        if ($searchTerm) {
            $pendingCorrections->appends(['search' => $searchTerm]);
        }

        // Kirim data ke view approval_list (akan dibuat)
        return view('attendance_corrections.approval_list', compact('pendingCorrections'));
    }

    /**
     * Menyetujui pengajuan koreksi absensi.
     *
     * @param  \App\Models\AttendanceCorrection  $correction
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approve(AttendanceCorrection $correction)
    {
        // Otorisasi: Cek apakah user boleh approve koreksi ini
        $this->authorize('approve', $correction);

        $correction->loadMissing('requester:id,name,email');
        $requester = $correction->requester;
        $viewUrl = route('attendance_corrections.index');
        $approver = Auth::user();

        DB::beginTransaction();
        try {
            // 1. Update status koreksi
            $correction->status = 'approved';
            $correction->processed_by = $approver->id;
            $correction->processed_at = now();
            $correction->reject_reason = null; // Hapus alasan reject jika ada

            $correction->save();

            // 2. Update data absensi asli (jika ada) atau buat baru (jika Alpha murni)
            $attendanceData = [
                'user_id' => $correction->user_id,
                'attendance_date' => $correction->correction_date,
                'shift_id' => $correction->requested_shift_id, // Gunakan shift yg diajukan
                'clock_in_time' => $correction->requested_clock_in ? Carbon::parse($correction->correction_date->format('Y-m-d') . ' ' . $correction->requested_clock_in)->toDateTimeString() : null,
                'clock_out_time' => $correction->requested_clock_out ? Carbon::parse($correction->correction_date->format('Y-m-d') . ' ' . $correction->requested_clock_out)->toDateTimeString() : null,
                'is_corrected' => true, // Tandai bahwa data ini hasil koreksi
                // Kolom lain (lokasi, foto) mungkin perlu di-handle terpisah atau dibiarkan null/lama?
                // Untuk sekarang kita fokus update waktu dan shift.
            ];

            // Sesuaikan jam keluar jika shift cross midnight dan jam keluar < jam masuk
            if ($attendanceData['clock_in_time'] && $attendanceData['clock_out_time']) {
                $shift = $correction->requestedShift()->first(); // Ambil shift yg diajukan
                if ($shift && $shift->crosses_midnight) {
                    $clockInCarbon = Carbon::parse($attendanceData['clock_in_time']);
                    $clockOutCarbon = Carbon::parse($attendanceData['clock_out_time']);
                    if ($clockOutCarbon->lt($clockInCarbon)) {
                        $attendanceData['clock_out_time'] = $clockOutCarbon->addDay()->toDateTimeString();
                    }
                }
            }


            // Gunakan updateOrCreate berdasarkan user_id dan attendance_date
            $updatedAttendance = Attendance::updateOrCreate(
                [
                    'user_id' => $correction->user_id,
                    'attendance_date' => $correction->correction_date,
                ],
                $attendanceData // Data baru atau yang diperbarui
            );

            // 3. (PENTING) Panggil ulang logika kalkulasi status absensi
            //    SEKARANG kita akan panggil Job untuk ini agar tidak memberatkan request
            RecalculateAttendanceStatus::dispatch($updatedAttendance);
            Log::info("Job RecalculateAttendanceStatus dispatched for Attendance ID: {$updatedAttendance->id}");

            // --- 4. Kirim Email Notifikasi Approval ---
            if ($requester && $requester->email) {
                try {
                    Mail::to($requester->email)->queue(new AttendanceCorrectionStatusMail($correction, $approver, $viewUrl));
                    Log::info("Attendance correction approval email queued for {$requester->email} (Correction ID: {$correction->id})");
                } catch (\Exception $e) {
                    Log::error("Failed to queue attendance correction approval email for {$requester->email}: " . $e->getMessage());
                    // Jangan gagalkan proses utama hanya karena email
                }
            } else {
                Log::warning("Cannot queue approval email: Requester or email not found for Correction ID {$correction->id}.");
            }
            // --- Akhir Kirim Email ---

            DB::commit();
            Alert::success('Berhasil', 'Pengajuan koreksi absensi telah disetujui.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal menyetujui koreksi absensi ID {$correction->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan saat memproses persetujuan.');
        }

        return redirect()->route('attendance_corrections.approval.list');
    }

    /**
     * Menolak pengajuan koreksi absensi.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AttendanceCorrection  $correction
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reject(Request $request, AttendanceCorrection $correction)
    {
        // Otorisasi: Cek apakah user boleh reject koreksi ini
        $this->authorize('reject', $correction);

        // Validasi alasan penolakan
        $validated = $request->validate([
            'reject_reason' => 'required|string|min:5|max:500',
        ]);

        $rejecter = Auth::user();
        $correction->loadMissing('requester:id,name,email');
        $requester = $correction->requester;
        $viewUrl = route('attendance_corrections.index');

        try {
            $correction->status = 'rejected';
            $correction->processed_by = $rejecter->id;
            $correction->processed_at = now();
            $correction->reject_reason = $validated['reject_reason'];
            $correction->save();

            // --- Kirim Email Notifikasi Reject ---
            if ($requester && $requester->email) {
                try {
                    Mail::to($requester->email)->queue(new AttendanceCorrectionStatusMail($correction, $rejecter, $viewUrl));
                    Log::info("Attendance correction rejection email queued for {$requester->email} (Correction ID: {$correction->id})");
                } catch (\Exception $e) {
                    Log::error("Failed to queue attendance correction rejection email for {$requester->email}: " . $e->getMessage());
                }
            } else {
                Log::warning("Cannot queue rejection email: Requester or email not found for Correction ID {$correction->id}.");
            }
            // --- Akhir Kirim Email ---

            Alert::success('Berhasil', 'Pengajuan koreksi absensi telah ditolak.');
        } catch (\Exception $e) {
            Log::error("Gagal menolak koreksi absensi ID {$correction->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan saat memproses penolakan.');
        }

        return redirect()->route('attendance_corrections.approval.list');
    }

    /**
     * Mengambil data absensi asli berdasarkan tanggal untuk user yang login.
     * Digunakan oleh AJAX dari form create koreksi.
     *
     * @param string $date (Format YYYY-MM-DD)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOriginalData(string $date)
    {
        $user = Auth::user();

        try {
            $validDate = Carbon::parse($date)->format('Y-m-d'); // Validasi & format ulang
        } catch (\Exception $e) {
            return response()->json(['error' => 'Format tanggal tidak valid.'], 400);
        }

        $originalAttendance = Attendance::with('shift:id,name') // Eager load nama shift
            ->where('user_id', $user->id)
            ->where('attendance_date', $validDate)
            ->first();

        if ($originalAttendance) {
            // Format data untuk dikirim sebagai JSON
            $data = [
                'id' => $originalAttendance->id,
                'shift_name' => $originalAttendance->shift?->name ?? 'N/A', // Ambil nama shift jika ada
                'shift_id' => $originalAttendance->shift_id, // Kirim juga ID shift
                'clock_in' => $originalAttendance->clock_in_time ? Carbon::parse($originalAttendance->clock_in_time)->format('H:i:s') : 'N/A',
                'clock_out' => $originalAttendance->clock_out_time ? Carbon::parse($originalAttendance->clock_out_time)->format('H:i:s') : 'N/A',
                'status' => $originalAttendance->attendance_status ?? 'Belum Diproses',
                'notes' => $originalAttendance->notes ?? '-',
            ];
            return response()->json($data);
        } else {
            // Kirim response kosong atau status not found jika tidak ada data
            return response()->json(null, 200); // Atau 404 jika lebih sesuai
        }
    }
}

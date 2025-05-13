<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User; // Meskipun $user diambil dari Auth, ini adalah model yang relevan
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
// use RealRashid\SweetAlert\Facades\Alert; // Tidak digunakan di controller ini, bisa dihapus jika tidak ada rencana penggunaan
use Illuminate\Support\Facades\DB;

/**
 * Class AttendanceController
 *
 * Mengelola semua logika yang berkaitan dengan proses absensi karyawan,
 * termasuk menampilkan halaman absensi untuk check-in/check-out,
 * serta memproses dan menyimpan data absensi tersebut.
 *
 * @package App\Http\Controllers
 */
class AttendanceController extends Controller
{
    /**
     * Menampilkan halaman utama absensi.
     * Method ini menentukan status absensi pengguna saat ini (apakah sudah check-in,
     * sudah check-out, atau belum melakukan keduanya) dan mengambil daftar shift
     * yang relevan untuk ditampilkan di form absensi.
     *
     * @return \Illuminate\View\View Mengembalikan view 'attendances.index' dengan data yang diperlukan.
     */
    public function index()
    {
        /** @var \App\Models\User $user Pengguna yang sedang login. */
        $user = Auth::user();
        $today = Carbon::today()->toDateString(); // Mendapatkan tanggal hari ini dalam format YYYY-MM-DD

        // Mencari catatan absensi pengguna untuk hari ini
        $todaysAttendance = Attendance::where('user_id', $user->id)
            ->where('attendance_date', $today)
            ->first();

        // Menentukan aksi absensi berikutnya (check_in, check_out, atau completed)
        $attendanceAction = 'check_in'; // Default adalah check_in
        $checkInTime = null; // Waktu check-in, null jika belum check-in

        if ($todaysAttendance) {
            if (is_null($todaysAttendance->clock_out_time)) {
                // Jika sudah ada record absensi dan belum check-out
                $attendanceAction = 'check_out';
                $checkInTime = $todaysAttendance->clock_in_time;
            } else {
                // Jika sudah check-in dan check-out
                $attendanceAction = 'completed';
                $checkInTime = $todaysAttendance->clock_in_time;
            }
        }

        // Mengambil daftar shift yang aktif dan sesuai dengan jenis kelamin pengguna
        $shifts = Shift::where('is_active', true)
            // Memfilter shift berdasarkan kolom 'applicable_gender' di tabel shifts
            // yang harus cocok dengan 'jenis_kelamin' pengguna atau bernilai 'Semua'.
            ->whereIn('applicable_gender', ['Semua', $user->jenis_kelamin])
            ->orderBy('start_time')
            ->get(['id', 'name', 'start_time', 'end_time']); // Hanya mengambil kolom yang diperlukan untuk view

        // Mengirim data ke view
        return view('attendances.index', compact(
            'attendanceAction',
            'todaysAttendance', // Bisa null jika belum ada absensi hari ini
            'checkInTime',      // Bisa null
            'shifts'
        ));
    }

    /**
     * Menyimpan data check-in atau check-out yang dikirim dari frontend (diharapkan via AJAX/Fetch).
     * Method ini melakukan validasi input, validasi lokasi geografis, penyimpanan foto selfie,
     * dan akhirnya menyimpan atau memperbarui catatan absensi di database.
     *
     * @param  \Illuminate\Http\Request  $request Data request yang berisi detail absensi.
     * Harus menyertakan 'latitude', 'longitude', 'selfie_data' (base64),
     * 'action' ('check_in' atau 'check_out'), dan 'shift_id' (jika action adalah 'check_in').
     * @return \Illuminate\Http\JsonResponse Respons JSON yang mengindikasikan keberhasilan atau kegagalan operasi.
     */
    public function store(Request $request)
    {
        // Validasi input dari request
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'], // Regex untuk validasi format Latitude
            'longitude' => ['required', 'numeric', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'], // Regex untuk validasi format Longitude
            'selfie_data' => 'required|string', // Data gambar selfie dalam format base64 URI
            'shift_id' => 'required_if:action,check_in|nullable|exists:shifts,id', // Wajib jika action adalah 'check_in', dan harus ada di tabel shifts
            'action' => 'required|in:check_in,check_out', // Aksi yang valid hanya 'check_in' atau 'check_out'
        ]);

        /** @var \App\Models\User $user Pengguna yang sedang login. */
        $user = Auth::user();
        $today = Carbon::today()->toDateString();
        $now = Carbon::now(config('app.timezone', 'Asia/Jakarta')); // Menggunakan timezone aplikasi untuk konsistensi waktu

        // --- Validasi Lokasi Pengguna ---
        // Mengambil data konfigurasi lokasi kantor dan radius yang diizinkan.
        // yang berisi key 'office_latitude', 'office_longitude', dan 'allowed_radius_meters'.
        $officeLat = config('attendance.office_latitude');
        $officeLng = config('attendance.office_longitude');
        $allowedRadius = config('attendance.allowed_radius_meters');

        // Menghitung jarak pengguna dari lokasi kantor
        $distance = $this->calculateDistance(
            (float) $validated['latitude'], // Cast ke float untuk kalkulasi
            (float) $validated['longitude'],
            (float) $officeLat,
            (float) $officeLng
        );

        $locationStatus = 'Tidak Diketahui'; // Status lokasi default
        if ($distance !== false) { // Jika kalkulasi jarak berhasil
            $locationStatus = ($distance <= $allowedRadius) ? 'Dalam Radius' : 'Luar Radius';
        }

        // Jika lokasi pengguna berada di luar radius yang diizinkan, kembalikan respons error.
        if ($locationStatus === 'Luar Radius') {
            Log::warning("Upaya absensi di luar radius oleh User ID {$user->id}. Jarak: {$distance}m. Koordinat: {$validated['latitude']},{$validated['longitude']}");
            return response()->json([
                'success' => false,
                'message' => 'Anda berada di luar radius kantor yang diizinkan (' . round($distance) . ' meter dari lokasi kantor).'
            ], 422); // HTTP status 422: Unprocessable Entity
        }
        // --- Akhir Validasi Lokasi ---

        // --- Proses dan Simpan Foto Selfie ---
        $photoPath = null; // Path foto yang akan disimpan di database
        try {
            // Mengekstrak tipe gambar dan data base64 dari string data URI
            if (preg_match('/^data:image\/(\w+);base64,/', $validated['selfie_data'], $type)) {
                $imageData = substr($validated['selfie_data'], strpos($validated['selfie_data'], ',') + 1);
                $imageType = strtolower($type[1]); // Mendapatkan ekstensi gambar (jpg, png, dll.)

                // Validasi tipe gambar yang diizinkan
                if (!in_array($imageType, ['jpg', 'jpeg', 'png'])) {
                    throw new \Exception('Format gambar selfie tidak valid. Hanya JPG, JPEG, PNG yang diizinkan.');
                }
                $imageData = base64_decode($imageData); // Decode data gambar
                if ($imageData === false) {
                    throw new \Exception('Gagal melakukan decode base64 pada data gambar selfie.');
                }

                // Membuat nama file yang unik untuk foto selfie
                $fileName = 'selfie_' . $user->id . '_' . date('Ymd_His')  . '.' . $imageType;
                // Menyimpan file foto ke disk 'public' di dalam direktori 'attendance_photos'
                // Pastikan direktori 'storage/app/public/attendance_photos' ada dan writable.
                // Jalankan 'php artisan storage:link' jika belum.
                Storage::disk('public')->put('attendance_photos/' . $fileName, $imageData);
                $photoPath = 'attendance_photos/' . $fileName; // Path relatif yang akan disimpan di DB
            } else {
                throw new \Exception('Format data URI gambar selfie tidak valid.');
            }
        } catch (\Exception $e) {
            Log::error("Gagal mengunggah foto selfie untuk User ID {$user->id}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan foto selfie: ' . $e->getMessage()], 500); // HTTP status 500: Internal Server Error
        }
        // --- Akhir Simpan Foto Selfie ---

        // --- Simpan atau Update Data Absensi ke Database ---
        DB::beginTransaction(); // Memulai transaksi database untuk menjaga integritas data
        try {
            if ($validated['action'] === 'check_in') {
                // Logika untuk proses Check-In
                // Cek apakah pengguna sudah melakukan check-in sebelumnya pada hari yang sama
                $existingAttendance = Attendance::where('user_id', $user->id)->where('attendance_date', $today)->first();
                if ($existingAttendance) {
                    DB::rollBack(); // Batalkan transaksi jika ada
                    // Hapus foto yang mungkin sudah terlanjur diunggah jika check-in gagal karena duplikasi
                    if ($photoPath && Storage::disk('public')->exists($photoPath)) {
                        Storage::disk('public')->delete($photoPath);
                    }
                    Log::warning("Upaya check-in ganda oleh User ID {$user->id} pada tanggal {$today}.");
                    return response()->json(['success' => false, 'message' => 'Anda sudah melakukan check-in hari ini.'], 409); // HTTP status 409: Conflict
                }

                // Buat entri absensi baru
                Attendance::create([
                    'user_id' => $user->id,
                    'shift_id' => $validated['shift_id'],
                    'attendance_date' => $today,
                    'clock_in_time' => $now, // Waktu check-in saat ini
                    'clock_in_latitude' => $validated['latitude'],
                    'clock_in_longitude' => $validated['longitude'],
                    'clock_in_photo_path' => $photoPath,
                    'clock_in_location_status' => $locationStatus,
                    // Kolom 'attendance_status' akan diisi oleh scheduled task (ProcessDailyAttendance)
                ]);
                $message = 'Check-in berhasil dicatat pada pukul ' . $now->format('H:i:s') . '.';
                Log::info("User ID {$user->id} berhasil check-in pada {$now}. Lokasi: {$locationStatus}. Foto: {$photoPath}");
            } elseif ($validated['action'] === 'check_out') {
                // Logika untuk proses Check-Out
                // Cari data check-in yang belum ada check-outnya untuk hari ini
                $attendance = Attendance::where('user_id', $user->id)
                    ->where('attendance_date', $today)
                    ->whereNull('clock_out_time') // Hanya yang belum check-out
                    ->first();

                if (!$attendance) {
                    DB::rollBack();
                    if ($photoPath && Storage::disk('public')->exists($photoPath)) {
                        Storage::disk('public')->delete($photoPath);
                    }
                    Log::warning("Upaya check-out tanpa check-in sebelumnya oleh User ID {$user->id} pada tanggal {$today}.");
                    return response()->json(['success' => false, 'message' => 'Data check-in tidak ditemukan atau Anda sudah melakukan check-out.'], 404); // HTTP status 404: Not Found
                }

                // Validasi tambahan: jam check-out tidak boleh lebih kecil dari jam check-in
                if ($attendance->clock_in_time && $now->lessThan(Carbon::parse($attendance->clock_in_time))) {
                    DB::rollBack();
                    if ($photoPath && Storage::disk('public')->exists($photoPath)) {
                        Storage::disk('public')->delete($photoPath);
                    }
                    Log::warning("Upaya check-out sebelum waktu check-in untuk User ID {$user->id}, Attendance ID {$attendance->id}.");
                    return response()->json(['success' => false, 'message' => 'Waktu check-out tidak boleh sebelum waktu check-in.'], 422);
                }

                // Update record absensi dengan data check-out
                $attendance->update([
                    'clock_out_time' => $now, // Waktu check-out saat ini
                    'clock_out_latitude' => $validated['latitude'],
                    'clock_out_longitude' => $validated['longitude'],
                    'clock_out_photo_path' => $photoPath,
                    'clock_out_location_status' => $locationStatus,
                ]);
                $message = 'Check-out berhasil dicatat pada pukul ' . $now->format('H:i:s') . '.';
                Log::info("User ID {$user->id} berhasil check-out pada {$now} untuk Attendance ID {$attendance->id}. Lokasi: {$locationStatus}. Foto: {$photoPath}");
            }

            DB::commit(); // Simpan semua perubahan ke database jika tidak ada error
            return response()->json(['success' => true, 'message' => $message, 'action' => $validated['action']]);
        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan semua perubahan jika terjadi error selama proses DB
            // Hapus foto yang mungkin sudah terlanjur diunggah jika terjadi error saat proses database
            if ($photoPath && Storage::disk('public')->exists($photoPath)) {
                Storage::disk('public')->delete($photoPath);
                Log::info("Foto selfie '{$photoPath}' di-rollback karena error transaksi DB untuk User ID {$user->id}.");
            }
            Log::error("Error saat menyimpan data absensi untuk User ID {$user->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data absensi. Silakan coba lagi nanti.'], 500);
        }
        // --- Akhir Simpan/Update Data Absensi ---
    }


    /**
     * Menghitung jarak antara dua titik koordinat geografis menggunakan formula Haversine.
     * Formula ini memperhitungkan kelengkungan bumi untuk akurasi yang lebih baik.
     *
     * @param  float  $latitudeFrom  Latitude dari titik awal (dalam derajat desimal).
     * @param  float  $longitudeFrom Longitude dari titik awal (dalam derajat desimal).
     * @param  float  $latitudeTo    Latitude dari titik tujuan (dalam derajat desimal).
     * @param  float  $longitudeTo   Longitude dari titik tujuan (dalam derajat desimal).
     * @return float|false Jarak antara dua titik dalam meter, atau false jika input koordinat tidak valid.
     */
    private function calculateDistance(float $latitudeFrom, float $longitudeFrom, float $latitudeTo, float $longitudeTo): float|false
    {
        // Validasi dasar untuk tipe numerik, meskipun validasi regex sudah ada di controller store
        if (!is_numeric($latitudeFrom) || !is_numeric($longitudeFrom) || !is_numeric($latitudeTo) || !is_numeric($longitudeTo)) {
            Log::warning("Tipe koordinat tidak valid diberikan ke fungsi calculateDistance.");
            return false;
        }

        $earthRadius = 6371000; // Radius rata-rata bumi dalam meter

        // Konversi selisih derajat ke radian
        $latDelta = deg2rad($latitudeTo - $latitudeFrom);
        $lonDelta = deg2rad($longitudeTo - $longitudeFrom);

        // Konversi latitude awal dan tujuan ke radian
        $latFromRad = deg2rad($latitudeFrom);
        $latToRad = deg2rad($latitudeTo);

        // Implementasi formula Haversine
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFromRad) * cos($latToRad) * pow(sin($lonDelta / 2), 2)));

        $distance = $angle * $earthRadius; // Jarak dalam meter

        return $distance;
    }
}

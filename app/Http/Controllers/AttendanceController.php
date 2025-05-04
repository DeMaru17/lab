<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use RealRashid\SweetAlert\Facades\Alert; // Jika perlu untuk redirect
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{


    /**
     * Menampilkan halaman utama absensi (tombol check-in/out).
     */
    public function index()
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();

        // Cek apakah user sudah check-in hari ini
        $todaysAttendance = Attendance::where('user_id', $user->id)
            ->where('attendance_date', $today)
            ->first();

        // Tentukan status tombol (check-in atau check-out)
        $attendanceAction = 'check_in'; // Default
        $checkInTime = null;
        if ($todaysAttendance) {
            if (is_null($todaysAttendance->clock_out_time)) {
                // Sudah check-in, belum check-out
                $attendanceAction = 'check_out';
                $checkInTime = $todaysAttendance->clock_in_time;
            } else {
                // Sudah check-in dan check-out
                $attendanceAction = 'completed';
                $checkInTime = $todaysAttendance->clock_in_time;
            }
        }

        // Ambil daftar shift yang aktif dan sesuai gender user
        $shifts = Shift::where('is_active', true)
            ->whereIn('applicable_gender', ['Semua', $user->jenis_kelamin]) // Filter by gender
            ->orderBy('start_time')
            ->get(['id', 'name', 'start_time', 'end_time']); // Ambil kolom yg perlu

        return view('attendances.index', compact(
            'attendanceAction',
            'todaysAttendance',
            'checkInTime',
            'shifts'
        ));
    }

    /**
     * Menyimpan data check-in atau check-out.
     * Diharapkan menerima data via AJAX/Fetch.
     */
    public function store(Request $request)
    {
        // Validasi data yang diterima dari frontend
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'], // Validasi format Latitude
            'longitude' => ['required', 'numeric', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'], // Validasi format Longitude
            'selfie_data' => 'required|string', // Base64 data URI dari gambar selfie
            'shift_id' => 'required_if:action,check_in|exists:shifts,id', // Wajib jika check-in
            'action' => 'required|in:check_in,check_out', // Aksi yg dilakukan
        ]);

        $user = Auth::user();
        $today = Carbon::today()->toDateString();
        $now = Carbon::now();

        // --- Validasi Lokasi (Gunakan config()) ---
        $officeLat = config('attendance.office_latitude'); // <-- Ambil dari config
        $officeLng = config('attendance.office_longitude'); // <-- Ambil dari config
        $allowedRadius = config('attendance.allowed_radius_meters'); // <-- Ambil dari config

        $distance = $this->calculateDistance(
            $validated['latitude'], $validated['longitude'],
            $officeLat, $officeLng // <-- Gunakan variabel config
        );

        $locationStatus = 'Tidak Diketahui';
        if ($distance !== false) {
            $locationStatus = ($distance <= $allowedRadius) ? 'Dalam Radius' : 'Luar Radius';
        }

        if ($locationStatus === 'Luar Radius') {
            return response()->json([
                'success' => false,
                'message' => 'Anda berada di luar radius kantor yang diizinkan (' . round($distance) . ' meter).'
            ], 422);
        }
        // --- Akhir Validasi Lokasi ---

        // --- Simpan Foto Selfie ---
        $photoPath = null;
        try {
            // ... (Logika simpan foto seperti sebelumnya) ...
             if (preg_match('/^data:image\/(\w+);base64,/', $validated['selfie_data'], $type)) {
                 $imageData = substr($validated['selfie_data'], strpos($validated['selfie_data'], ',') + 1);
                 $type = strtolower($type[1]);
                 if (!in_array($type, ['jpg', 'jpeg', 'png'])) { throw new \Exception('Format gambar tidak valid.'); }
                 $imageData = base64_decode($imageData);
                 if ($imageData === false) { throw new \Exception('Gagal decode base64.'); }
                 $fileName = 'selfie_' . $user->id . '_' . date('Ymd_His') . '.' . $type;
                 Storage::disk('public')->put('attendance_photos/' . $fileName, $imageData);
                 $photoPath = 'attendance_photos/' . $fileName;
             } else { throw new \Exception('Data URI gambar tidak valid.'); }
        } catch (\Exception $e) {
            Log::error("Selfie upload failed for user {$user->id}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan foto selfie.'], 500);
        }
        // --- Akhir Simpan Foto Selfie ---

        // --- Simpan/Update Data Absensi ---
        DB::beginTransaction();
        try {
            if ($validated['action'] === 'check_in') {
                // ... (Logika create attendance seperti sebelumnya) ...
                 $existingAttendance = Attendance::where('user_id', $user->id)->where('attendance_date', $today)->first();
                 if ($existingAttendance) { DB::rollBack(); if($photoPath && Storage::disk('public')->exists($photoPath)) Storage::disk('public')->delete($photoPath); return response()->json(['success' => false, 'message' => 'Anda sudah check-in hari ini.'], 409); }
                 Attendance::create([
                     'user_id' => $user->id,
                     'shift_id' => $validated['shift_id'],
                     'attendance_date' => $today,
                     'clock_in_time' => $now,
                     'clock_in_latitude' => $validated['latitude'],
                     'clock_in_longitude' => $validated['longitude'],
                     'clock_in_photo_path' => $photoPath,
                     'clock_in_location_status' => $locationStatus,
                 ]);
                 $message = 'Check-in berhasil dicatat.';

            } elseif ($validated['action'] === 'check_out') {
                // ... (Logika update attendance seperti sebelumnya) ...
                 $attendance = Attendance::where('user_id', $user->id)->where('attendance_date', $today)->whereNull('clock_out_time')->first();
                 if (!$attendance) { DB::rollBack(); if($photoPath && Storage::disk('public')->exists($photoPath)) Storage::disk('public')->delete($photoPath); return response()->json(['success' => false, 'message' => 'Data check-in tidak ditemukan.'], 404); }
                 $attendance->update([
                     'clock_out_time' => $now,
                     'clock_out_latitude' => $validated['latitude'],
                     'clock_out_longitude' => $validated['longitude'],
                     'clock_out_photo_path' => $photoPath,
                     'clock_out_location_status' => $locationStatus,
                 ]);
                 $message = 'Check-out berhasil dicatat.';
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => $message]);

        } catch (\Exception $e) {
            DB::rollBack();
            if($photoPath && Storage::disk('public')->exists($photoPath)) Storage::disk('public')->delete($photoPath);
            Log::error("Error storing attendance for user {$user->id}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data absensi.'], 500);
        }
        // --- Akhir Simpan/Update Data Absensi ---
    }


    /**
     * Menghitung jarak antara dua titik koordinat (Haversine formula).
     *
     * @param float $lat1 Latitude titik 1
     * @param float $lon1 Longitude titik 1
     * @param float $lat2 Latitude titik 2
     * @param float $lon2 Longitude titik 2
     * @return float|false Jarak dalam meter atau false jika input tidak valid.
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float|false
    {
        if (!is_numeric($lat1) || !is_numeric($lon1) || !is_numeric($lat2) || !is_numeric($lon2)) {
            return false;
        }

        $earthRadius = 6371000; // Radius bumi dalam meter

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return $distance;
    }
}

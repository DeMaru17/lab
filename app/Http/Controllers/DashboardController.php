<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request; // Meskipun tidak digunakan secara langsung di method index, umum untuk controller
use Illuminate\Support\Facades\Auth; // Untuk mendapatkan pengguna yang sedang login
use Carbon\Carbon; // Untuk manipulasi tanggal dan waktu
use App\Models\Attendance;
use App\Models\Cuti;
use App\Models\CutiQuota;
use App\Models\JenisCuti;
use App\Models\Overtime;
use App\Models\AttendanceCorrection;
use App\Models\MonthlyTimesheet;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Holiday;
use Illuminate\Routing\Controller as BaseController; // Menggunakan BaseController Laravel

/**
 * Class DashboardController
 *
 * Mengelola tampilan halaman dashboard utama aplikasi.
 * Konten dashboard disesuaikan berdasarkan peran (role) dan jabatan pengguna yang login,
 * menampilkan ringkasan informasi yang relevan dan akses cepat ke fitur-fitur penting.
 *
 * @package App\Http\Controllers
 */
class DashboardController extends BaseController // Meng-extend BaseController Laravel
{
    /**
     * Membuat instance controller baru.
     * Menerapkan middleware 'auth' untuk memastikan hanya pengguna yang terotentikasi
     * yang dapat mengakses method-method di controller ini.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Menampilkan halaman dashboard aplikasi.
     * Method ini mengumpulkan berbagai data ringkasan yang relevan dengan peran pengguna
     * (Personil, Manajemen (Asisten/Manager), Admin) dan mengirimkannya ke view dashboard.
     * Termasuk data untuk tombol absensi dinamis, ringkasan cuti/lembur,
     * tugas persetujuan, statistik sistem, dan kalender mini.
     *
     * @return \Illuminate\View\View Mengembalikan view 'dashboard.index' dengan data yang dikumpulkan.
     */
    public function index()
    {
        /** @var \App\Models\User $user Pengguna yang sedang login. */
        $user = Auth::user();
        // Mendapatkan waktu saat ini sesuai timezone aplikasi
        $now = Carbon::now(config('app.timezone', 'Asia/Jakarta'));
        $todayDateString = $now->toDateString(); // Format YYYY-MM-DD untuk hari ini
        $data = []; // Array untuk menampung semua data yang akan dikirim ke view

        // --- Data Umum untuk Semua Pengguna ---
        $data['currentTime'] = $now; // Objek Carbon untuk waktu saat ini (bisa digunakan untuk jam digital)
        $data['todayDateString'] = $todayDateString; // String tanggal hari ini

        // --- Data untuk Tombol Absensi Dinamis ---
        // Menentukan teks tombol dan aksi berdasarkan status absensi pengguna hari ini.
        $data['attendanceButtonText'] = 'Absen Masuk Sekarang'; // Teks default
        $data['attendanceAction'] = 'check_in'; // Aksi default
        $data['todaysAttendanceRecord'] = null; // Untuk menyimpan record absensi hari ini jika ada

        // Cek hanya jika pengguna adalah 'personil' atau 'admin' yang dikonfigurasi bisa absen
        if ($user->role === 'personil' || ($user->role === 'admin' && config('hris.admin_can_attend', true))) {
            $todaysAttendance = Attendance::where('user_id', $user->id)
                                ->where('attendance_date', $todayDateString)
                                ->first();
            $data['todaysAttendanceRecord'] = $todaysAttendance; // Kirim record ini ke view untuk info tambahan

            if ($todaysAttendance) {
                if (is_null($todaysAttendance->clock_out_time)) {
                    // Jika sudah check-in tapi belum check-out
                    $data['attendanceAction'] = 'check_out';
                    $data['attendanceButtonText'] = 'Absen Keluar Sekarang';
                } else {
                    // Jika sudah check-in dan check-out
                    $data['attendanceAction'] = 'completed';
                    $data['attendanceButtonText'] = 'Lihat Absensi Hari Ini';
                }
            }
        }

        // --- Mengumpulkan Data Spesifik Berdasarkan Peran Pengguna ---

        // Untuk Pengguna dengan Peran 'personil'
        if ($user->role === 'personil') {
            // Ambil sisa kuota cuti tahunan
            $data['annualLeaveQuota'] = CutiQuota::where('user_id', $user->id)
                                        ->whereHas('jenisCuti', fn($q) => $q->where('nama_cuti', 'Cuti Tahunan'))
                                        ->value('durasi_cuti');
            // Ambil sisa kuota cuti khusus perjalanan dinas
            $data['specialPDLeaveQuota'] = CutiQuota::where('user_id', $user->id)
                                        ->whereHas('jenisCuti', fn($q) => $q->where('nama_cuti', 'Cuti Khusus Perjalanan Dinas'))
                                        ->value('durasi_cuti');
            // Hitung jumlah pengajuan cuti yang masih pending atau menunggu approval manager
            $data['pendingLeaveCount'] = Cuti::where('user_id', $user->id)->whereIn('status', ['pending', 'pending_manager_approval'])->count();
            // Hitung jumlah pengajuan lembur yang masih pending atau menunggu approval manager
            $data['pendingOvertimeCount'] = Overtime::where('user_id', $user->id)->whereIn('status', ['pending', 'pending_manager_approval'])->count();

            // Hitung total jam lembur yang sudah disetujui untuk bulan ini
            $overtimeMinutesThisMonth = Overtime::where('user_id', $user->id)
                                        ->where('status', 'approved')
                                        ->whereYear('tanggal_lembur', $now->year)
                                        ->whereMonth('tanggal_lembur', $now->month)
                                        ->sum('durasi_menit');
            $hours = floor($overtimeMinutesThisMonth / 60);
            $minutes = $overtimeMinutesThisMonth % 60;
            $data['approvedOvertimeThisMonthFormatted'] = sprintf('%d jam %02d menit', $hours, $minutes);

            // Ambil data timesheet bulanan terbaru milik pengguna
            $data['latestTimesheet'] = MonthlyTimesheet::where('user_id', $user->id)
                                        ->orderBy('period_end_date', 'desc')
                                        ->first();
            // Hitung jumlah pengajuan koreksi absensi yang masih pending
            $data['pendingAttendanceCorrectionCount'] = AttendanceCorrection::where('user_id', $user->id)->where('status', 'pending')->count();
        }
        // Untuk Pengguna dengan Peran 'manajemen'
        elseif ($user->role === 'manajemen') {
            // Logika spesifik untuk Asisten Manager
            if (in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator'])) {
                // Hitung jumlah pengajuan cuti yang menunggu persetujuan Asisten (sesuai scope jabatan)
                $data['pendingLeaveApprovalCount'] = Cuti::where('status', 'pending')
                    ->whereHas('user', function ($q_user) use ($user) {
                        if ($user->jabatan === 'asisten manager analis') { $q_user->whereIn('jabatan', ['analis', 'admin']);
                        } elseif ($user->jabatan === 'asisten manager preparator') { $q_user->whereIn('jabatan', ['preparator', 'mekanik', 'admin']); }
                    })->count();
                // Hitung jumlah pengajuan lembur yang menunggu persetujuan Asisten (sesuai scope jabatan)
                $data['pendingOvertimeApprovalCount'] = Overtime::where('status', 'pending')
                     ->whereHas('user', function ($q_user) use ($user) {
                        if ($user->jabatan === 'asisten manager analis') { $q_user->whereIn('jabatan', ['analis', 'admin']);
                        } elseif ($user->jabatan === 'asisten manager preparator') { $q_user->whereIn('jabatan', ['preparator', 'mekanik', 'admin']); }
                    })->count();
                // Hitung jumlah pengajuan koreksi absensi yang menunggu persetujuan Asisten (sesuai scope jabatan)
                $data['pendingCorrectionApprovalCount'] = AttendanceCorrection::where('status', 'pending')
                    ->whereHas('requester', function ($q_user) use ($user) { // Asumsi relasi 'requester' di model AttendanceCorrection
                         if ($user->jabatan === 'asisten manager analis') { $q_user->whereIn('jabatan', ['analis', 'admin']);
                        } elseif ($user->jabatan === 'asisten manager preparator') { $q_user->whereIn('jabatan', ['preparator', 'mekanik', 'admin']); }
                    })->count();
                // Hitung jumlah timesheet yang menunggu persetujuan Asisten (status 'generated' atau 'rejected')
                $data['pendingTimesheetApprovalCount'] = MonthlyTimesheet::whereIn('status', ['generated', 'rejected'])
                    ->whereHas('user', function ($q_user) use ($user) {
                        if ($user->jabatan === 'asisten manager analis') { $q_user->whereIn('jabatan', ['analis', 'admin']);
                        } elseif ($user->jabatan === 'asisten manager preparator') { $q_user->whereIn('jabatan', ['preparator', 'mekanik', 'admin']); }
                    })->count();
            }
            // Logika spesifik untuk Manager
            elseif ($user->jabatan === 'manager') {
                // Hitung jumlah pengajuan cuti yang menunggu persetujuan final Manager
                $data['pendingLeaveApprovalCount'] = Cuti::where('status', 'pending_manager_approval')->count(); // Sesuaikan status jika di DB adalah 'pending_manager'
                // Hitung jumlah pengajuan lembur yang menunggu persetujuan final Manager
                $data['pendingOvertimeApprovalCount'] = Overtime::where('status', 'pending_manager_approval')->count(); // Sesuaikan status
                // Hitung jumlah timesheet yang menunggu persetujuan final Manager
                $data['pendingTimesheetApprovalCount'] = MonthlyTimesheet::where('status', 'pending_manager')->count(); // Sesuaikan status
            }
            // Statistik Cepat untuk semua Manajemen
            // Jumlah karyawan unik yang hadir/terlambat/pulang cepat hari ini
            $data['employeesPresentToday'] = Attendance::where('attendance_date', $todayDateString)
                                                ->whereIn('attendance_status', ['Hadir', 'Terlambat', 'Pulang Cepat', 'Terlambat & Pulang Cepat'])
                                                ->distinct('user_id')->count();
            // Jumlah karyawan unik yang cuti/sakit/dinas luar hari ini
            $data['employeesOnLeaveOrDutyToday'] = Attendance::where('attendance_date', $todayDateString)
                                                ->whereIn('attendance_status', ['Cuti', 'Sakit', 'Dinas Luar'])
                                                ->distinct('user_id')->count();
        }
        // Untuk Pengguna dengan Peran 'admin'
        elseif ($user->role === 'admin') {
            // Statistik umum sistem
            $data['totalActiveUsers'] = User::where('id', '!=', $user->id)->count(); // Jumlah pengguna lain (asumsi tidak ada kolom is_active)
            $data['totalVendors'] = Vendor::count(); // Jumlah total vendor
            // Jumlah pengajuan baru (cuti & lembur) yang dibuat hari ini dan masih pending
            $data['newLeaveApplicationsToday'] = Cuti::whereDate('created_at', $todayDateString)->whereIn('status',['pending', 'pending_manager_approval'])->count();
            $data['newOvertimeApplicationsToday'] = Overtime::whereDate('created_at', $todayDateString)->whereIn('status',['pending', 'pending_manager_approval'])->count();
            // Statistik Cepat untuk Admin (bisa sama atau lebih detail dari manajemen)
            $data['employeesPresentToday'] = Attendance::where('attendance_date', $todayDateString)
                                                ->whereIn('attendance_status', ['Hadir', 'Terlambat', 'Pulang Cepat', 'Terlambat & Pulang Cepat'])
                                                ->distinct('user_id')->count();
            $data['employeesOnLeaveOrDutyToday'] = Attendance::where('attendance_date', $todayDateString)
                                                ->whereIn('attendance_status', ['Cuti', 'Sakit', 'Dinas Luar'])
                                                ->distinct('user_id')->count();
        }

        // --- Data untuk Kalender Mini ---
        $calendarDate = $now->copy(); // Gunakan $now yang sudah di-set zona waktunya
        $data['calendarYear'] = $calendarDate->year; // Tahun saat ini untuk kalender
        $data['calendarMonthNumeric'] = $calendarDate->month; // Angka bulan saat ini (1-12)
        $data['calendarMonthName'] = $calendarDate->translatedFormat('F'); // Nama bulan dilokalisasi (misal: Mei)
        $data['daysInMonth'] = $calendarDate->daysInMonth; // Jumlah hari dalam bulan ini
        $firstDayOfMonth = $calendarDate->copy()->startOfMonth(); // Objek Carbon untuk hari pertama bulan ini
        $data['firstDayOffset'] = $firstDayOfMonth->dayOfWeek; // Hari ke berapa dalam seminggu (0=Minggu, 1=Senin, ..., 6=Sabtu)
        $data['dayNames'] = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']; // Nama hari untuk header kalender

        // Mengambil data hari libur untuk bulan saat ini
        $holidaysThisMonth = Holiday::whereYear('tanggal', $data['calendarYear'])
                                ->whereMonth('tanggal', $calendarDate->month)
                                ->get();
        // Membuat array asosiatif dari tanggal libur untuk pengecekan cepat di view
        // Key: 'YYYY-MM-DD', Value: 'Nama Libur'
        $data['holidayDates'] = $holidaysThisMonth->mapWithKeys(function ($holiday) {
            return [Carbon::parse($holiday->tanggal)->format('Y-m-d') => $holiday->nama_libur];
        })->toArray();
        // --- Akhir Data Kalender Mini ---

        return view('dashboard.index', $data); // Mengirim semua data yang dikumpulkan ke view
    }
}

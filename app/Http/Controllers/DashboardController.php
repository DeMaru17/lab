<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\Cuti;
use App\Models\CutiQuota;
use App\Models\JenisCuti;
use App\Models\Overtime;
use App\Models\AttendanceCorrection;
use App\Models\MonthlyTimesheet;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Holiday; // Pastikan Holiday model di-import
use Illuminate\Routing\Controller as BaseController;

class DashboardController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        $now = Carbon::now(config('app.timezone', 'Asia/Jakarta'));
        $todayDateString = $now->toDateString(); // Y-m-d format for today
        $data = [];

        // Data Umum
        $data['currentTime'] = $now;
        $data['todayDateString'] = $todayDateString;

        // Data untuk Tombol Absensi Dinamis
        $data['attendanceButtonText'] = 'Absen Masuk Sekarang';
        $data['attendanceAction'] = 'check_in';
        $data['todaysAttendanceRecord'] = null;

        if ($user->role === 'personil' || ($user->role === 'admin' && config('hris.admin_can_attend', true))) {
            $todaysAttendance = Attendance::where('user_id', $user->id)
                ->where('attendance_date', $todayDateString)
                ->first();
            $data['todaysAttendanceRecord'] = $todaysAttendance;

            if ($todaysAttendance) {
                if (is_null($todaysAttendance->clock_out_time)) {
                    $data['attendanceAction'] = 'check_out';
                    $data['attendanceButtonText'] = 'Absen Keluar Sekarang';
                } else {
                    $data['attendanceAction'] = 'completed';
                    $data['attendanceButtonText'] = 'Lihat Absensi Hari Ini';
                }
            }
        }

        // Data Spesifik Berdasarkan Peran (Personil)
        if ($user->role === 'personil') {
            $data['annualLeaveQuota'] = CutiQuota::where('user_id', $user->id)
                ->whereHas('jenisCuti', fn($q) => $q->where('nama_cuti', 'Cuti Tahunan'))
                ->value('durasi_cuti');
            $data['specialPDLeaveQuota'] = CutiQuota::where('user_id', $user->id)
                ->whereHas('jenisCuti', fn($q) => $q->where('nama_cuti', 'Cuti Khusus Perjalanan Dinas'))
                ->value('durasi_cuti');
            $data['pendingLeaveCount'] = Cuti::where('user_id', $user->id)->whereIn('status', ['pending', 'pending_manager_approval'])->count();
            $data['pendingOvertimeCount'] = Overtime::where('user_id', $user->id)->whereIn('status', ['pending', 'pending_manager_approval'])->count();
            $overtimeMinutesThisMonth = Overtime::where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereYear('tanggal_lembur', $now->year)
                ->whereMonth('tanggal_lembur', $now->month)
                ->sum('durasi_menit');
            $hours = floor($overtimeMinutesThisMonth / 60);
            $minutes = $overtimeMinutesThisMonth % 60;
            $data['approvedOvertimeThisMonthFormatted'] = sprintf('%d jam %02d menit', $hours, $minutes);
            $data['latestTimesheet'] = MonthlyTimesheet::where('user_id', $user->id)
                ->orderBy('period_end_date', 'desc')
                ->first();
            $data['pendingAttendanceCorrectionCount'] = AttendanceCorrection::where('user_id', $user->id)->where('status', 'pending')->count();
        }
        // Data Spesifik Berdasarkan Peran (Manajemen)
        elseif ($user->role === 'manajemen') {
            if (in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator'])) {
                $data['pendingLeaveApprovalCount'] = Cuti::where('status', 'pending')
                    ->whereHas('user', function ($q_user) use ($user) {
                        if ($user->jabatan === 'asisten manager analis') {
                            $q_user->whereIn('jabatan', ['analis', 'admin']);
                        } elseif ($user->jabatan === 'asisten manager preparator') {
                            $q_user->whereIn('jabatan', ['preparator', 'mekanik', 'admin']);
                        }
                    })->count();
                $data['pendingOvertimeApprovalCount'] = Overtime::where('status', 'pending')
                    ->whereHas('user', function ($q_user) use ($user) {
                        if ($user->jabatan === 'asisten manager analis') {
                            $q_user->whereIn('jabatan', ['analis', 'admin']);
                        } elseif ($user->jabatan === 'asisten manager preparator') {
                            $q_user->whereIn('jabatan', ['preparator', 'mekanik', 'admin']);
                        }
                    })->count();
                $data['pendingCorrectionApprovalCount'] = AttendanceCorrection::where('status', 'pending')
                    ->whereHas('requester', function ($q_user) use ($user) {
                        if ($user->jabatan === 'asisten manager analis') {
                            $q_user->whereIn('jabatan', ['analis', 'admin']);
                        } elseif ($user->jabatan === 'asisten manager preparator') {
                            $q_user->whereIn('jabatan', ['preparator', 'mekanik', 'admin']);
                        }
                    })->count();
                $data['pendingTimesheetApprovalCount'] = MonthlyTimesheet::whereIn('status', ['generated', 'rejected'])
                    ->whereHas('user', function ($q_user) use ($user) {
                        if ($user->jabatan === 'asisten manager analis') {
                            $q_user->whereIn('jabatan', ['analis', 'admin']);
                        } elseif ($user->jabatan === 'asisten manager preparator') {
                            $q_user->whereIn('jabatan', ['preparator', 'mekanik', 'admin']);
                        }
                    })->count();
            } elseif ($user->jabatan === 'manager') {
                $data['pendingLeaveApprovalCount'] = Cuti::where('status', 'pending_manager')->count();
                $data['pendingOvertimeApprovalCount'] = Overtime::where('status', 'pending_manager_approval')->count();
                $data['pendingTimesheetApprovalCount'] = MonthlyTimesheet::where('status', 'pending_manager')->count();
            }
            // Statistik Cepat untuk Manajemen
            $data['employeesPresentToday'] = Attendance::where('attendance_date', $todayDateString)
                ->whereIn('attendance_status', ['Hadir', 'Terlambat', 'Pulang Cepat', 'Terlambat & Pulang Cepat'])
                ->distinct('user_id')->count(); // Hitung user unik yang hadir
            $data['employeesOnLeaveOrDutyToday'] = Attendance::where('attendance_date', $todayDateString)
                ->whereIn('attendance_status', ['Cuti', 'Sakit', 'Dinas Luar'])
                ->distinct('user_id')->count();
        }
        // Data Spesifik Berdasarkan Peran (Admin)
        elseif ($user->role === 'admin') {
            $data['totalActiveUsers'] = User::where('id', '!=', $user->id)->count(); // Asumsi tidak ada kolom is_active
            $data['totalVendors'] = Vendor::count();
            $data['newLeaveApplicationsToday'] = Cuti::whereDate('created_at', $todayDateString)->whereIn('status', ['pending', 'pending_manager_approval'])->count();
            $data['newOvertimeApplicationsToday'] = Overtime::whereDate('created_at', $todayDateString)->whereIn('status', ['pending', 'pending_manager_approval'])->count();
            // Statistik Cepat untuk Admin (bisa sama atau lebih detail dari manajemen)
            $data['employeesPresentToday'] = Attendance::where('attendance_date', $todayDateString)
                ->whereIn('attendance_status', ['Hadir', 'Terlambat', 'Pulang Cepat', 'Terlambat & Pulang Cepat'])
                ->distinct('user_id')->count();
            $data['employeesOnLeaveOrDutyToday'] = Attendance::where('attendance_date', $todayDateString)
                ->whereIn('attendance_status', ['Cuti', 'Sakit', 'Dinas Luar'])
                ->distinct('user_id')->count();
        }

        // --- Data untuk Kalender Mini ---
        $calendarDate = $now->copy();
        $data['calendarYear'] = $calendarDate->year;
        $data['calendarMonthNumeric'] = $calendarDate->month; // Untuk membuat tanggal di loop
        $data['calendarMonthName'] = $calendarDate->translatedFormat('F');
        $data['daysInMonth'] = $calendarDate->daysInMonth;
        $firstDayOfMonth = $calendarDate->copy()->startOfMonth();
        $data['firstDayOffset'] = $firstDayOfMonth->dayOfWeek; // 0 (Minggu) - 6 (Sabtu)
        $data['dayNames'] = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

        $holidaysThisMonth = Holiday::whereYear('tanggal', $data['calendarYear'])
            ->whereMonth('tanggal', $calendarDate->month)
            ->get();
        $data['holidayDates'] = $holidaysThisMonth->mapWithKeys(function ($holiday) {
            return [Carbon::parse($holiday->tanggal)->format('Y-m-d') => $holiday->nama_libur];
        })->toArray();
        // --- Akhir Data Kalender Mini ---

        return view('dashboard.index', $data);
    }
}

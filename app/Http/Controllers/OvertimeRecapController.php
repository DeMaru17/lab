<?php

namespace App\Http\Controllers;

use App\Models\Overtime;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Carbon\CarbonPeriod; // Untuk iterasi tanggal jika perlu
use RealRashid\SweetAlert\Facades\Alert;
use Maatwebsite\Excel\Facades\Excel; // <-- Import Facade Excel
use App\Exports\OvertimeRecapExport; // <-- Import Class Export

class OvertimeRecapController extends Controller
{
    /**
     * Menampilkan halaman rekap lembur dengan filter.
     */
    public function index(Request $request)
    {
        // Ambil data untuk filter dropdown (selalu diperlukan)
        $users = collect();
        $vendors = collect();
        if (in_array(Auth::user()->role, ['admin', 'manajemen'])) {
            $users = User::orderBy('name')->select('id', 'name')->get();
            $vendors = Vendor::orderBy('name')->select('id', 'name')->get();
        }

        // Ambil nilai filter dari request, gunakan default untuk tampilan form awal
        $selectedUserId = $request->input('user_id');
        $selectedVendorId = $request->input('vendor_id');
        $selectedStatus = $request->input('status', 'approved'); // Default status tetap approved
        // Default tanggal untuk tampilan form, tapi query hanya jalan jika ada di request
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        // Inisialisasi hasil rekap sebagai collection kosong
        $recapData = collect();
        $hasFiltered = false; // Flag untuk menandai apakah filter sudah diterapkan

        // --- PROSES DATA HANYA JIKA FILTER TANGGAL ADA ---
        if ($request->has('start_date') && $request->has('end_date')) {
            $hasFiltered = true; // Tandai bahwa filter sudah dijalankan

            // Query dasar lembur
            $overtimeQuery = Overtime::query()
                ->with(['user:id,name,vendor_id', 'user.vendor:id,name'])
                ->whereBetween('tanggal_lembur', [$startDate, $endDate]);

            // Filter status
            if ($selectedStatus) {
                $overtimeQuery->where('status', $selectedStatus);
            }

            // Filter role & user/vendor
            if (Auth::user()->role === 'personil') {
                $overtimeQuery->where('user_id', Auth::id());
            } else {
                if ($selectedUserId) {
                    $overtimeQuery->where('user_id', $selectedUserId);
                }
                if ($selectedVendorId) {
                    // Handle jika 'Internal Karyawan' dipilih (vendor_id is null)
                    if ($selectedVendorId === 'is_null') {
                        $overtimeQuery->whereHas('user', function ($q) {
                            $q->whereNull('vendor_id');
                        });
                    } else {
                        $overtimeQuery->whereHas('user', function ($q) use ($selectedVendorId) {
                            $q->where('vendor_id', $selectedVendorId);
                        });
                    }
                }
            }

            $overtimes = $overtimeQuery->orderBy('user_id')->orderBy('tanggal_lembur')->get();

            // Proses Data untuk Rekap Berdasarkan Periode Vendor
            $userTotals = []; // Tidak digunakan lagi untuk view ini, tapi bisa untuk export
            $overtimesByUser = $overtimes->groupBy('user_id');

            foreach ($overtimesByUser as $userId => $userOvertimes) {
                $user = $userOvertimes->first()->user;
                if (!$user) continue;

                $userRecap = [
                    'user' => $user,
                    'details' => collect(), // Inisialisasi detail
                    'periods' => [],
                ];

                // Tentukan periode vendor yang relevan
                list($periodStart, $periodEnd) = $this->getVendorPeriod($user, Carbon::parse($startDate));
                $overtimesInPeriod = $userOvertimes->filter(fn($ot) => Carbon::parse($ot->tanggal_lembur)->betweenIncluded($periodStart, $periodEnd));
                $totalMinutesInPeriod = $overtimesInPeriod->sum('durasi_menit');

                $userRecap['details'] = $userRecap['details']->merge($overtimesInPeriod);
                $userRecap['periods'][] = [
                    'start' => $periodStart->format('d/m/Y'),
                    'end' => $periodEnd->format('d/m/Y'),
                    'total_minutes' => $totalMinutesInPeriod,
                ];

                // Cek periode berikutnya jika perlu (untuk CSI)
                if (($user->vendor->name ?? null) === 'PT Cakra Satya Internusa' && Carbon::parse($endDate)->greaterThan($periodEnd)) {
                    list($nextPeriodStart, $nextPeriodEnd) = $this->getVendorPeriod($user, $periodEnd->copy()->addDay());
                    $overtimesInNextPeriod = $userOvertimes->filter(fn($ot) => Carbon::parse($ot->tanggal_lembur)->betweenIncluded($nextPeriodStart, $nextPeriodEnd));
                    if ($overtimesInNextPeriod->isNotEmpty() || $nextPeriodStart->betweenIncluded($startDate, $endDate)) {
                        $totalMinutesInNextPeriod = $overtimesInNextPeriod->sum('durasi_menit');
                        $userRecap['details'] = $userRecap['details']->merge($overtimesInNextPeriod);
                        $userRecap['periods'][] = [
                            'start' => $nextPeriodStart->format('d/m/Y'),
                            'end' => $nextPeriodEnd->format('d/m/Y'),
                            'total_minutes' => $totalMinutesInNextPeriod,
                        ];
                    }
                }
                $recapData->push($userRecap);
            }
        }
        // --- AKHIR PROSES DATA ---

        return view('overtimes.recap.index', compact(
            'users',
            'vendors',
            'selectedUserId',
            'selectedVendorId',
            'selectedStatus',
            'startDate',
            'endDate',
            'recapData', // Akan kosong jika filter belum dijalankan
            'hasFiltered' // Kirim flag ini ke view
        ));
    }

    /**
     * Menangani ekspor data rekap ke Excel.
     * (Akan diimplementasikan nanti menggunakan Maatwebsite/Excel)
     */
    public function export(Request $request)
    {
        // --- Ambil Filter (Sama seperti index) ---
        $selectedUserId = $request->input('user_id');
        $selectedVendorId = $request->input('vendor_id');
        $selectedStatus = $request->input('status', 'approved');
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        // --- Query Data (Sama seperti index) ---
        $overtimeQuery = Overtime::query()
            ->with(['user:id,name,jabatan,vendor_id', 'user.vendor:id,name']) // Muat relasi yg dibutuhkan
            ->whereBetween('tanggal_lembur', [$startDate, $endDate]);
        if ($selectedStatus) {
            $overtimeQuery->where('status', $selectedStatus);
        }
        if (Auth::user()->role === 'personil') {
            $overtimeQuery->where('user_id', Auth::id());
        } else {
            if ($selectedUserId) {
                $overtimeQuery->where('user_id', $selectedUserId);
            }
            if ($selectedVendorId) {
                if ($selectedVendorId === 'is_null') {
                    $overtimeQuery->whereHas('user', fn($q) => $q->whereNull('vendor_id'));
                } else {
                    $overtimeQuery->whereHas('user', fn($q) => $q->where('vendor_id', $selectedVendorId));
                }
            }
        }
        $overtimes = $overtimeQuery->orderBy('user_id')->orderBy('tanggal_lembur')->get();

        // --- Proses Data (Sama seperti index) ---
        $recapData = collect();
        $overtimesByUser = $overtimes->groupBy('user_id');
        foreach ($overtimesByUser as $userId => $userOvertimes) {
            $user = $userOvertimes->first()->user;
            if (!$user) continue;
            $userRecap = ['user' => $user, 'details' => collect(), 'periods' => []];
            list($periodStart, $periodEnd) = $this->getVendorPeriod($user, Carbon::parse($startDate));
            $overtimesInPeriod = $userOvertimes->filter(fn($ot) => Carbon::parse($ot->tanggal_lembur)->betweenIncluded($periodStart, $periodEnd));
            $totalMinutesInPeriod = $overtimesInPeriod->sum('durasi_menit');
            $userRecap['details'] = $userRecap['details']->merge($overtimesInPeriod);
            $userRecap['periods'][] = ['start' => $periodStart->format('d/m/Y'), 'end' => $periodEnd->format('d/m/Y'), 'total_minutes' => $totalMinutesInPeriod];
            if (($user->vendor->name ?? null) === 'PT Cakra Satya Internusa' && Carbon::parse($endDate)->greaterThan($periodEnd)) {
                list($nextPeriodStart, $nextPeriodEnd) = $this->getVendorPeriod($user, $periodEnd->copy()->addDay());
                $overtimesInNextPeriod = $userOvertimes->filter(fn($ot) => Carbon::parse($ot->tanggal_lembur)->betweenIncluded($nextPeriodStart, $nextPeriodEnd));
                if ($overtimesInNextPeriod->isNotEmpty() || $nextPeriodStart->betweenIncluded($startDate, $endDate)) {
                    $totalMinutesInNextPeriod = $overtimesInNextPeriod->sum('durasi_menit');
                    $userRecap['details'] = $userRecap['details']->merge($overtimesInNextPeriod);
                    $userRecap['periods'][] = ['start' => $nextPeriodStart->format('d/m/Y'), 'end' => $nextPeriodEnd->format('d/m/Y'), 'total_minutes' => $totalMinutesInNextPeriod];
                }
            }
            $recapData->push($userRecap);
        }
        // --- Akhir Proses Data ---

        // --- Buat Nama File Excel ---
        $fileName = 'rekap_lembur_' . Carbon::parse($startDate)->format('Ymd') . '-' . Carbon::parse($endDate)->format('Ymd') . '.xlsx';

        // --- Trigger Download Excel ---
        // Kirim request (filter) dan data yang sudah diproses ke class Export
        return Excel::download(new OvertimeRecapExport($request, $recapData), $fileName);
    }


    /**
     * Helper untuk menentukan periode vendor berdasarkan user dan tanggal acuan.
     *
     * @param User $user
     * @param Carbon $targetDate
     * @return array [Carbon $periodStartDate, Carbon $periodEndDate]
     */
    private function getVendorPeriod(User $user, Carbon $targetDate): array
    {
        $vendorName = $user->vendor->name ?? null; // Pastikan relasi vendor di-load

        if ($vendorName === 'PT Cakra Satya Internusa') { // Sesuaikan nama vendor
            // Periode: Tgl 16 bulan sebelumnya s/d Tgl 15 bulan ini
            if ($targetDate->day >= 16) {
                $periodStartDate = $targetDate->copy()->day(16);
                $periodEndDate = $targetDate->copy()->addMonthNoOverflow()->day(15);
            } else {
                $periodStartDate = $targetDate->copy()->subMonthNoOverflow()->day(16);
                $periodEndDate = $targetDate->copy()->day(15);
            }
        } else {
            // Default (atau vendor lain): Tgl 1 s/d akhir bulan
            $periodStartDate = $targetDate->copy()->startOfMonth();
            $periodEndDate = $targetDate->copy()->endOfMonth();
        }
        return [$periodStartDate, $periodEndDate];
    }
}

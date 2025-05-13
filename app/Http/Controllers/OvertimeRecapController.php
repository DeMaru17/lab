<?php

namespace App\Http\Controllers;

use App\Models\Overtime;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Carbon\CarbonPeriod; // Meskipun tidak digunakan secara langsung di sini, mungkin berguna untuk logika tanggal kompleks di masa depan
// use RealRashid\SweetAlert\Facades\Alert; // Tidak digunakan di controller ini
use Maatwebsite\Excel\Facades\Excel; // Facade untuk package Maatwebsite/Excel
use App\Exports\OvertimeRecapExport; // Class Export kustom Anda
use Illuminate\Support\Facades\Log; // Untuk logging error saat ekspor
use RealRashid\SweetAlert\Facades\Alert; // Untuk menampilkan alert menggunakan SweetAlert

/**
 * Class OvertimeRecapController
 *
 * Mengelola logika untuk menampilkan dan mengekspor rekapitulasi data lembur karyawan.
 * Rekapitulasi ini dapat difilter berdasarkan pengguna, vendor, status, dan rentang tanggal.
 * Data rekap juga diproses untuk menampilkan total lembur per periode vendor.
 *
 * @package App\Http\Controllers
 */
class OvertimeRecapController extends Controller
{
    /**
     * Menampilkan halaman rekapitulasi lembur dengan opsi filter.
     * Data lembur akan diproses dan dikelompokkan per pengguna,
     * serta dihitung totalnya per periode vendor yang relevan.
     *
     * @param  \Illuminate\Http\Request  $request Data request yang mungkin berisi parameter filter.
     * @return \Illuminate\View\View Mengembalikan view 'overtimes.recap.index' dengan data yang diperlukan.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $loggedInUser Pengguna yang sedang login. */
        $loggedInUser = Auth::user(); // Ambil pengguna yang sedang login

        // Menyiapkan data untuk dropdown filter (hanya untuk Admin/Manajemen)
        $users = collect(); // Default koleksi kosong
        $vendors = collect(); // Default koleksi kosong
        if (in_array($loggedInUser->role, ['admin', 'manajemen'])) {
            $users = User::orderBy('name')->select('id', 'name')->get();
            $vendors = Vendor::orderBy('name')->select('id', 'name')->get();
        }

        // Mengambil nilai filter dari request.
        // Default status adalah 'approved' jika tidak ada filter status yang dipilih.
        // Default rentang tanggal adalah awal hingga akhir bulan saat ini.
        $selectedUserId = $request->input('user_id');
        $selectedVendorId = $request->input('vendor_id');
        $selectedStatus = $request->input('status', 'approved'); // Default ke 'approved'
        $startDate = $request->input('start_date', Carbon::now(config('app.timezone', 'Asia/Jakarta'))->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now(config('app.timezone', 'Asia/Jakarta'))->endOfMonth()->toDateString());

        // Inisialisasi data rekap sebagai koleksi kosong
        $recapData = collect();
        $hasFiltered = false; // Flag untuk menandai apakah filter sudah diterapkan dan query dijalankan

        // --- PROSES PENGAMBILAN DAN PENGOLAHAN DATA HANYA JIKA FILTER TANGGAL ADA DI REQUEST ---
        // Ini untuk mencegah query berat berjalan saat halaman pertama kali dibuka tanpa filter.
        if ($request->has('start_date') && $request->has('end_date')) {
            $hasFiltered = true; // Menandai bahwa filter telah dijalankan

            // Query dasar untuk mengambil data lembur
            $overtimeQuery = Overtime::query()
                ->with(['user:id,name,jabatan,vendor_id', 'user.vendor:id,name']) // Eager load data user dan vendor terkait
                ->whereBetween('tanggal_lembur', [$startDate, $endDate]); // Filter berdasarkan rentang tanggal

            // Menerapkan filter status jika dipilih
            if ($selectedStatus) {
                $overtimeQuery->where('status', $selectedStatus);
            }

            // Menerapkan filter berdasarkan peran pengguna
            if ($loggedInUser->role === 'personil') {
                // Personil hanya bisa melihat rekap lembur miliknya sendiri
                $overtimeQuery->where('user_id', $loggedInUser->id);
            } else { // Untuk Admin dan Manajemen
                if ($selectedUserId) {
                    $overtimeQuery->where('user_id', $selectedUserId);
                }
                if ($selectedVendorId) {
                    if ($selectedVendorId === 'is_null') { // Handle untuk karyawan internal (tanpa vendor)
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

            // Mengambil semua data lembur yang cocok dengan filter, diurutkan berdasarkan user dan tanggal
            $overtimes = $overtimeQuery->orderBy('user_id')->orderBy('tanggal_lembur')->get();

            // Mengelompokkan data lembur per pengguna untuk diproses lebih lanjut
            $overtimesByUser = $overtimes->groupBy('user_id');

            // Memproses data lembur untuk setiap pengguna
            foreach ($overtimesByUser as $userId => $userOvertimes) {
                /** @var \App\Models\User $userModel Pengguna pemilik data lembur. */
                $userModel = $userOvertimes->first()->user;
                if (!$userModel) continue; // Lewati jika data user tidak ditemukan (seharusnya tidak terjadi)

                $userRecap = [
                    'user' => $userModel, // Menyimpan objek User
                    'details' => collect(), // Koleksi untuk menyimpan detail lembur yang relevan per periode
                    'periods' => [],        // Array untuk menyimpan ringkasan per periode vendor
                ];

                // Tentukan periode vendor pertama yang relevan berdasarkan tanggal mulai filter
                list($periodStart, $periodEnd) = $this->getVendorPeriod($userModel, Carbon::parse($startDate, config('app.timezone', 'Asia/Jakarta')));

                // Filter lembur yang masuk dalam periode vendor pertama ini
                $overtimesInPeriod = $userOvertimes->filter(
                    fn($ot) => Carbon::parse($ot->tanggal_lembur, config('app.timezone', 'Asia/Jakarta'))->betweenIncluded($periodStart, $periodEnd)
                );
                $totalMinutesInPeriod = $overtimesInPeriod->sum('durasi_menit');

                // Tambahkan detail lembur dan ringkasan periode pertama
                $userRecap['details'] = $userRecap['details']->merge($overtimesInPeriod);
                $userRecap['periods'][] = [
                    'start' => $periodStart->format('d/m/Y'),
                    'end' => $periodEnd->format('d/m/Y'),
                    'total_minutes' => $totalMinutesInPeriod,
                ];

                // Logika khusus untuk vendor 'PT Cakra Satya Internusa' (CSI)
                // Jika rentang filter lebih panjang dari satu periode CSI, cek periode berikutnya.
                if (($userModel->vendor?->name ?? null) === 'PT Cakra Satya Internusa' && Carbon::parse($endDate, config('app.timezone', 'Asia/Jakarta'))->greaterThan($periodEnd)) {
                    // Tentukan periode vendor berikutnya
                    list($nextPeriodStart, $nextPeriodEnd) = $this->getVendorPeriod($userModel, $periodEnd->copy()->addDay());

                    // Filter lembur yang masuk dalam periode vendor berikutnya
                    $overtimesInNextPeriod = $userOvertimes->filter(
                        fn($ot) => Carbon::parse($ot->tanggal_lembur, config('app.timezone', 'Asia/Jakarta'))->betweenIncluded($nextPeriodStart, $nextPeriodEnd)
                    );

                    // Tambahkan periode berikutnya jika ada lembur di dalamnya ATAU jika periode berikutnya masih dalam rentang filter
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
                // Kumpulkan data rekap per pengguna
                $recapData->push($userRecap);
            }
        }
        // --- Akhir Proses Pengambilan dan Pengolahan Data ---

        return view('overtimes.recap.index', compact(
            'users',
            'vendors',
            'selectedUserId',
            'selectedVendorId',
            'selectedStatus',
            'startDate',
            'endDate',
            'recapData', // Akan kosong jika filter tanggal belum dijalankan
            'hasFiltered' // Flag untuk view, menandakan apakah query sudah dijalankan
        ));
    }

    /**
     * Menangani permintaan ekspor data rekapitulasi lembur ke format Excel.
     * Menggunakan class OvertimeRecapExport untuk memformat data.
     * Logika pengambilan data dan filter mirip dengan method index().
     *
     * @param  \Illuminate\Http\Request  $request Data request yang berisi parameter filter.
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse File Excel atau redirect jika gagal.
     */
    public function export(Request $request)
    {
        // Otorisasi: Siapa yang boleh melakukan ekspor? Biasanya Admin atau Manajemen.
        // Anda bisa menambahkan $this->authorize('exportRecap', Overtime::class); jika ada policy-nya.
        if (!in_array(Auth::user()->role, ['admin', 'manajemen'])) {
            Alert::error('Akses Ditolak', 'Anda tidak memiliki izin untuk melakukan ekspor data ini.');
            return redirect()->back();
        }

        // --- Mengambil Parameter Filter (Sama seperti di method index) ---
        $selectedUserId = $request->input('user_id');
        $selectedVendorId = $request->input('vendor_id');
        $selectedStatus = $request->input('status', 'approved'); // Default ke 'approved'
        $startDate = $request->input('start_date', Carbon::now(config('app.timezone', 'Asia/Jakarta'))->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now(config('app.timezone', 'Asia/Jakarta'))->endOfMonth()->toDateString());

        // --- Query Data Lembur (Sama seperti di method index) ---
        $overtimeQuery = Overtime::query()
            ->with(['user:id,name,jabatan,vendor_id', 'user.vendor:id,name'])
            ->whereBetween('tanggal_lembur', [$startDate, $endDate]);
        if ($selectedStatus) {
            $overtimeQuery->where('status', $selectedStatus);
        }
        // Filter role untuk export (jika personil export, hanya data dia)
        if (Auth::user()->role === 'personil') {
            $overtimeQuery->where('user_id', Auth::id());
        } else { // Admin & Manajemen bisa filter
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

        // Jika tidak ada data yang cocok dengan filter untuk diekspor
        if ($overtimes->isEmpty()) {
            Alert::warning('Tidak Ada Data', 'Tidak ada data lembur yang cocok dengan filter untuk diekspor.');
            return redirect()->back();
        }

        // --- Proses Data untuk Rekap (Sama seperti di method index) ---
        // Ini penting agar data yang dikirim ke class Export sudah dalam format yang siap digunakan.
        $recapData = collect();
        $overtimesByUser = $overtimes->groupBy('user_id');
        foreach ($overtimesByUser as $userId => $userOvertimes) {
            /** @var \App\Models\User $userModel */
            $userModel = $userOvertimes->first()->user;
            if (!$userModel) continue;

            $userRecap = [
                'user' => $userModel,
                'details' => collect(), // Akan diisi dengan semua detail lembur user dalam rentang filter
                'periods' => [],
            ];

            // Tentukan periode vendor pertama yang relevan
            list($periodStart, $periodEnd) = $this->getVendorPeriod($userModel, Carbon::parse($startDate, config('app.timezone', 'Asia/Jakarta')));
            // Ambil semua lembur user dalam rentang filter yang masuk periode ini
            $overtimesInPeriod = $userOvertimes->filter(
                fn($ot) => Carbon::parse($ot->tanggal_lembur, config('app.timezone', 'Asia/Jakarta'))->betweenIncluded($periodStart, $periodEnd)
            );
            $totalMinutesInPeriod = $overtimesInPeriod->sum('durasi_menit');
            // Semua detail lembur user dalam rentang filter akan dimasukkan ke 'details'
            // $userRecap['details'] = $userRecap['details']->merge($overtimesInPeriod); // Ini mungkin tidak perlu jika Class Export menghandle $userOvertimes langsung
            $userRecap['details'] = $userOvertimes; // Kirim semua lembur user, biarkan Class Export yang memproses detailnya jika perlu
            $userRecap['periods'][] = [
                'start' => $periodStart->format('d/m/Y'),
                'end' => $periodEnd->format('d/m/Y'),
                'total_minutes' => $totalMinutesInPeriod,
            ];

            // Cek periode berikutnya jika vendor CSI dan rentang filter mencakup lebih dari satu periode CSI
            if (($userModel->vendor?->name ?? null) === 'PT Cakra Satya Internusa' && Carbon::parse($endDate, config('app.timezone', 'Asia/Jakarta'))->greaterThan($periodEnd)) {
                list($nextPeriodStart, $nextPeriodEnd) = $this->getVendorPeriod($userModel, $periodEnd->copy()->addDay());
                $overtimesInNextPeriod = $userOvertimes->filter(
                    fn($ot) => Carbon::parse($ot->tanggal_lembur, config('app.timezone', 'Asia/Jakarta'))->betweenIncluded($nextPeriodStart, $nextPeriodEnd)
                );
                // Hanya tambahkan periode berikutnya jika ada lembur di dalamnya ATAU jika periode berikutnya dimulai dalam rentang filter
                if ($overtimesInNextPeriod->isNotEmpty() || $nextPeriodStart->betweenIncluded($startDate, $endDate)) {
                    $totalMinutesInNextPeriod = $overtimesInNextPeriod->sum('durasi_menit');
                    // $userRecap['details'] = $userRecap['details']->merge($overtimesInNextPeriod); // Tidak perlu merge lagi jika sudah kirim semua
                    $userRecap['periods'][] = [
                        'start' => $nextPeriodStart->format('d/m/Y'),
                        'end' => $nextPeriodEnd->format('d/m/Y'),
                        'total_minutes' => $totalMinutesInNextPeriod,
                    ];
                }
            }
            $recapData->push($userRecap); // Kumpulkan data rekap per pengguna
        }
        // --- Akhir Proses Data untuk Rekap ---

        // Membuat nama file Excel yang dinamis berdasarkan rentang tanggal filter
        $fileName = 'rekap_lembur_' . Carbon::parse($startDate)->format('Ymd') . '_sd_' . Carbon::parse($endDate)->format('Ymd') . '.xlsx';

        // Menggunakan Maatwebsite/Excel untuk men-trigger download file Excel.
        // Class OvertimeRecapExport bertanggung jawab untuk memformat data ke dalam sheet Excel.
        // Meneruskan $request (untuk filter) dan $recapData (data yang sudah diproses) ke class Export.
        try {
            return Excel::download(new OvertimeRecapExport($request, $recapData), $fileName);
        } catch (\Exception $e) {
            Log::error("Error saat mengekspor rekap lembur ke Excel: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Ekspor', 'Terjadi kesalahan saat membuat file Excel. Silakan coba lagi.');
            return redirect()->back();
        }
    }


    /**
     * Helper method untuk menentukan periode vendor (tanggal mulai dan selesai)
     * berdasarkan pengguna dan tanggal acuan. Ini penting untuk perhitungan
     * total lembur per periode cut-off vendor, terutama untuk vendor seperti CSI.
     *
     * @param  \App\Models\User  $user Instance pengguna.
     * @param  \Carbon\Carbon  $targetDate Objek Carbon sebagai tanggal acuan untuk menentukan periode.
     * @return array<int, \Carbon\Carbon> Array berisi dua objek Carbon: [0] => tanggal mulai periode, [1] => tanggal selesai periode.
     */
    private function getVendorPeriod(User $user, Carbon $targetDate): array
    {
        $vendorName = $user->vendor?->name ?? null; // Mengambil nama vendor dari relasi User (jika ada)
                                                 // Pastikan relasi 'vendor' sudah di-load pada objek $user untuk efisiensi.

        // Logika khusus untuk vendor 'PT Cakra Satya Internusa' (CSI)
        if ($vendorName === 'PT Cakra Satya Internusa') { // Sesuaikan nama vendor ini jika berbeda di database Anda
            // Periode CSI adalah dari tanggal 16 hingga tanggal 15 bulan berikutnya.
            // Penentuan periode ini didasarkan pada $targetDate.
            if ($targetDate->day >= 16) {
                // Jika tanggal di $targetDate adalah 16 atau setelahnya, periode dimulai tanggal 16 bulan $targetDate
                // dan berakhir tanggal 15 bulan berikutnya.
                $periodStartDate = $targetDate->copy()->day(16);
                $periodEndDate = $targetDate->copy()->addMonthNoOverflow()->day(15); // addMonthNoOverflow mencegah error jika bulan target Februari
            } else {
                // Jika tanggal di $targetDate adalah sebelum tanggal 16, periode dimulai tanggal 16 bulan SEBELUMNYA
                // dan berakhir tanggal 15 bulan $targetDate.
                $periodStartDate = $targetDate->copy()->subMonthNoOverflow()->day(16);
                $periodEndDate = $targetDate->copy()->day(15);
            }
        } else {
            // Untuk vendor lain atau pengguna internal (tanpa vendor), periode default adalah awal hingga akhir bulan dari $targetDate.
            $periodStartDate = $targetDate->copy()->startOfMonth();
            $periodEndDate = $targetDate->copy()->endOfMonth();
        }
        return [$periodStartDate, $periodEndDate];
    }
}

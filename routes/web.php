<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PerjalananDinasController;
use App\Http\Controllers\CutiQuotaController;
use App\Http\Controllers\CutiController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\OvertimeRecapController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\MonthlyTimesheetController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// == Public Routes ==
Route::get('/', function () {
    return view('auth.login');
});
Route::get('login', [LoginController::class, 'index'])->name('login');
Route::post('action-login', [LoginController::class, 'actionLogin'])->name('action-login');


// == Authenticated Routes ==
Route::middleware('auth')->group(function () {

    // Logout
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Dashboard (Bisa diakses semua user terotentikasi)
    Route::resource('dashboard', DashboardController::class)->only(['index']);

    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit'); // Menampilkan form edit profil
        Route::match(['put', 'patch'], '/', [ProfileController::class, 'update'])->name('update'); // Menyimpan perubahan profil
    });

    // Perjalanan Dinas (Akses detail diatur Policy)
    Route::resource('perjalanan-dinas', PerjalananDinasController::class)
        ->parameters(['perjalanan-dinas' => 'perjalananDina']);

    // Cuti Quota (Index bisa dilihat semua, Update hanya Admin)
    Route::prefix('cuti-quota')->name('cuti-quota.')->group(function () {
        Route::get('/', [CutiQuotaController::class, 'index'])->name('index');
        // Lindungi route update dengan middleware role:admin
        Route::match(['put', 'patch'], '/{id}', [CutiQuotaController::class, 'update'])
            ->middleware('role:admin') // <-- Middleware Admin
            ->name('update');
    });

    // Cuti (Akses detail diatur Policy)
    Route::prefix('cuti')->name('cuti.')->group(function () {
        Route::get('/', [CutiController::class, 'index'])->name('index');
        // Lindungi create hanya untuk admin & personil (jika policy belum diterapkan)
        Route::get('/create', [CutiController::class, 'create'])->middleware('role:admin,personil')->name('create');
        Route::post('/', [CutiController::class, 'store'])->middleware('role:admin,personil')->name('store');
        Route::get('/get-quota-ajax', [CutiController::class, 'getQuota'])->name('getQuota.ajax'); // AJAX tidak perlu role spesifik?
        Route::post('/{cuti}/cancel', [CutiController::class, 'cancel'])->name('cancel'); // Otorisasi di controller/policy
        Route::get('/{cuti}/edit', [CutiController::class, 'edit'])->name('edit');         // Otorisasi di controller/policy
        Route::match(['put', 'patch'], '/{cuti}', [CutiController::class, 'update'])->name('update'); // Otorisasi di controller/policy
        // Lindungi download PDF hanya untuk admin
        Route::get('/{cuti}/pdf', [CutiController::class, 'downloadPdf'])->middleware('role:admin')->name('pdf');
    });

    // Lembur (Overtime) (Akses detail diatur Policy)
    Route::prefix('overtimes')->name('overtimes.')->group(function () {
        Route::get('/', [OvertimeController::class, 'index'])->name('index');
        // Lindungi create hanya untuk admin & personil
        Route::get('/create', [OvertimeController::class, 'create'])->middleware('role:admin,personil')->name('create');
        Route::post('/', [OvertimeController::class, 'store'])->middleware('role:admin,personil')->name('store');
        Route::get('/{overtime}/edit', [OvertimeController::class, 'edit'])->name('edit'); // Otorisasi di controller/policy
        Route::match(['put', 'patch'], '/{overtime}', [OvertimeController::class, 'update'])->name('update'); // Otorisasi di controller/policy
        Route::delete('/{overtime}', [OvertimeController::class, 'destroy'])->middleware('role:admin')->name('destroy'); // Hapus hanya admin
        Route::post('/{overtime}/cancel', [OvertimeController::class, 'cancel'])->name('cancel'); // Otorisasi di controller/policy
        // Lindungi download PDF hanya untuk admin
        Route::get('/{overtime}/pdf', [OvertimeController::class, 'downloadOvertimePdf'])->middleware('role:admin')->name('pdf');
        // Lindungi bulk download PDF hanya untuk admin
        Route::post('/bulk-pdf', [OvertimeController::class, 'bulkDownloadPdf'])->middleware('role:admin')->name('bulk.pdf');
    });


    // --- GRUP KHUSUS ADMIN ---
    Route::middleware(['role:admin'])->group(function () {
        // Manajemen User (Personil)
        Route::resource('personil', UserController::class);
        // Manajemen Vendor
        Route::resource('vendors', VendorController::class);

        Route::resource('holidays', HolidayController::class);
    });
    // --- AKHIR GRUP ADMIN ---


    // --- GRUP KHUSUS MANAJEMEN ---
    Route::middleware(['role:manajemen'])->group(function () {
        // Cuti Approval
        Route::prefix('cuti-approval')->name('cuti.approval.')->group(function () {
            Route::get('/asisten', [CutiController::class, 'listForAsisten'])->name('asisten.list');
            Route::patch('/asisten/{cuti}/approve', [CutiController::class, 'approveAsisten'])->name('asisten.approve');
            Route::get('/manager', [CutiController::class, 'listForManager'])->name('manager.list');
            Route::patch('/manager/{cuti}/approve', [CutiController::class, 'approveManager'])->name('manager.approve');
            Route::post('/{cuti}/reject', [CutiController::class, 'reject'])->name('reject');
        });

        // Lembur Approval
        Route::prefix('overtime-approval')->name('overtimes.approval.')->group(function () {
            Route::get('/asisten', [OvertimeController::class, 'listForAsisten'])->name('asisten.list');
            Route::patch('/asisten/{overtime}/approve', [OvertimeController::class, 'approveAsisten'])->name('asisten.approve');
            Route::get('/manager', [OvertimeController::class, 'listForManager'])->name('manager.list');
            Route::patch('/manager/{overtime}/approve', [OvertimeController::class, 'approveManager'])->name('manager.approve');
            Route::post('/{overtime}/reject', [OvertimeController::class, 'reject'])->name('reject');
            Route::post('/bulk-approve', [OvertimeController::class, 'bulkApprove'])->name('bulk.approve');
        });
    });
    // --- AKHIR GRUP MANAJEMEN ---


    // Rekap Lembur (Bisa diakses semua, controller/policy atur detail)
    Route::prefix('overtime-recap')->name('overtimes.recap.')->group(function () {
        Route::get('/', [OvertimeRecapController::class, 'index'])->name('index');
        // Lindungi export hanya untuk admin/manajemen?
        Route::get('/export', [OvertimeRecapController::class, 'export'])->middleware('role:admin,manajemen')->name('export');
    });

    // === TAMBAHKAN ROUTE ABSENSI ===
    Route::prefix('attendances')->name('attendances.')->group(function () {
        // Halaman utama absensi (menampilkan tombol check-in/out)
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        // Aksi untuk menyimpan data check-in/check-out (via AJAX/Fetch)
        Route::post('/store', [AttendanceController::class, 'store'])->name('store');
        // Mungkin perlu route untuk riwayat absensi atau koreksi nanti
        // Route::get('/history', [AttendanceController::class, 'history'])->name('history');
        // Route::get('/correction/create', [AttendanceController::class, 'createCorrection'])->name('correction.create');
        // Route::post('/correction', [AttendanceController::class, 'storeCorrection'])->name('correction.store');
    });
    // === AKHIR ROUTE ABSENSI ===

    // === MODUL KOREKSI ABSENSI ===
    Route::prefix('attendance-corrections')->name('attendance_corrections.')->group(function () {
        // Menampilkan form untuk membuat pengajuan koreksi baru
        // Parameter {attendance?} dibuat opsional, karena bisa saja mengoreksi 'Alpha' murni
        // atau kita bisa putuskan untuk selalu butuh tanggal, bukan ID attendance
        Route::get('/create/{attendance_date?}', [App\Http\Controllers\AttendanceCorrectionController::class, 'create'])->name('create');

        // Menyimpan pengajuan koreksi baru
        Route::post('/', [App\Http\Controllers\AttendanceCorrectionController::class, 'store'])->name('store');

        // TODO: Tambahkan route untuk daftar pengajuan user (milik sendiri)
        // Route::get('/', [App\Http\Controllers\AttendanceCorrectionController::class, 'index'])->name('index');

        Route::get('/', [AttendanceCorrectionController::class, 'index'])
            ->middleware(['role:personil,admin']) // Sesuaikan jika admin perlu lihat semua di halaman lain
            ->name('index');


        Route::get('/get-original-data/{date}', [App\Http\Controllers\AttendanceCorrectionController::class, 'getOriginalData'])
            ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}') // Validasi format tanggal YYYY-MM-DD
            ->name('get_original_data');

        // Middleware 'role:manajemen' ditambahkan di sini untuk membatasi akses awal
        Route::middleware(['role:manajemen'])->prefix('approval')->name('approval.')->group(function () {
            // Menampilkan daftar koreksi yang menunggu persetujuan
            Route::get('/', [AttendanceCorrectionController::class, 'listForApproval'])->name('list');

            // Memproses persetujuan (Approve)
            // Menggunakan PATCH karena kita memodifikasi resource yang ada
            // Menggunakan Route Model Binding untuk $correction
            Route::patch('/{correction}/approve', [AttendanceCorrectionController::class, 'approve'])->name('approve');

            // Memproses penolakan (Reject)
            // Menggunakan PATCH atau POST bisa, PATCH lebih sesuai
            Route::patch('/{correction}/reject', [AttendanceCorrectionController::class, 'reject'])->name('reject');
        });
    });
    // === AKHIR MODUL KOREKSI ABSENSI ===

    Route::prefix('monthly-timesheets')->name('monthly_timesheets.')->group(function () {

        // Halaman Index (bisa diakses semua role terotentikasi)
        Route::get('/', [MonthlyTimesheetController::class, 'index'])->name('index');
        Route::get('/{timesheet}', [MonthlyTimesheetController::class, 'show'])->name('show'); // Menampilkan detail timesheet
        // routes/web.php
        Route::post('/{timesheet}/force-reprocess', [MonthlyTimesheetController::class, 'forceReprocess'])->name('force-reprocess')
            ->middleware(['role:admin,manajemen']); // Sesuaikan role

        // Route Export (bisa diakses semua role terotentikasi, otorisasi di controller/policy)
        Route::get('/{timesheet}/export/{format}', [MonthlyTimesheetController::class, 'export'])
            ->where('format', '(pdf|excel)') // Hanya izinkan pdf atau excel
            ->name('export');

        // Grup Approval (Hanya Manajemen)
        Route::middleware(['role:manajemen'])->prefix('approval')->name('approval.')->group(function () {
            Route::get('/asisten', [MonthlyTimesheetController::class, 'listForAsistenApproval'])->name('asisten.list');
            Route::patch('/{timesheet}/approve/asisten', [MonthlyTimesheetController::class, 'approveAsisten'])->name('asisten.approve');
            Route::get('/manager', [MonthlyTimesheetController::class, 'listForManagerApproval'])->name('manager.list');
            Route::patch('/{timesheet}/approve/manager', [MonthlyTimesheetController::class, 'approveManager'])->name('manager.approve');
            Route::patch('/{timesheet}/reject', [MonthlyTimesheetController::class, 'reject'])->name('reject');
            Route::post('/bulk-approve', [MonthlyTimesheetController::class, 'bulkApprove'])->name('bulk.approve'); // Method POST
        });

        // TODO: Route untuk show detail jika perlu
        // Route::get('/{timesheet}', [MonthlyTimesheetController::class, 'show'])->name('show');

    });
}); // Akhir middleware 'auth' group

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
|
| Di sinilah Anda dapat mendaftarkan rute web untuk aplikasi Anda. Rute-rute
| ini dimuat oleh RouteServiceProvider dan semuanya akan
| ditugaskan ke grup middleware "web". Buat sesuatu yang hebat!
|
*/

//==========================================================================
// RUTE PUBLIK (Dapat Diakses Tanpa Autentikasi)
//==========================================================================

/**
 * Rute root ('/') aplikasi.
 * Mengarahkan pengguna ke halaman login jika mereka belum terotentikasi.
 */
Route::get('/', function () {
    return view('auth.login'); // Menampilkan view halaman login.
});

/**
 * Rute untuk menampilkan halaman form login.
 * Nama rute: 'login'
 */
Route::get('login', [LoginController::class, 'index'])->name('login');

/**
 * Rute untuk memproses upaya login pengguna.
 * Menggunakan metode POST.
 * Nama rute: 'action-login'
 */
Route::post('action-login', [LoginController::class, 'actionLogin'])->name('action-login');


//==========================================================================
// RUTE TERAUTENTIKASI (Memerlukan Login)
//==========================================================================
// Semua rute dalam grup ini dilindungi oleh middleware 'auth',
// yang memastikan hanya pengguna yang sudah login yang dapat mengaksesnya.
Route::middleware('auth')->group(function () {

    /**
     * Rute untuk memproses logout pengguna.
     * Menggunakan metode POST.
     * Nama rute: 'logout'
     */
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    /**
     * Rute untuk Dashboard.
     * Menggunakan resource controller, tetapi hanya untuk action 'index' (menampilkan dashboard).
     * Dapat diakses oleh semua pengguna yang terotentikasi.
     * Nama rute: 'dashboard.index'
     */
    Route::resource('dashboard', DashboardController::class)->only(['index']);

    /**
     * Grup rute untuk fungsionalitas Profil Pengguna.
     * Semua rute dalam grup ini akan memiliki prefix URI 'profile' dan nama rute diawali 'profile.'.
     */
    Route::prefix('profile')->name('profile.')->group(function () {
        /**
         * Menampilkan form untuk mengedit profil pengguna yang sedang login.
         * Nama rute: 'profile.edit'
         */
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        /**
         * Menyimpan perubahan data profil pengguna.
         * Menerima metode PUT atau PATCH.
         * Nama rute: 'profile.update'
         */
        Route::match(['put', 'patch'], '/', [ProfileController::class, 'update'])->name('update');
    });

    /**
     * Rute resource untuk manajemen Perjalanan Dinas.
     * Menggunakan Route Model Binding dengan parameter 'perjalananDina' untuk model PerjalananDinas.
     * Akses ke setiap action (create, edit, delete, dll.) diatur lebih lanjut oleh PerjalananDinasPolicy.
     * Contoh nama rute yang dihasilkan: 'perjalanan-dinas.index', 'perjalanan-dinas.create', 'perjalanan-dinas.edit', dll.
     */
    Route::resource('perjalanan-dinas', PerjalananDinasController::class)
        ->parameters(['perjalanan-dinas' => 'perjalananDina']); // Menyesuaikan nama parameter di URL.

    /**
     * Grup rute untuk manajemen Kuota Cuti.
     * Semua rute dalam grup ini akan memiliki prefix URI 'cuti-quota' dan nama rute diawali 'cuti-quota.'.
     */
    Route::prefix('cuti-quota')->name('cuti-quota.')->group(function () {
        /**
         * Menampilkan daftar kuota cuti.
         * Dapat diakses oleh semua pengguna terotentikasi (logika filter berdasarkan peran ada di controller).
         * Nama rute: 'cuti-quota.index'
         */
        Route::get('/', [CutiQuotaController::class, 'index'])->name('index');
        /**
         * Memperbarui data kuota cuti tertentu.
         * Hanya dapat diakses oleh pengguna dengan peran 'admin' (dilindungi middleware 'role:admin').
         * Menerima metode PUT atau PATCH.
         * Nama rute: 'cuti-quota.update'
         */
        Route::match(['put', 'patch'], '/{id}', [CutiQuotaController::class, 'update'])
            ->middleware('role:admin') // Middleware untuk membatasi akses hanya untuk Admin.
            ->name('update');
    });

    /**
     * Grup rute untuk fungsionalitas Cuti.
     * Semua rute dalam grup ini akan memiliki prefix URI 'cuti' dan nama rute diawali 'cuti.'.
     * Akses detail dan otorisasi diatur oleh CutiPolicy.
     */
    Route::prefix('cuti')->name('cuti.')->group(function () {
        /**
         * Menampilkan daftar pengajuan cuti.
         * Nama rute: 'cuti.index'
         */
        Route::get('/', [CutiController::class, 'index'])->name('index');
        /**
         * Menampilkan form untuk membuat pengajuan cuti baru.
         * Dilindungi middleware untuk memastikan hanya 'admin' atau 'personil' yang bisa akses.
         * Kebijakan (Policy) juga bisa digunakan untuk otorisasi yang lebih granural.
         * Nama rute: 'cuti.create'
         */
        Route::get('/create', [CutiController::class, 'create'])->middleware('role:admin,personil')->name('create');
        /**
         * Menyimpan pengajuan cuti baru.
         * Dilindungi middleware untuk 'admin' atau 'personil'.
         * Nama rute: 'cuti.store'
         */
        Route::post('/', [CutiController::class, 'store'])->middleware('role:admin,personil')->name('store');
        /**
         * Rute AJAX untuk mendapatkan sisa kuota cuti pengguna secara dinamis.
         * Nama rute: 'cuti.getQuota.ajax'
         */
        Route::get('/get-quota-ajax', [CutiController::class, 'getQuota'])->name('getQuota.ajax');
        /**
         * Membatalkan pengajuan cuti. Otorisasi dihandle di controller/policy.
         * Nama rute: 'cuti.cancel'
         */
        Route::post('/{cuti}/cancel', [CutiController::class, 'cancel'])->name('cancel');
        /**
         * Menampilkan form untuk mengedit pengajuan cuti. Otorisasi di controller/policy.
         * Nama rute: 'cuti.edit'
         */
        Route::get('/{cuti}/edit', [CutiController::class, 'edit'])->name('edit');
        /**
         * Memperbarui pengajuan cuti yang ada. Otorisasi di controller/policy.
         * Menerima metode PUT atau PATCH.
         * Nama rute: 'cuti.update'
         */
        Route::match(['put', 'patch'], '/{cuti}', [CutiController::class, 'update'])->name('update');
        /**
         * Mengunduh formulir cuti dalam format PDF.
         * Hanya dapat diakses oleh 'admin'.
         * Nama rute: 'cuti.pdf'
         */
        Route::get('/{cuti}/pdf', [CutiController::class, 'downloadPdf'])->middleware('role:admin')->name('pdf');
    });

    /**
     * Grup rute untuk fungsionalitas Lembur (Overtime).
     * Semua rute dalam grup ini akan memiliki prefix URI 'overtimes' dan nama rute diawali 'overtimes.'.
     * Akses detail dan otorisasi diatur oleh OvertimePolicy.
     */
    Route::prefix('overtimes')->name('overtimes.')->group(function () {
        /**
         * Menampilkan daftar pengajuan lembur.
         * Nama rute: 'overtimes.index'
         */
        Route::get('/', [OvertimeController::class, 'index'])->name('index');
        /**
         * Menampilkan form untuk membuat pengajuan lembur baru.
         * Hanya dapat diakses oleh 'admin' atau 'personil'.
         * Nama rute: 'overtimes.create'
         */
        Route::get('/create', [OvertimeController::class, 'create'])->middleware('role:admin,personil')->name('create');
        /**
         * Menyimpan pengajuan lembur baru.
         * Hanya dapat diakses oleh 'admin' atau 'personil'.
         * Nama rute: 'overtimes.store'
         */
        Route::post('/', [OvertimeController::class, 'store'])->middleware('role:admin,personil')->name('store');
        /**
         * Menampilkan form untuk mengedit pengajuan lembur. Otorisasi di controller/policy.
         * Nama rute: 'overtimes.edit'
         */
        Route::get('/{overtime}/edit', [OvertimeController::class, 'edit'])->name('edit');
        /**
         * Memperbarui pengajuan lembur yang ada. Otorisasi di controller/policy.
         * Menerima metode PUT atau PATCH.
         * Nama rute: 'overtimes.update'
         */
        Route::match(['put', 'patch'], '/{overtime}', [OvertimeController::class, 'update'])->name('update');
        /**
         * Menghapus pengajuan lembur.
         * Hanya dapat diakses oleh 'admin'.
         * Nama rute: 'overtimes.destroy'
         */
        Route::delete('/{overtime}', [OvertimeController::class, 'destroy'])->middleware('role:admin')->name('destroy');
        /**
         * Membatalkan pengajuan lembur. Otorisasi di controller/policy.
         * Nama rute: 'overtimes.cancel'
         */
        Route::post('/{overtime}/cancel', [OvertimeController::class, 'cancel'])->name('cancel');
        /**
         * Mengunduh formulir lembur dalam format PDF.
         * Hanya dapat diakses oleh 'admin'.
         * Nama rute: 'overtimes.pdf'
         */
        Route::get('/{overtime}/pdf', [OvertimeController::class, 'downloadOvertimePdf'])->middleware('role:admin')->name('pdf');
        /**
         * Mengunduh beberapa formulir lembur dalam satu file ZIP.
         * Hanya dapat diakses oleh 'admin'.
         * Nama rute: 'overtimes.bulk.pdf'
         */
        Route::post('/bulk-pdf', [OvertimeController::class, 'bulkDownloadPdf'])->middleware('role:admin')->name('bulk.pdf');
    });


    //----------------------------------------------------------------------
    // GRUP RUTE KHUSUS UNTUK PENGGUNA DENGAN PERAN 'admin'
    //----------------------------------------------------------------------
    // Rute-rute dalam grup ini hanya dapat diakses oleh pengguna yang memiliki peran 'admin'.
    Route::middleware(['role:admin'])->group(function () {
        /**
         * Rute resource untuk manajemen Pengguna (Personil).
         * Mencakup action index, create, store, show, edit, update, destroy.
         * Contoh nama rute: 'personil.index', 'personil.create', dll.
         */
        Route::resource('personil', UserController::class);
        /**
         * Rute resource untuk manajemen Vendor.
         * Mencakup action index, create, store, show, edit, update, destroy.
         * Contoh nama rute: 'vendors.index', 'vendors.create', dll.
         */
        Route::resource('vendors', VendorController::class);
        /**
         * Rute resource untuk manajemen Hari Libur (Holidays).
         * Mencakup action index, create, store, show, edit, update, destroy.
         * Contoh nama rute: 'holidays.index', 'holidays.create', dll.
         */
        Route::resource('holidays', HolidayController::class);
    });
    // --- AKHIR GRUP RUTE KHUSUS ADMIN ---


    //----------------------------------------------------------------------
    // GRUP RUTE KHUSUS UNTUK PENGGUNA DENGAN PERAN 'manajemen'
    //----------------------------------------------------------------------
    // Rute-rute dalam grup ini hanya dapat diakses oleh pengguna yang memiliki peran 'manajemen'
    // (misalnya Asisten Manager, Manager).
    Route::middleware(['role:manajemen'])->group(function () {
        /**
         * Grup rute untuk fungsionalitas Persetujuan Cuti.
         * Semua rute dalam grup ini akan memiliki prefix URI 'cuti-approval'
         * dan nama rute diawali 'cuti.approval.'.
         */
        Route::prefix('cuti-approval')->name('cuti.approval.')->group(function () {
            /**
             * Menampilkan daftar pengajuan cuti yang menunggu persetujuan Asisten Manager.
             * Nama rute: 'cuti.approval.asisten.list'
             */
            Route::get('/asisten', [CutiController::class, 'listForAsisten'])->name('asisten.list');
            /**
             * Memproses persetujuan cuti oleh Asisten Manager.
             * Nama rute: 'cuti.approval.asisten.approve'
             */
            Route::patch('/asisten/{cuti}/approve', [CutiController::class, 'approveAsisten'])->name('asisten.approve');
            /**
             * Menampilkan daftar pengajuan cuti yang menunggu persetujuan Manager.
             * Nama rute: 'cuti.approval.manager.list'
             */
            Route::get('/manager', [CutiController::class, 'listForManager'])->name('manager.list');
            /**
             * Memproses persetujuan cuti oleh Manager.
             * Nama rute: 'cuti.approval.manager.approve'
             */
            Route::patch('/manager/{cuti}/approve', [CutiController::class, 'approveManager'])->name('manager.approve');
            /**
             * Memproses penolakan pengajuan cuti (oleh Asisten Manager atau Manager).
             * Nama rute: 'cuti.approval.reject'
             */
            Route::post('/{cuti}/reject', [CutiController::class, 'reject'])->name('reject');
        });

        /**
         * Grup rute untuk fungsionalitas Persetujuan Lembur.
         * Semua rute dalam grup ini akan memiliki prefix URI 'overtime-approval'
         * dan nama rute diawali 'overtimes.approval.'.
         */
        Route::prefix('overtime-approval')->name('overtimes.approval.')->group(function () {
            /**
             * Menampilkan daftar pengajuan lembur yang menunggu persetujuan Asisten Manager.
             * Nama rute: 'overtimes.approval.asisten.list'
             */
            Route::get('/asisten', [OvertimeController::class, 'listForAsisten'])->name('asisten.list');
            /**
             * Memproses persetujuan lembur oleh Asisten Manager.
             * Nama rute: 'overtimes.approval.asisten.approve'
             */
            Route::patch('/asisten/{overtime}/approve', [OvertimeController::class, 'approveAsisten'])->name('asisten.approve');
            /**
             * Menampilkan daftar pengajuan lembur yang menunggu persetujuan Manager.
             * Nama rute: 'overtimes.approval.manager.list'
             */
            Route::get('/manager', [OvertimeController::class, 'listForManager'])->name('manager.list');
            /**
             * Memproses persetujuan lembur oleh Manager.
             * Nama rute: 'overtimes.approval.manager.approve'
             */
            Route::patch('/manager/{overtime}/approve', [OvertimeController::class, 'approveManager'])->name('manager.approve');
            /**
             * Memproses penolakan pengajuan lembur (oleh Asisten Manager atau Manager).
             * Nama rute: 'overtimes.approval.reject'
             */
            Route::post('/{overtime}/reject', [OvertimeController::class, 'reject'])->name('reject');
            /**
             * Memproses persetujuan massal (bulk approve) untuk pengajuan lembur.
             * Nama rute: 'overtimes.approval.bulk.approve'
             */
            Route::post('/bulk-approve', [OvertimeController::class, 'bulkApprove'])->name('bulk.approve');
        });
    });
    // --- AKHIR GRUP RUTE KHUSUS MANAJEMEN ---


    /**
     * Grup rute untuk Rekapitulasi Lembur.
     * Semua rute dalam grup ini akan memiliki prefix URI 'overtime-recap' dan nama rute diawali 'overtimes.recap.'.
     * Dapat diakses oleh semua peran (controller/policy akan mengatur data yang ditampilkan).
     */
    Route::prefix('overtime-recap')->name('overtimes.recap.')->group(function () {
        /**
         * Menampilkan halaman rekapitulasi lembur.
         * Nama rute: 'overtimes.recap.index'
         */
        Route::get('/', [OvertimeRecapController::class, 'index'])->name('index');
        /**
         * Mengekspor data rekapitulasi lembur ke Excel.
         * Dilindungi middleware untuk 'admin' atau 'manajemen'.
         * Nama rute: 'overtimes.recap.export'
         */
        Route::get('/export', [OvertimeRecapController::class, 'export'])->middleware('role:admin,manajemen')->name('export');
    });

    //==========================================================================
    // MODUL ABSENSI
    //==========================================================================
    /**
     * Grup rute untuk fungsionalitas Absensi.
     * Semua rute dalam grup ini akan memiliki prefix URI 'attendances' dan nama rute diawali 'attendances.'.
     */
    Route::prefix('attendances')->name('attendances.')->group(function () {
        /**
         * Menampilkan halaman utama absensi, yang berisi tombol check-in/check-out.
         * Nama rute: 'attendances.index'
         */
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        /**
         * Menyimpan data check-in atau check-out. Diharapkan dipanggil via AJAX/Fetch.
         * Nama rute: 'attendances.store'
         */
        Route::post('/store', [AttendanceController::class, 'store'])->name('store');
        // Komentar untuk rute yang mungkin akan ditambahkan nanti:
        // Route::get('/history', [AttendanceController::class, 'history'])->name('history');
        // Route::get('/correction/create', [AttendanceController::class, 'createCorrection'])->name('correction.create');
        // Route::post('/correction', [AttendanceController::class, 'storeCorrection'])->name('correction.store');
    });
    // === AKHIR RUTE ABSENSI ===

    //==========================================================================
    // MODUL KOREKSI ABSENSI
    //==========================================================================
    /**
     * Grup rute untuk fungsionalitas Koreksi Absensi.
     * Semua rute dalam grup ini akan memiliki prefix URI 'attendance-corrections'
     * dan nama rute diawali 'attendance_corrections.'.
     */
    Route::prefix('attendance-corrections')->name('attendance_corrections.')->group(function () {
        /**
         * Menampilkan form untuk membuat pengajuan koreksi absensi baru.
         * Parameter {attendance_date?} bersifat opsional, untuk pra-mengisi tanggal dari konteks lain.
         * Nama rute: 'attendance_corrections.create'
         */
        Route::get('/create/{attendance_date?}', [App\Http\Controllers\AttendanceCorrectionController::class, 'create'])->name('create');

        /**
         * Menyimpan pengajuan koreksi absensi baru.
         * Nama rute: 'attendance_corrections.store'
         */
        Route::post('/', [App\Http\Controllers\AttendanceCorrectionController::class, 'store'])->name('store');

        /**
         * Menampilkan daftar pengajuan koreksi absensi milik pengguna yang login.
         * Dilindungi middleware untuk 'personil' atau 'admin'. (Admin mungkin memiliki view terpisah untuk semua koreksi).
         * Nama rute: 'attendance_corrections.index'
         */
        Route::get('/', [AttendanceCorrectionController::class, 'index'])
            ->middleware(['role:personil,admin']) // Sesuaikan jika admin perlu lihat semua di halaman lain.
            ->name('index');

        /**
         * Rute AJAX untuk mengambil data absensi asli berdasarkan tanggal.
         * Digunakan di form koreksi untuk pra-mengisi data.
         * Parameter {date} divalidasi formatnya YYYY-MM-DD.
         * Nama rute: 'attendance_corrections.get_original_data'
         */
        Route::get('/get-original-data/{date}', [App\Http\Controllers\AttendanceCorrectionController::class, 'getOriginalData'])
            ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}') // Validasi format tanggal.
            ->name('get_original_data');

        /**
         * Grup rute untuk proses Persetujuan Koreksi Absensi.
         * Hanya dapat diakses oleh pengguna dengan peran 'manajemen'.
         * Semua rute dalam grup ini memiliki prefix URI 'approval' dan nama rute diawali 'approval.'.
         */
        Route::middleware(['role:manajemen'])->prefix('approval')->name('approval.')->group(function () {
            /**
             * Menampilkan daftar pengajuan koreksi yang menunggu persetujuan (untuk Asisten Manager).
             * Nama rute: 'attendance_corrections.approval.list'
             */
            Route::get('/', [AttendanceCorrectionController::class, 'listForApproval'])->name('list');
            /**
             * Memproses persetujuan pengajuan koreksi absensi.
             * Menggunakan metode PATCH dan Route Model Binding untuk $correction.
             * Nama rute: 'attendance_corrections.approval.approve'
             */
            Route::patch('/{correction}/approve', [AttendanceCorrectionController::class, 'approve'])->name('approve');
            /**
             * Memproses penolakan pengajuan koreksi absensi.
             * Menggunakan metode PATCH dan Route Model Binding untuk $correction.
             * Nama rute: 'attendance_corrections.approval.reject'
             */
            Route::patch('/{correction}/reject', [AttendanceCorrectionController::class, 'reject'])->name('reject');
        });
    });
    // === AKHIR MODUL KOREKSI ABSENSI ===

    //==========================================================================
    // MODUL TIMESHEET BULANAN
    //==========================================================================
    /**
     * Grup rute untuk fungsionalitas Rekapitulasi Timesheet Bulanan.
     * Semua rute dalam grup ini akan memiliki prefix URI 'monthly-timesheets'
     * dan nama rute diawali 'monthly_timesheets.'.
     */
    Route::prefix('monthly-timesheets')->name('monthly_timesheets.')->group(function () {

        /**
         * Menampilkan daftar rekap timesheet bulanan.
         * Dapat diakses oleh semua peran terotentikasi (logika filter di controller).
         * Nama rute: 'monthly_timesheets.index'
         */
        Route::get('/', [MonthlyTimesheetController::class, 'index'])->name('index');
        /**
         * Menampilkan detail satu rekap timesheet bulanan.
         * Nama rute: 'monthly_timesheets.show'
         */
        Route::get('/{timesheet}', [MonthlyTimesheetController::class, 'show'])->name('show');
        /**
         * Memaksa pemrosesan ulang (re-generate) sebuah timesheet bulanan.
         * Hanya dapat diakses oleh 'admin' atau 'manajemen'.
         * Nama rute: 'monthly_timesheets.force-reprocess'
         */
        Route::post('/{timesheet}/force-reprocess', [MonthlyTimesheetController::class, 'forceReprocess'])->name('force-reprocess')
            ->middleware(['role:admin,manajemen']); // Sesuaikan peran yang diizinkan.

        /**
         * Mengekspor detail timesheet bulanan ke format tertentu (PDF/Excel).
         * Parameter {format} divalidasi hanya untuk 'pdf' atau 'excel'.
         * Otorisasi lebih lanjut ada di controller/policy.
         * Nama rute: 'monthly_timesheets.export'
         */
        Route::get('/{timesheet}/export/{format}', [MonthlyTimesheetController::class, 'export'])
            ->where('format', '(pdf|excel)') // Hanya izinkan format pdf atau excel.
            ->name('export');

        /**
         * Grup rute untuk proses Persetujuan Timesheet Bulanan.
         * Hanya dapat diakses oleh pengguna dengan peran 'manajemen'.
         * Semua rute dalam grup ini memiliki prefix URI 'approval' dan nama rute diawali 'approval.'.
         */
        Route::middleware(['role:manajemen'])->prefix('approval')->name('approval.')->group(function () {
            /**
             * Menampilkan daftar timesheet yang menunggu persetujuan Asisten Manager.
             * Nama rute: 'monthly_timesheets.approval.asisten.list'
             */
            Route::get('/asisten', [MonthlyTimesheetController::class, 'listForAsistenApproval'])->name('asisten.list');
            /**
             * Memproses persetujuan timesheet oleh Asisten Manager.
             * Nama rute: 'monthly_timesheets.approval.asisten.approve'
             */
            Route::patch('/{timesheet}/approve/asisten', [MonthlyTimesheetController::class, 'approveAsisten'])->name('asisten.approve');
            /**
             * Menampilkan daftar timesheet yang menunggu persetujuan Manager.
             * Nama rute: 'monthly_timesheets.approval.manager.list'
             */
            Route::get('/manager', [MonthlyTimesheetController::class, 'listForManagerApproval'])->name('manager.list');
            /**
             * Memproses persetujuan timesheet oleh Manager.
             * Nama rute: 'monthly_timesheets.approval.manager.approve'
             */
            Route::patch('/{timesheet}/approve/manager', [MonthlyTimesheetController::class, 'approveManager'])->name('manager.approve');
            /**
             * Memproses penolakan timesheet (oleh Asisten Manager atau Manager).
             * Nama rute: 'monthly_timesheets.approval.reject'
             */
            Route::patch('/{timesheet}/reject', [MonthlyTimesheetController::class, 'reject'])->name('reject');
            /**
             * Memproses persetujuan massal (bulk approve) untuk timesheet.
             * Menggunakan metode POST.
             * Nama rute: 'monthly_timesheets.approval.bulk.approve'
             */
            Route::post('/bulk-approve', [MonthlyTimesheetController::class, 'bulkApprove'])->name('bulk.approve');
        });

    });
}); // Akhir dari grup middleware 'auth'

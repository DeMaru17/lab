{{--
    File: sidebar.blade.php
    Deskripsi: File ini mendefinisikan struktur dan konten dari sidebar navigasi aplikasi.
    Sidebar ini bersifat dinamis, menampilkan menu yang berbeda berdasarkan peran (role)
    dan hak akses (policy/gate) pengguna yang sedang login.
    Juga mencakup fitur seperti logo, theme toggler, dan link logout.
--}}

<div id="sidebar">
    <div class="sidebar-wrapper active">
        {{-- Header Sidebar: Berisi Logo, Theme Toggler, dan Tombol Hide Sidebar (untuk mobile) --}}
        <div class="sidebar-header position-relative">
            <div class="d-flex justify-content-between align-items-center">
                {{-- Logo Perusahaan --}}
                <div class="logo">
                    <a href="{{ route('dashboard.index') }}"><img style="width: 120px; height:auto"
                            src="{{ asset('logo-antam.png') }}" alt="Logo" srcset=""></a>
                </div>
                {{-- Theme Toggler (Light/Dark Mode) --}}
                <div class="theme-toggle d-flex gap-2 align-items-center mt-2">
                    {{-- Ikon untuk mode terang --}}
                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                        aria-hidden="true" role="img" class="iconify iconify--system-uicons" width="20"
                        height="20" preserveAspectRatio="xMidYMid meet" viewBox="0 0 21 21">
                        {{-- SVG path data --}}
                    </svg>
                    <div class="form-check form-switch fs-6">
                        <input class="form-check-input me-0" type="checkbox" id="toggle-dark" style="cursor: pointer">
                        <label class="form-check-label"></label>
                    </div>
                    {{-- Ikon untuk mode gelap --}}
                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                        aria-hidden="true" role="img" class="iconify iconify--mdi" width="20" height="20"
                        preserveAspectRatio="xMidYMid meet" viewBox="0 0 24 24">
                        {{-- SVG path data --}}
                    </svg>
                </div>
                {{-- Tombol untuk menyembunyikan sidebar pada tampilan mobile/kecil --}}
                <div class="sidebar-toggler x">
                    <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a>
                </div>
            </div>
        </div>

        {{-- Menu Utama Sidebar --}}
        <div class="sidebar-menu">
            <ul class="menu">
                <li class="sidebar-title">Menu</li>

                {{-- Item Menu: Dashboard --}}
                {{-- Kelas 'active' ditambahkan jika route saat ini cocok dengan 'dashboard.*' --}}
                <li class="sidebar-item {{ Route::is('dashboard.*') ? 'active' : '' }} ">
                    <a href="{{ route('dashboard.index') }}" class='sidebar-link'>
                        <i class="bi bi-grid-fill"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                {{-- ====================================================================== --}}
                {{-- ==               BAGIAN MENU ABSENSI & TIMESHEET                     == --}}
                {{-- ====================================================================== --}}

                @php
                    // Mengambil data pengguna yang sedang login.
                    $loggedInUser = Auth::user();
                    // Flag untuk menandai apakah menu utama 'Absensi' atau submenu-nya aktif.
                    $isAbsensiTimesheetMenuActive = false; // Default tidak aktif.

                    // Cek jika pengguna sudah login sebelum mengakses propertinya.
                    if ($loggedInUser) {
                        // Daftar route yang akan membuat menu 'Absensi' atau submenu-nya aktif.
                        $isAbsensiTimesheetMenuActive =
                            request()->routeIs('attendances.*') || // Semua route terkait absensi (check-in/out).
                            request()->routeIs('attendance_corrections.index') || // Daftar pengajuan koreksi saya.
                            request()->routeIs('attendance_corrections.create') || // Form buat koreksi.
                            request()->routeIs('attendance_corrections.approval.list') || // Daftar approval koreksi (untuk Asisten).
                            request()->routeIs('monthly_timesheets.index') || // Daftar rekap absensi (timesheet), baik milik sendiri maupun kelola (untuk Admin/Manajemen).
                            request()->routeIs('monthly_timesheets.show') || // Detail timesheet.
                            request()->routeIs('monthly_timesheets.approval.asisten.list') || // Daftar approval timesheet Asisten.
                            request()->routeIs('monthly_timesheets.approval.manager.list'); // Daftar approval timesheet Manager.
                    }
                @endphp

                {{-- Tampilkan menu Absensi jika pengguna sudah login --}}
                @if ($loggedInUser)
                    <li class="sidebar-item has-sub {{ $isAbsensiTimesheetMenuActive ? 'active' : '' }}">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-calendar-check-fill"></i>
                            <span>Absensi</span>
                        </a>
                        {{-- Submenu untuk Absensi & Timesheet --}}
                        <ul class="submenu {{ $isAbsensiTimesheetMenuActive ? 'active' : '' }}">

                            {{-- Submenu 1: Absen Hari Ini --}}
                            {{-- Tampil hanya untuk peran 'personil' atau 'admin'. --}}
                            @if ($loggedInUser->role === 'personil' || $loggedInUser->role === 'admin')
                                <li class="submenu-item {{ request()->routeIs('attendances.index') ? 'active' : '' }}">
                                    <a href="{{ route('attendances.index') }}" class="submenu-link">Absen Hari Ini</a>
                                </li>
                            @endif

                            {{-- Submenu 2: Pengajuan Koreksi Absensi Saya --}}
                            {{-- Tampil jika pengguna memiliki hak akses 'create' untuk AttendanceCorrection (via Policy). --}}
                            {{-- Biasanya untuk 'personil' dan 'admin' yang bisa mengajukan koreksi untuk diri sendiri. --}}
                            @can('create', \App\Models\AttendanceCorrection::class)
                                <li
                                    class="submenu-item {{ request()->routeIs(['attendance_corrections.index', 'attendance_corrections.create']) ? 'active' : '' }}">
                                    <a href="{{ route('attendance_corrections.index') }}" class="submenu-link">Pengajuan
                                        Koreksi Absensi</a>
                                </li>
                            @endcan

                            {{-- Submenu 3: Rekap Absensi Saya (Timesheet Milik Sendiri) --}}
                            {{-- Tampil hanya untuk peran 'personil'. --}}
                            @if ($loggedInUser->role === 'personil')
                                {{-- Personil melihat daftar timesheet miliknya sendiri melalui route index standar --}}
                                <li
                                    class="submenu-item {{ request()->routeIs('monthly_timesheets.index') || request()->routeIs('monthly_timesheets.show') ? 'active' : '' }}">
                                    <a href="{{ route('monthly_timesheets.index') }}" class="submenu-link">Rekap
                                        Absensi</a>
                                </li>
                            @endif

                            {{-- Submenu 4: Kelola Rekap Absensi (Timesheet Bulanan Umum) --}}
                            {{-- Tampil untuk 'admin' dan 'manajemen' (bukan 'personil'). --}}
                            {{-- Menggunakan policy 'viewAny' untuk MonthlyTimesheet. --}}
                            @if ($loggedInUser->role !== 'personil')
                                @can('viewAny', \App\Models\MonthlyTimesheet::class)
                                    {{-- Kondisi 'active' dibuat lebih spesifik agar tidak bentrok dengan menu approval --}}
                                    <li
                                        class="submenu-item {{ (request()->routeIs('monthly_timesheets.index') || request()->routeIs('monthly_timesheets.show')) && !request()->routeIs('monthly_timesheets.approval.*') ? 'active' : '' }}">
                                        <a href="{{ route('monthly_timesheets.index') }}" class="submenu-link">Kelola Rekap
                                            Absensi</a>
                                    </li>
                                @endcan
                            @endif

                            {{-- Submenu 5: Approval Koreksi Absensi (Untuk Asisten Manager) --}}
                            {{-- Tampil jika pengguna memiliki hak akses 'viewApprovalList' untuk AttendanceCorrection (via Policy). --}}
                            @can('viewApprovalList', \App\Models\AttendanceCorrection::class)
                                <li
                                    class="submenu-item {{ request()->routeIs('attendance_corrections.approval.list') ? 'active' : '' }}">
                                    <a href="{{ route('attendance_corrections.approval.list') }}"
                                        class="submenu-link">Approval Koreksi Absensi</a>
                                </li>
                            @endcan

                            {{-- Submenu 6: Approval Rekap Absensi (Timesheet) oleh Asisten --}}
                            {{-- Tampil jika pengguna memiliki hak akses 'viewAsistenApprovalList' untuk MonthlyTimesheet (via Policy). --}}
                            @can('viewAsistenApprovalList', \App\Models\MonthlyTimesheet::class)
                                <li
                                    class="submenu-item {{ request()->routeIs('monthly_timesheets.approval.asisten.list') ? 'active' : '' }}">
                                    <a href="{{ route('monthly_timesheets.approval.asisten.list') }}"
                                        class="submenu-link">Approval Rekap Absensi</a>
                                </li>
                            @endcan

                            {{-- Submenu 7: Approval Rekap Absensi (Timesheet) oleh Manager --}}
                            {{-- Tampil jika pengguna memiliki hak akses 'viewManagerApprovalList' untuk MonthlyTimesheet (via Policy). --}}
                            @can('viewManagerApprovalList', \App\Models\MonthlyTimesheet::class)
                                <li
                                    class="submenu-item {{ request()->routeIs('monthly_timesheets.approval.manager.list') ? 'active' : '' }}">
                                    <a href="{{ route('monthly_timesheets.approval.manager.list') }}"
                                        class="submenu-link">Approval Rekap Absensi</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif
                {{-- ====================================================================== --}}
                {{-- ==           AKHIR BAGIAN MENU ABSENSI & TIMESHEET                 == --}}
                {{-- ====================================================================== --}}


                {{-- Helper variabel untuk logika menu Lembur --}}
                @php
                    // $user sudah didefinisikan di atas.
                    $isManajemen = $loggedInUser->role == 'manajemen'; // Cek apakah peran adalah manajemen.
                    // Cek apakah pengguna adalah Asisten Manager (Analis atau Preparator).
                    $isAsisten =
                        $isManajemen &&
                        in_array($loggedInUser->jabatan, ['asisten manager analis', 'asisten manager preparator']);
                    // Cek apakah pengguna adalah Manager.
                    $isManager = $isManajemen && $loggedInUser->jabatan == 'manager';

                    // Kondisi untuk menandai menu utama 'Lembur' atau submenu-nya aktif.
                    // Aktif jika route saat ini diawali 'overtimes.' atau 'overtimes.approval.'.
                    $isLemburMenuActive =
                        request()->routeIs('overtimes.*') || request()->routeIs('overtimes.approval.*');
                @endphp

                {{-- Menu Utama: Lembur --}}
                <li class="sidebar-item has-sub {{ $isLemburMenuActive ? 'active' : '' }}">
                    <a href="#" class='sidebar-link'>
                        <i class="bi bi-clock-history"></i> {{-- Ikon untuk Lembur --}}
                        <span>Lembur</span>
                    </a>
                    {{-- Submenu untuk Lembur --}}
                    <ul class="submenu {{ $isLemburMenuActive ? 'active' : '' }}">

                        {{-- Submenu 1: Daftar Lembur --}}
                        {{-- Dapat diakses oleh semua peran (data akan difilter di controller/policy). --}}
                        {{-- Aktif jika route saat ini adalah 'overtimes.index', 'overtimes.create', 'overtimes.edit', atau 'overtimes.show'. --}}
                        <li
                            class="submenu-item {{ request()->routeIs(['overtimes.index', 'overtimes.create', 'overtimes.edit', 'overtimes.show']) ? 'active' : '' }}">
                            <a href="{{ route('overtimes.index') }}" class="submenu-link">Daftar Lembur</a>
                        </li>

                        {{-- Submenu: Rekap Lembur --}}
                        <li class="submenu-item {{ Route::is('overtimes.recap.index') ? 'active' : '' }}">
                            <a href="{{ route('overtimes.recap.index') }}" class="submenu-link">Rekap Lembur</a>
                        </li>

                        {{-- Submenu 2: Persetujuan Asisten Manager --}}
                        {{-- Tampil hanya jika pengguna adalah Asisten Manager. --}}
                        @if ($isAsisten)
                            <li
                                class="submenu-item {{ request()->routeIs('overtimes.approval.asisten.list') ? 'active' : '' }}">
                                <a href="{{ route('overtimes.approval.asisten.list') }}"
                                    class="submenu-link">Persetujuan Asisten Manager</a>
                            </li>
                        @endif

                        {{-- Submenu 3: Persetujuan Manager --}}
                        {{-- Tampil hanya jika pengguna adalah Manager. --}}
                        @if ($isManager)
                            <li
                                class="submenu-item {{ request()->routeIs('overtimes.approval.manager.list') ? 'active' : '' }}">
                                <a href="{{ route('overtimes.approval.manager.list') }}"
                                    class="submenu-link">Persetujuan Manager</a>
                            </li>
                        @endif
                        {{-- Catatan: Link "Ajukan Lembur" biasanya tidak ada di submenu, melainkan tombol di halaman daftar lembur. --}}
                    </ul>
                </li>

                {{-- Helper variabel untuk logika menu Cuti --}}
                @php
                    // $user, $isManajemen, $isAsisten, $isManager sudah didefinisikan sebelumnya.

                    // Kondisi untuk menandai menu utama 'Cuti' atau submenu-nya aktif.
                    // Aktif jika route saat ini adalah 'cuti.index', 'cuti.create', 'cuti.edit',
                    // atau semua route yang diawali 'cuti-quota.*' atau 'cuti.approval.*'.
                    $isCutiMenuActive =
                        request()->routeIs(['cuti.index', 'cuti.create', 'cuti.edit']) ||
                        request()->routeIs('cuti-quota.*') ||
                        request()->routeIs('cuti.approval.*');
                @endphp

                {{-- Menu Utama: Cuti --}}
                <li class="sidebar-item has-sub {{ $isCutiMenuActive ? 'active' : '' }}">
                    <a href="#" class='sidebar-link'>
                        <i class="bi bi-calendar-week-fill"></i> {{-- Ikon untuk Cuti --}}
                        <span>Cuti</span>
                    </a>
                    {{-- Submenu untuk Cuti --}}
                    <ul class="submenu {{ $isCutiMenuActive ? 'active' : '' }}">

                        {{-- Submenu 1: Daftar Cuti --}}
                        {{-- Dapat diakses oleh semua peran (data difilter di controller/policy). --}}
                        <li
                            class="submenu-item {{ request()->routeIs(['cuti.index', 'cuti.edit', 'cuti.create']) ? 'active' : '' }}">
                            <a href="{{ route('cuti.index') }}" class="submenu-link">Daftar Cuti</a>
                        </li>

                        {{-- Submenu 2: Persetujuan Asisten Manager --}}
                        {{-- Tampil hanya jika pengguna adalah Asisten Manager. --}}
                        @if ($isAsisten)
                            <li
                                class="submenu-item {{ request()->routeIs('cuti.approval.asisten.list') ? 'active' : '' }}">
                                <a href="{{ route('cuti.approval.asisten.list') }}" class="submenu-link">Persetujuan
                                    Asisten Manager</a>
                            </li>
                        @endif

                        {{-- Submenu 3: Persetujuan Manager --}}
                        {{-- Tampil hanya jika pengguna adalah Manager. --}}
                        @if ($isManager)
                            <li
                                class="submenu-item {{ request()->routeIs('cuti.approval.manager.list') ? 'active' : '' }}">
                                <a href="{{ route('cuti.approval.manager.list') }}" class="submenu-link">Persetujuan
                                    Manager</a>
                            </li>
                        @endif

                        {{-- Submenu 4: Kuota Cuti --}}
                        {{-- Dapat diakses semua peran (hak akses detail diatur di controller CutiQuotaController). --}}
                        <li class="submenu-item {{ request()->routeIs('cuti-quota.*') ? 'active' : '' }}">
                            <a href="{{ route('cuti-quota.index') }}" class="submenu-link">Kuota Cuti</a>
                        </li>
                    </ul>
                </li>

                {{-- Item Menu: Perjalanan Dinas --}}
                {{-- Dapat diakses semua peran (data difilter di controller/policy). --}}
                <li class="sidebar-item {{ Route::is('perjalanan-dinas.*') ? 'active' : '' }}">
                    <a href="{{ route('perjalanan-dinas.index') }}" class='sidebar-link'>
                        <i class="bi bi-airplane-fill"></i>
                        <span>Perjalanan Dinas</span>
                    </a>
                </li>

                {{-- Helper variabel untuk menu khusus Admin --}}
                @php
                    // $user sudah didefinisikan.
                    $isAdmin = $loggedInUser->role == 'admin'; // Cek apakah pengguna adalah Admin.
                @endphp

                {{-- Bagian Menu Khusus Admin --}}
                @if ($isAdmin)
                    {{-- Item Menu: Manajemen Personil --}}
                    <li class="sidebar-item {{ Route::is('personil.*') ? 'active' : '' }}">
                        <a href="{{ route('personil.index') }}" class='sidebar-link'>
                            <i class="bi bi-people-fill"></i>
                            <span>Personil</span>
                        </a>
                    </li>

                    {{-- Item Menu: Manajemen Vendor --}}
                    <li class="sidebar-item {{ Route::is('vendors.*') ? 'active' : '' }}">
                        <a href="{{ route('vendors.index') }}" class='sidebar-link'>
                            <i class="bi bi-grid-1x2-fill"></i>
                            <span>Vendor</span>
                        </a>
                    </li>

                    {{-- Item Menu: Manajemen Hari Libur --}}
                    {{-- Kelas 'active' ditambahkan jika route saat ini cocok dengan 'Holidays.*'.
                         Catatan: Route::is() bersifat case-sensitive, pastikan nama route ('holidays.index', dll.)
                         sesuai dengan yang didefinisikan. Jika resource controller dinamai 'holidays',
                         maka Route::is('holidays.*') akan lebih tepat.
                    --}}
                    <li class="sidebar-item {{ Route::is('holidays.*') ? 'active' : '' }}">
                        <a href="{{ route('holidays.index') }}" class='sidebar-link'>
                            <i class="bi bi-calendar-x"></i>
                            <span>Hari Libur</span>
                        </a>
                    </li>
                @endif
                {{-- Akhir Bagian Menu Khusus Admin --}}

                {{-- Item Menu: Profil Saya --}}
                {{-- Dapat diakses oleh semua pengguna terotentikasi. --}}
                <li class="sidebar-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                    <a href="{{ route('profile.edit') }}" class='sidebar-link'>
                        <i class="bi bi-person-circle"></i>
                        <span>Profil Saya</span>
                    </a>
                </li>
            </ul>

            {{-- Bagian Menu Logout --}}
            <ul class="menu">
                <li class="sidebar-item" style="margin-top: 15px;">
                    {{-- Tautan Logout, menggunakan JavaScript untuk konfirmasi sebelum submit form. --}}
                    <a href="{{ route('logout') }}" class='sidebar-link'
                        onclick="event.preventDefault(); if (confirm('Apakah Anda yakin ingin keluar?')) { document.getElementById('logout-form').submit(); }">
                        <i class="bi bi-door-open-fill"></i>
                        <span>Logout</span>
                    </a>
                    {{-- Form tersembunyi untuk melakukan logout dengan metode POST (lebih aman). --}}
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf {{-- Token CSRF untuk keamanan --}}
                    </form>
                </li>
            </ul>
        </div>
    </div>
</div>

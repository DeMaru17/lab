<div id="sidebar">
    <div class="sidebar-wrapper active">
        <div class="sidebar-header position-relative">
            <div class="d-flex justify-content-between align-items-center">
                <div class="logo">
                    <a href="index.html"><img src="{{ asset('assets/compiled/svg/logo.svg') }}" alt="Logo"
                            srcset=""></a>
                </div>
                <div class="theme-toggle d-flex gap-2  align-items-center mt-2">
                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                        aria-hidden="true" role="img" class="iconify iconify--system-uicons" width="20"
                        height="20" preserveAspectRatio="xMidYMid meet" viewBox="0 0 21 21">
                        <g fill="none" fill-rule="evenodd" stroke="currentColor" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path
                                d="M10.5 14.5c2.219 0 4-1.763 4-3.982a4.003 4.003 0 0 0-4-4.018c-2.219 0-4 1.781-4 4c0 2.219 1.781 4 4 4zM4.136 4.136L5.55 5.55m9.9 9.9l1.414 1.414M1.5 10.5h2m14 0h2M4.135 16.863L5.55 15.45m9.899-9.9l1.414-1.415M10.5 19.5v-2m0-14v-2"
                                opacity=".3"></path>
                            <g transform="translate(-210 -1)">
                                <path d="M220.5 2.5v2m6.5.5l-1.5 1.5"></path>
                                <circle cx="220.5" cy="11.5" r="4"></circle>
                                <path d="m214 5l1.5 1.5m5 14v-2m6.5-.5l-1.5-1.5M214 18l1.5-1.5m-4-5h2m14 0h2"></path>
                            </g>
                        </g>
                    </svg>
                    <div class="form-check form-switch fs-6">
                        <input class="form-check-input  me-0" type="checkbox" id="toggle-dark" style="cursor: pointer">
                        <label class="form-check-label"></label>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                        aria-hidden="true" role="img" class="iconify iconify--mdi" width="20" height="20"
                        preserveAspectRatio="xMidYMid meet" viewBox="0 0 24 24">
                        <path fill="currentColor"
                            d="m17.75 4.09l-2.53 1.94l.91 3.06l-2.63-1.81l-2.63 1.81l.91-3.06l-2.53-1.94L12.44 4l1.06-3l1.06 3l3.19.09m3.5 6.91l-1.64 1.25l.59 1.98l-1.7-1.17l-1.7 1.17l.59-1.98L15.75 11l2.06-.05L18.5 9l.69 1.95l2.06.05m-2.28 4.95c.83-.08 1.72 1.1 1.19 1.85c-.32.45-.66.87-1.08 1.27C15.17 23 8.84 23 4.94 19.07c-3.91-3.9-3.91-10.24 0-14.14c.4-.4.82-.76 1.27-1.08c.75-.53 1.93.36 1.85 1.19c-.27 2.86.69 5.83 2.89 8.02a9.96 9.96 0 0 0 8.02 2.89m-1.64 2.02a12.08 12.08 0 0 1-7.8-3.47c-2.17-2.19-3.33-5-3.49-7.82c-2.81 3.14-2.7 7.96.31 10.98c3.02 3.01 7.84 3.12 10.98.31Z">
                        </path>
                    </svg>
                </div>
                <div class="sidebar-toggler  x">
                    <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a>
                </div>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul class="menu">
                <li class="sidebar-title">Menu</li>

                <li class="sidebar-item {{ Route::is('dashboard.*') ? 'active' : '' }} ">
                    <a href="{{ route('dashboard.index') }}" class='sidebar-link'>
                        <i class="bi bi-grid-fill"></i>
                        <span>Dashboard</span>
                    </a>


                </li>

                {{-- ====================================================================== --}}
                {{-- ==          BAGIAN MENU ABSENSI & TIMESHEET                         == --}}
                {{-- ====================================================================== --}}

                {{-- Variabel helper (idealnya diletakkan sekali di awal file sidebar) --}}
                @php
                    // Pastikan Auth::user() dipanggil hanya sekali jika memungkinkan
                    if (!isset($loggedInUser)) {
                        // Cek jika belum didefinisikan sebelumnya
                        $loggedInUser = Auth::user();
                    }
                    if ($loggedInUser) {
                        // Cek jika user benar-benar login
                        $userRole = $loggedInUser->role;
                        $userJabatan = $loggedInUser->jabatan;

                        $isManajemen = $userRole == 'manajemen';
                        $isAsisten =
                            $isManajemen &&
                            in_array($userJabatan, ['asisten manager analis', 'asisten manager preparator']);
                        $isManager = $isManajemen && $userJabatan == 'manager';
                        $isAdmin = $userRole == 'admin';
                        $isPersonil = $userRole == 'personil';

                        // Kondisi untuk class 'active' di menu utama Absensi & Timesheet
                        // Cek apakah route saat ini dimulai dengan prefix yang relevan
                        // Gunakan nama route yang benar (dengan underscore)
                        $isAbsensiTimesheetMenuActive =
                            request()->routeIs('attendances.*') ||
                            request()->routeIs('attendance_corrections.*') ||
                            request()->routeIs('monthly_timesheets.*'); // Mencakup semua route timesheet termasuk approval
                    } else {
                        // Default values jika user tidak login (seharusnya tidak terjadi jika sidebar dilindungi middleware auth)
                        $isManajemen = $isAsisten = $isManager = $isAdmin = $isPersonil = false;
                        $isAbsensiTimesheetMenuActive = false;
                    }
                @endphp

                {{-- Menu Utama Absensi & Timesheet --}}
                @if ($loggedInUser) {{-- Tampilkan hanya jika user login --}}
                    <li class="sidebar-item has-sub {{ $isAbsensiTimesheetMenuActive ? 'active' : '' }}">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-calendar-check-fill"></i> {{-- Ikon Absensi/Timesheet --}}
                            <span>Absensi</span>
                        </a>
                        {{-- Submenu Absensi & Timesheet --}}
                        <ul class="submenu {{ $isAbsensiTimesheetMenuActive ? 'active' : '' }}">

                            {{-- 1. Submenu Absen Hari Ini (Personil & Admin) --}}
                            @if ($isPersonil || $isAdmin)
                                <li class="submenu-item {{ request()->routeIs('attendances.index') ? 'active' : '' }}">
                                    <a href="{{ route('attendances.index') }}" class="submenu-link">Absen Hari Ini</a>
                                </li>
                            @endif

                            {{-- 2. Submenu Koreksi Absensi (Personil & Admin) --}}
                            @if ($isPersonil || $isAdmin)
                                {{-- Aktif jika di index koreksi atau form create/edit koreksi --}}
                                <li
                                    class="submenu-item {{ request()->routeIs(['attendance_corrections.index', 'attendance_corrections.create', 'attendance_corrections.edit']) ? 'active' : '' }}">
                                    <a href="{{ route('attendance_corrections.index') }}" class="submenu-link">Koreksi
                                        Absensi</a>
                                </li>
                            @endif

                            {{-- 3. Submenu Rekap Timesheet (Admin & Manajemen) --}}
                            {{-- Gunakan @can jika policy sudah ada --}}
                            {{-- @can('viewAny', \App\Models\MonthlyTimesheet::class) --}}
                            @if ($isAdmin || $isManajemen)
                                {{-- Aktif jika di index rekap atau halaman show rekap --}}
                                <li
                                    class="submenu-item {{ request()->routeIs(['monthly_timesheets.index', 'monthly_timesheets.show']) ? 'active' : '' }}">
                                    <a href="{{ route('monthly_timesheets.index') }}" class="submenu-link">Rekap
                                        Absensi</a>
                                </li>
                                {{-- @endcan --}}
                            @endif

                            {{-- 4. Submenu Approval Koreksi (Hanya Asisten) --}}
                            @if ($isAsisten)
                                <li
                                    class="submenu-item {{ request()->routeIs('attendance_corrections.approval.list') ? 'active' : '' }}">
                                    <a href="{{ route('attendance_corrections.approval.list') }}"
                                        class="submenu-link">Approval Koreksi Absen</a>
                                </li>
                            @endif

                            {{-- 5. Submenu Approval Timesheet Asisten (Hanya Asisten) --}}
                            @if ($isAsisten)
                                {{-- Gunakan @can jika policy ada: @can('viewAsistenApprovalList', \App\Models\MonthlyTimesheet::class) --}}
                                <li
                                    class="submenu-item {{ request()->routeIs('monthly_timesheets.approval.asisten.list') ? 'active' : '' }}">
                                    <a href="{{ route('monthly_timesheets.approval.asisten.list') }}"
                                        class="submenu-link">Approval Absensi</a>
                                </li>
                                {{-- @endcan --}}
                            @endif

                            {{-- 6. Submenu Approval Timesheet Manager (Hanya Manager) --}}
                            @if ($isManager)
                                {{-- Gunakan @can jika policy ada: @can('viewManagerApprovalList', \App\Models\MonthlyTimesheet::class) --}}
                                <li
                                    class="submenu-item {{ request()->routeIs('monthly_timesheets.approval.manager.list') ? 'active' : '' }}">
                                    <a href="{{ route('monthly_timesheets.approval.manager.list') }}"
                                        class="submenu-link">Approval Absensi</a>
                                </li>
                                {{-- @endcan --}}
                            @endif

                        </ul>
                    </li>
                @endif
                {{-- ====================================================================== --}}
                {{-- ==        AKHIR BAGIAN MENU ABSENSI & TIMESHEET                     == --}}
                {{-- ====================================================================== --}}



                {{-- Variabel helper untuk mempersingkat pengecekan (bisa diletakkan sekali di awal sidebar) --}}
                @php
                    $user = Auth::user(); // Ambil user yang login
                    $isManajemen = $user->role == 'manajemen';
                    $isAsisten =
                        $isManajemen &&
                        in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator']);
                    $isManager = $isManajemen && $user->jabatan == 'manager';

                    // Kondisi untuk class 'active' di menu utama Lembur
                    // Cek apakah route saat ini dimulai dengan 'overtimes.' atau 'overtimes.approval.'
                    $isLemburMenuActive =
                        request()->routeIs('overtimes.*') || request()->routeIs('overtimes.approval.*');
                @endphp

                {{-- Menu Utama Lembur --}}
                <li class="sidebar-item has-sub {{ $isLemburMenuActive ? 'active' : '' }}">
                    <a href="#" class='sidebar-link'>
                        <i class="bi bi-clock-history"></i> {{-- Ikon Lembur --}}
                        <span>Lembur</span>
                    </a>
                    {{-- Submenu Lembur --}}
                    <ul class="submenu {{ $isLemburMenuActive ? 'active' : '' }}">

                        {{-- 1. Submenu Daftar Lembur (Semua role bisa lihat daftarnya masing-masing) --}}
                        {{-- Aktif jika route adalah index, create, edit, atau show (jika ada) --}}
                        <li
                            class="submenu-item {{ request()->routeIs(['overtimes.index', 'overtimes.create', 'overtimes.edit', 'overtimes.show']) ? 'active' : '' }}">
                            <a href="{{ route('overtimes.index') }}" class="submenu-link">Daftar Lembur</a>
                        </li>

                        <li class="submenu-item {{ Route::is('overtimes.recap.index') ? 'active' : '' }}">
                            <a href="{{ route('overtimes.recap.index') }}" class="submenu-link">Rekap Lembur</a>
                        </li>

                        {{-- 2. Submenu Persetujuan Asisten (Hanya Asisten Manager) --}}
                        @if ($isAsisten)
                            <li
                                class="submenu-item {{ request()->routeIs('overtimes.approval.asisten.list') ? 'active' : '' }}">
                                {{-- Pastikan route 'overtimes.approval.asisten.list' sudah/akan didefinisikan --}}
                                <a href="{{ route('overtimes.approval.asisten.list') }}"
                                    class="submenu-link">Persetujuan
                                    Asisten Manager</a>
                            </li>
                        @endif

                        {{-- 3. Submenu Persetujuan Manager (Hanya Manager) --}}
                        @if ($isManager)
                            <li
                                class="submenu-item {{ request()->routeIs('overtimes.approval.manager.list') ? 'active' : '' }}">
                                {{-- Pastikan route 'overtimes.approval.manager.list' sudah/akan didefinisikan --}}
                                <a href="{{ route('overtimes.approval.manager.list') }}"
                                    class="submenu-link">Persetujuan
                                    Manager</a>
                            </li>
                        @endif

                        {{-- Link Ajukan Lembur tidak perlu karena tombol ada di index --}}

                    </ul>
                </li>

                @php
                    $user = Auth::user();
                    $isManajemen = $user->role == 'manajemen';
                    $isAsisten =
                        $isManajemen &&
                        in_array($user->jabatan, ['asisten manager analis', 'asisten manager preparator']);
                    $isManager = $isManajemen && $user->jabatan == 'manager';

                    // Kondisi untuk class 'active' di menu utama Cuti
                    $isCutiMenuActive =
                        request()->is('cuti*') || request()->is('cuti-quota*') || request()->is('cuti-approval*');
                    // Note: request()->is('cuti*') akan match /cuti, /cuti/create, /cuti/1/edit, dll.
                    // Ini juga akan match /cuti-quota dan /cuti-approval, jadi mungkin cukup request()->is('cuti*')
                    // Mari kita buat lebih spesifik:
                    $isCutiMenuActive =
                        request()->routeIs(['cuti.index', 'cuti.create', 'cuti.edit']) ||
                        request()->routeIs('cuti-quota.*') ||
                        request()->routeIs('cuti.approval.*');

                @endphp

                {{-- Menu Utama Cuti --}}
                <li class="sidebar-item has-sub {{ $isCutiMenuActive ? 'active' : '' }}">
                    <a href="#" class='sidebar-link'>
                        {{-- Ganti ikon jika perlu - Contoh: Ikon Kalender/Jadwal --}}
                        <i class="bi bi-calendar-week-fill"></i>
                        <span>Cuti</span>
                    </a>
                    {{-- Submenu Cuti --}}
                    <ul class="submenu {{ $isCutiMenuActive ? 'active' : '' }}">

                        {{-- 1. Submenu Daftar Cuti (Semua role bisa lihat daftarnya masing-masing) --}}
                        <li
                            class="submenu-item {{ request()->routeIs(['cuti.index', 'cuti.edit', 'cuti.create']) ? 'active' : '' }}">
                            <a href="{{ route('cuti.index') }}" class="submenu-link">Daftar Cuti</a>
                        </li>

                        {{-- 2. Submenu Persetujuan Asisten (Hanya Asisten Manager) --}}
                        @if ($isAsisten)
                            <li
                                class="submenu-item {{ request()->routeIs('cuti.approval.asisten.list') ? 'active' : '' }}">
                                <a href="{{ route('cuti.approval.asisten.list') }}" class="submenu-link">Persetujuan
                                    Asisten Manager</a>
                            </li>
                        @endif

                        {{-- 3. Submenu Persetujuan Manager (Hanya Manager) --}}
                        @if ($isManager)
                            <li
                                class="submenu-item {{ request()->routeIs('cuti.approval.manager.list') ? 'active' : '' }}">
                                {{-- Ganti route jika sudah dibuat --}}
                                <a href="{{ route('cuti.approval.manager.list') }}" class="submenu-link">Persetujuan
                                    Manager</a>
                            </li>
                        @endif

                        {{-- 4. Submenu Kuota Cuti (Semua role bisa lihat/kelola sesuai hak akses di controllernya) --}}
                        <li class="submenu-item {{ request()->routeIs('cuti-quota.*') ? 'active' : '' }}">
                            <a href="{{ route('cuti-quota.index') }}" class="submenu-link">Kuota Cuti</a>
                        </li>



                    </ul>
                </li>

                <li class="sidebar-item {{ Route::is('perjalanan-dinas.*') ? 'active' : '' }}">
                    <a href="{{ route('perjalanan-dinas.index') }}" class='sidebar-link'>
                        <i class="bi bi-airplane-fill"></i>
                        <span>Perjalanan Dinas</span>
                    </a>
                </li>

                <li class="sidebar-item {{ Route::is('personil.*') ? 'active' : '' }}">
                    <a href="{{ route('personil.index') }}" class='sidebar-link'>
                        <i class="bi bi-people-fill"></i>
                        <span>Personil</span>
                    </a>
                </li>

                <li class="sidebar-item {{ Route::is('vendors.*') ? 'active' : '' }}">
                    <a href="{{ route('vendors.index') }}" class='sidebar-link'>
                        <i class="bi bi-grid-1x2-fill"></i>
                        <span>Vendor</span>
                    </a>
                </li>

                <li class="sidebar-item {{ Route::is('Holidays.*') ? 'active' : '' }}">
                    <a href="{{ route('holidays.index') }}" class='sidebar-link'>
                        <i class="bi bi-calendar-x"></i>
                        <span>Hari Libur</span>
                    </a>
                </li>

                <li class="sidebar-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                    <a href="{{ route('profile.edit') }}" class='sidebar-link'>
                        <i class="bi bi-person-circle"></i>
                        <span>Profil Saya</span>
                    </a>
                </li>





            </ul>

            <ul class="menu">
                <li class="sidebar-item" style="margin-top: 15px;">
                    <a href="{{ route('logout') }}" class='sidebar-link'
                        onclick="event.preventDefault(); if (confirm('Apakah Anda yakin ingin keluar?')) { document.getElementById('logout-form').submit(); }">
                        <i class="bi bi-door-open-fill"></i>
                        <span>Logout</span>
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </li>
            </ul>
        </div>
    </div>
</div>

@extends('layout.app')

{{-- @section('title', 'Dashboard') --}}

@section('content')
    <div id="main">
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Dashboard</h3>
                        <p class="text-subtitle text-muted">Selamat datang kembali, {{ Auth::user()->name }}!</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active" aria-current="page"><i class="bi bi-grid-fill"></i>
                                    Dashboard</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="row">
                {{-- Kolom Kiri - Konten Utama --}}
                <div class="col-lg-8 col-md-12">
                    {{-- Tombol Absen Hari Ini (Untuk Personil & Admin yang Absen) --}}
                    @if (Auth::user()->role === 'personil' || (Auth::user()->role === 'admin' && config('hris.admin_can_attend', true)))
                        <div class="card shadow-sm mb-4">
                            <div class="card-body text-center">
                                <h5 class="card-title">Absensi Hari Ini</h5>
                                <p class="text-muted">{{ $currentTime->translatedFormat('l, d F Y') }} - <span
                                        id="clock">{{ $currentTime->format('H:i:s') }}</span></p>
                                <a href="{{ route('attendances.index') }}" class="btn btn-primary btn-lg px-4 py-2 fs-5">
                                    <i class="bi bi-fingerprint me-2"></i>{{ $attendanceButtonText }}
                                </a>
                                @if ($todaysAttendanceRecord)
                                    <div class="mt-3">
                                        @if ($todaysAttendanceRecord->clock_in_time)
                                            <p class="mb-0"><small>Anda telah melakukan check-in pada:
                                                    <strong>{{ $todaysAttendanceRecord->clock_in_time->format('H:i:s') }}</strong></small>
                                            </p>
                                        @endif
                                        @if ($todaysAttendanceRecord->clock_out_time)
                                            <p class="mb-0"><small>Anda telah melakukan check-out pada:
                                                    <strong>{{ $todaysAttendanceRecord->clock_out_time->format('H:i:s') }}</strong></small>
                                            </p>
                                        @endif
                                        @if ($todaysAttendanceRecord->attendance_status)
                                            <p class="mb-0"><small>Status Kehadiran: <span
                                                        class="fw-bold">{{ $todaysAttendanceRecord->attendance_status }}</span></small>
                                            </p>
                                        @endif
                                    </div>
                                @else
                                    <p class="mt-3 mb-0"><small>Anda belum melakukan absensi hari ini.</small></p>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- WIDGETS BERDASARKAN ROLE --}}
                    <div class="row">
                        {{-- === WIDGETS UNTUK PERSONIL === --}}
                        @if (Auth::user()->role === 'personil')
                            <div class="col-md-6 col-xl-4">
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="stats-icon purple">
                                                <i class="bi bi-calendar-plus-fill fs-3"></i>
                                            </div>
                                            <div class="ms-3">
                                                <h6 class="text-muted font-semibold">Cuti Tahunan</h6>
                                                <h5 class="font-extrabold mb-0">{{ $annualLeaveQuota ?? 0 }} hari</h5>
                                            </div>
                                        </div>
                                        @if (isset($specialPDLeaveQuota) && $specialPDLeaveQuota > 0)
                                            <hr class="my-2">
                                            <div class="d-flex align-items-center">
                                                <div class="stats-icon purple">
                                                    <i class="bi bi-briefcase-fill fs-4"></i>
                                                </div>
                                                <div class="ms-3">
                                                    <h6 class="text-muted font-semibold"><small>Cuti Perj. Dinas</small>
                                                    </h6>
                                                    <h6 class="font-extrabold mb-0"><small>{{ $specialPDLeaveQuota }}
                                                            hari</small></h6>
                                                </div>
                                            </div>
                                        @endif
                                        <a href="{{ route('cuti.index') }}"
                                            class="btn btn-sm btn-outline-primary float-end mt-2">Lihat Cuti</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="stats-icon blue">
                                                <i class="bi bi-clock-history fs-3"></i>
                                            </div>
                                            <div class="ms-3">
                                                <h6 class="text-muted font-semibold">Lembur Bulan Ini</h6>
                                                <h5 class="font-extrabold mb-0">
                                                    {{ $approvedOvertimeThisMonthFormatted ?? '0 jam 00 menit' }}</h5>
                                            </div>
                                        </div>
                                        <a href="{{ route('overtimes.index') }}"
                                            class="btn btn-sm btn-outline-primary float-end mt-2">Lihat Lembur</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="stats-icon green">
                                                <i class="bi bi-card-checklist fs-3"></i>
                                            </div>
                                            <div class="ms-3">
                                                <h6 class="text-muted font-semibold">Timesheet Terkini</h6>
                                                @if ($latestTimesheet)
                                                    <h5 class="font-extrabold mb-0">
                                                        <span
                                                            class="badge {{ App\Helpers\StatusHelper::timesheetStatusColor($latestTimesheet->status) }}">
                                                            {{ Str::title(str_replace('_', ' ', $latestTimesheet->status)) }}
                                                        </span>
                                                    </h5>
                                                    <small
                                                        class="text-muted">{{ $latestTimesheet->period_start_date->format('M Y') }}</small>
                                                @else
                                                    <h5 class="font-extrabold mb-0">-</h5>
                                                @endif
                                            </div>
                                        </div>
                                        <a href="{{ route('monthly_timesheets.index') }}"
                                            class="btn btn-sm btn-outline-primary float-end mt-2">Lihat Timesheet</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="stats-icon red">
                                                <i class="bi bi-pencil-square fs-3"></i>
                                            </div>
                                            <div class="ms-3">
                                                <h6 class="text-muted font-semibold">Koreksi Absen Pending</h6>
                                                <h5 class="font-extrabold mb-0">
                                                    {{ $pendingAttendanceCorrectionCount ?? 0 }}</h5>
                                            </div>
                                        </div>
                                        <a href="{{ route('attendance_corrections.index') }}"
                                            class="btn btn-sm btn-outline-primary float-end mt-2">Lihat Koreksi</a>
                                    </div>
                                </div>
                            </div>
                        @endif
                        {{-- === AKHIR WIDGETS PERSONIL === --}}

                        {{-- === WIDGETS UNTUK MANAJEMEN (ASISTEN & MANAGER) === --}}
                        @if (Auth::user()->role === 'manajemen')
                            <div class="col-12">
                                <div class="card shadow-sm">
                                    <div class="card-header">
                                        <h4 class="card-title">Tugas Persetujuan Anda</h4>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-group list-group-flush">
                                            @if (isset($pendingLeaveApprovalCount) && $pendingLeaveApprovalCount > 0)
                                                <li
                                                    class="list-group-item d-flex justify-content-between align-items-center">
                                                    Cuti Menunggu Persetujuan
                                                    <div>
                                                        <span
                                                            class="badge bg-warning rounded-pill me-2">{{ $pendingLeaveApprovalCount }}</span>
                                                        <a href="{{ Auth::user()->jabatan === 'manager' ? route('cuti.approval.manager.list') : route('cuti.approval.asisten.list') }}"
                                                            class="btn btn-sm btn-outline-primary">Proses</a>
                                                    </div>
                                                </li>
                                            @endif
                                            @if (isset($pendingOvertimeApprovalCount) && $pendingOvertimeApprovalCount > 0)
                                                <li
                                                    class="list-group-item d-flex justify-content-between align-items-center">
                                                    Lembur Menunggu Persetujuan
                                                    <div>
                                                        <span
                                                            class="badge bg-warning rounded-pill me-2">{{ $pendingOvertimeApprovalCount }}</span>
                                                        <a href="{{ Auth::user()->jabatan === 'manager' ? route('overtimes.approval.manager.list') : route('overtimes.approval.asisten.list') }}"
                                                            class="btn btn-sm btn-outline-primary">Proses</a>
                                                    </div>
                                                </li>
                                            @endif
                                            @if (Auth::user()->jabatan !== 'manager' &&
                                                    isset($pendingCorrectionApprovalCount) &&
                                                    $pendingCorrectionApprovalCount > 0)
                                                <li
                                                    class="list-group-item d-flex justify-content-between align-items-center">
                                                    Koreksi Absensi Menunggu Persetujuan
                                                    <div>
                                                        <span
                                                            class="badge bg-warning rounded-pill me-2">{{ $pendingCorrectionApprovalCount }}</span>
                                                        <a href="{{ route('attendance_corrections.approval.list') }}"
                                                            class="btn btn-sm btn-outline-primary">Proses</a>
                                                    </div>
                                                </li>
                                            @endif
                                            @if (isset($pendingTimesheetApprovalCount) && $pendingTimesheetApprovalCount > 0)
                                                <li
                                                    class="list-group-item d-flex justify-content-between align-items-center">
                                                    Timesheet Bulanan Menunggu Persetujuan
                                                    <div>
                                                        <span
                                                            class="badge bg-warning rounded-pill me-2">{{ $pendingTimesheetApprovalCount }}</span>
                                                        <a href="{{ Auth::user()->jabatan === 'manager' ? route('monthly_timesheets.approval.manager.list') : route('monthly_timesheets.approval.asisten.list') }}"
                                                            class="btn btn-sm btn-outline-primary">Proses</a>
                                                    </div>
                                                </li>
                                            @endif
                                            @if (
                                                !(
                                                    (isset($pendingLeaveApprovalCount) && $pendingLeaveApprovalCount > 0) ||
                                                    (isset($pendingOvertimeApprovalCount) && $pendingOvertimeApprovalCount > 0) ||
                                                    (Auth::user()->jabatan !== 'manager' &&
                                                        isset($pendingCorrectionApprovalCount) &&
                                                        $pendingCorrectionApprovalCount > 0) ||
                                                    (isset($pendingTimesheetApprovalCount) && $pendingTimesheetApprovalCount > 0)
                                                ))
                                                <li class="list-group-item text-center text-muted">Tidak ada tugas
                                                    persetujuan saat ini.</li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endif
                        {{-- === AKHIR WIDGETS MANAJEMEN === --}}

                        {{-- === WIDGETS UNTUK ADMIN === --}}
                        @if (Auth::user()->role === 'admin')
                            {{-- Widget Statistik Umum Admin --}}
                            <div class="col-md-6 col-xl-4">
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="stats-icon blue"> <i class="bi bi-people-fill fs-3"></i> </div>
                                            <div class="ms-3">
                                                <h6 class="text-muted font-semibold">Total Pengguna Lain</h6>
                                                <h5 class="font-extrabold mb-0">{{ $totalActiveUsers ?? 0 }}</h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="stats-icon green"> <i class="bi bi-building fs-3"></i> </div>
                                            <div class="ms-3">
                                                <h6 class="text-muted font-semibold">Total Vendor</h6>
                                                <h5 class="font-extrabold mb-0">{{ $totalVendors ?? 0 }}</h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="stats-icon red"> <i class="bi bi-calendar-plus fs-3"></i> </div>
                                            <div class="ms-3">
                                                <h6 class="text-muted font-semibold">Pengajuan Baru Hari Ini</h6>
                                                <p class="mb-0"><small>Cuti: {{ $newLeaveApplicationsToday ?? 0 }} |
                                                        Lembur: {{ $newOvertimeApplicationsToday ?? 0 }}</small></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{-- Widget Akses Cepat Administrasi --}}
                            <div class="col-12">
                                <div class="card shadow-sm">
                                    <div class="card-header">
                                        <h4 class="card-title">Akses Cepat Administrasi</h4>
                                    </div>
                                    <div class="card-body d-flex flex-wrap gap-2">
                                        <a href="{{ route('personil.index') }}" class="btn btn-outline-secondary"><i
                                                class="bi bi-person-gear"></i> User</a>
                                        <a href="{{ route('vendors.index') }}" class="btn btn-outline-secondary"><i
                                                class="bi bi-building"></i> Vendor</a>
                                        <a href="{{ route('holidays.index') }}" class="btn btn-outline-secondary"><i
                                                class="bi bi-calendar3-event"></i> Hari Libur</a>
                                        <a href="{{ route('overtimes.index') }}" class="btn btn-outline-secondary"><i
                                                class="bi bi-clock-history"></i> Lembur</a>
                                        <a href="{{ route('cuti.index') }}" class="btn btn-outline-secondary"><i
                                                class="bi bi-calendar-plus"></i> Cuti</a>
                                        <a href="{{ route('cuti-quota.index') }}" class="btn btn-outline-secondary"><i
                                                class="bi bi-journal-bookmark"></i> Kuota Cuti</a>
                                        <a href="{{ route('monthly_timesheets.index') }}"
                                            class="btn btn-outline-secondary"><i class="bi bi-card-checklist"></i> Kelola
                                            Timesheet</a>
                                    </div>
                                </div>
                            </div>
                        @endif
                        {{-- === AKHIR WIDGETS ADMIN === --}}
                    </div>
                </div>

                {{-- Kolom Kanan - Kalender Mini dan Statistik Cepat Hari Ini --}}
                <div class="col-lg-4 col-md-12">
                    {{-- Kalender Mini --}}
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h4 class="card-title text-center">{{ $calendarMonthName }} {{ $calendarYear }}</h4>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered text-center dashboard-calendar">
                                    <thead>
                                        <tr>
                                            @foreach ($dayNames as $dayName)
                                                <th scope="col">{{ $dayName }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $currentDay = 1;
                                            $todayDayNumeric = (int) \Carbon\Carbon::parse($todayDateString)->format(
                                                'd',
                                            );
                                            $currentMonthNumeric = (int) $calendarMonthNumeric;
                                            $currentYearNumeric = (int) $calendarYear;
                                        @endphp
                                        @for ($i = 0; $i < 6; $i++)
                                            <tr>
                                                @for ($j = 0; $j < 7; $j++)
                                                    @if ($i === 0 && $j < $firstDayOffset)
                                                        <td></td>
                                                    @elseif ($currentDay <= $daysInMonth)
                                                        @php
                                                            $loopDate = \Carbon\Carbon::create(
                                                                $currentYearNumeric,
                                                                $currentMonthNumeric,
                                                                $currentDay,
                                                            );
                                                            $loopDateString = $loopDate->format('Y-m-d');
                                                            $isWeekend = $loopDate->isWeekend();
                                                            $isToday = $loopDateString === $todayDateString;
                                                            $isHoliday = isset($holidayDates[$loopDateString]);
                                                            $cellClass = '';
                                                            $title = '';

                                                            if ($isToday && $isHoliday) {
                                                                $cellClass = 'holiday-today';
                                                                $title = $holidayDates[$loopDateString];
                                                            } elseif ($isToday) {
                                                                $cellClass = 'current-day';
                                                            } elseif ($isHoliday) {
                                                                $cellClass = 'holiday';
                                                                $title = $holidayDates[$loopDateString];
                                                            } elseif ($isWeekend) {
                                                                // Tambahkan kondisi untuk weekend
                                                                $cellClass = 'weekend-day';
                                                                $title = 'Akhir Pekan';
                                                            }
                                                        @endphp
                                                        <td class="{{ $cellClass }}"
                                                            {{ $title ? 'data-bs-toggle="tooltip" title="' . htmlspecialchars($title) . '"' : '' }}>
                                                            {{ $currentDay }}
                                                        </td>
                                                        @php $currentDay++; @endphp
                                                    @else
                                                        <td></td>
                                                    @endif
                                                @endfor
                                            </tr>
                                            @if ($currentDay > $daysInMonth)
                                                @break
                                            @endif
                                        @endfor
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Statistik Cepat Hari Ini (untuk Admin & Manajemen) --}}
                    @if (Auth::user()->role === 'admin' || Auth::user()->role === 'manajemen')
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h4 class="card-title">Statistik Cepat Hari Ini</h4>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Karyawan Hadir
                                        <span
                                            class="badge bg-success rounded-pill">{{ $employeesPresentToday ?? 0 }}</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Karyawan Cuti/Sakit/Dinas
                                        <span
                                            class="badge bg-info rounded-pill">{{ $employeesOnLeaveOrDutyToday ?? 0 }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection

@push('styles')
    <style>
        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: .5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #eee;
        }

        .stats-icon i {
            color: #fff;
        }

        .stats-icon.purple {
            background-color: rgba(111, 66, 193, 0.18);
        }

        .stats-icon.purple i {
            color: #6f42c1;
        }

        .stats-icon.blue {
            background-color: rgba(13, 110, 253, 0.18);
        }

        .stats-icon.blue i {
            color: #0d6efd;
        }

        .stats-icon.green {
            background-color: rgba(25, 135, 84, 0.18);
        }

        .stats-icon.green i {
            color: #198754;
        }

        .stats-icon.red {
            background-color: rgba(220, 53, 69, 0.18);
        }

        .stats-icon.red i {
            color: #dc3545;
        }

        .card-title {
            margin-bottom: 0.5rem;
        }

        .font-semibold {
            font-weight: 600 !important;
        }

        .font-extrabold {
            font-weight: 800 !important;
        }

        .btn-lg {
            font-weight: bold;
        }

        /* Kalender Mini Responsif */
        .dashboard-calendar {
            table-layout: fixed;
            width: 100%;
            border-collapse: collapse;
        }

        .dashboard-calendar th,
        .dashboard-calendar td {
            padding: 0.3rem 0.15rem;
            font-size: 0.78rem;
            height: 35px;
            vertical-align: middle;
            text-align: center;
            border: 1px solid #dee2e6;
        }

        .dashboard-calendar th {
            font-weight: 600;
            background-color: #f8f9fa;
        }

        .dashboard-calendar td.current-day {
            font-weight: bold !important;
            border: 2px solid #0d6efd !important;
            background-color: #cfe2ff !important;
            color: #084298 !important;
            border-radius: 0.25rem;
        }

        .dashboard-calendar td.holiday {
            background-color: #f8d7da !important;
            /* Warna merah muda (danger light) */
            color: #842029 !important;
            /* Warna teks merah tua (danger dark) */
            font-weight: bold !important;
        }

        .dashboard-calendar td.weekend-day {
            /* Styling untuk akhir pekan */
            background-color: #ffe6e6 !important;
            /* Warna merah sangat muda */
            color: #c00000 !important;
            /* Warna teks merah sedikit lebih gelap */
        }

        .dashboard-calendar td.holiday-today {
            /* Prioritas tertinggi jika hari ini & libur */
            background-color: #dc3545 !important;
            /* Warna merah solid (danger) */
            color: #ffffff !important;
            border: 2px solid #b02a37 !important;
            font-weight: bold !important;
            border-radius: 0.25rem;
        }

        .dashboard-calendar td[data-bs-toggle="tooltip"]:hover {
            cursor: help;
        }

        .card-body.p-2 {
            padding: 0.5rem !important;
        }
    </style>
@endpush

@push('js')
    <script>
        // Skrip untuk jam digital
        function updateClock() {
            const clockElement = document.getElementById('clock');
            if (clockElement) {
                const now = new Date();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                clockElement.textContent = `${hours}:${minutes}:${seconds}`;
            }
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Inisialisasi Tooltip Bootstrap 5 untuk kalender dan elemen lain
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
@endpush

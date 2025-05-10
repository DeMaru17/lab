{{-- resources/views/monthly_timesheets/index.blade.php --}}
@extends('layout.app')

@section('content')
    <div id="main">
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        {{-- Sesuaikan Judul berdasarkan role --}}
                        @if (Auth::user()->role === 'personil')
                            <h3>Rekap Timesheet Saya</h3>
                            <p class="text-subtitle text-muted">Daftar rekapitulasi timesheet bulanan Anda.</p>
                        @else
                            <h3>Rekap Timesheet Bulanan</h3>
                            <p class="text-subtitle text-muted">Daftar rekapitulasi timesheet bulanan karyawan.</p>
                        @endif
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">
                                    @if (Auth::user()->role === 'personil')
                                        Rekap Timesheet Saya
                                    @else
                                        Rekap Timesheet
                                    @endif
                                </li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bagian Filter --}}
        {{-- Filter hanya ditampilkan jika bukan personil --}}
        @if (Auth::user()->role !== 'personil')
            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Filter Data Timesheet</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('monthly_timesheets.index') }}" method="GET" class="form">
                            <div class="row gy-2">
                                {{-- Filter Bulan & Tahun (Tetap ada untuk semua yang bisa filter) --}}
                                <div class="col-md-3 col-6">
                                    <label for="filter_month">Bulan</label>
                                    <select name="filter_month" id="filter_month" class="form-select form-select-sm">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" {{ $filterMonth == $m ? 'selected' : '' }}>
                                                {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-2 col-6">
                                    <label for="filter_year">Tahun</label>
                                    <select name="filter_year" id="filter_year" class="form-select form-select-sm">
                                        @for ($y = date('Y'); $y >= date('Y') - 5; $y--)
                                            <option value="{{ $y }}" {{ $filterYear == $y ? 'selected' : '' }}>
                                                {{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>

                                {{-- Filter Karyawan (Hanya Admin/Manajemen, sudah dicek di controller $usersForFilter akan kosong untuk personil) --}}
                                @if (Auth::user()->role === 'admin' || Auth::user()->role === 'manajemen')
                                    <div class="col-md-3 col-12">
                                        <label for="filter_user_id">Karyawan</label>
                                        <select name="filter_user_id" id="filter_user_id"
                                            class="form-select form-select-sm select2">
                                            <option value="">-- Semua Karyawan --</option>
                                            @foreach ($usersForFilter as $userFilter)
                                                <option value="{{ $userFilter->id }}"
                                                    {{ $filterUserId == $userFilter->id ? 'selected' : '' }}>
                                                    {{ $userFilter->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                {{-- Filter Vendor (Hanya Admin/Manajemen) --}}
                                @if (Auth::user()->role === 'admin' || Auth::user()->role === 'manajemen')
                                    <div class="col-md-2 col-12">
                                        <label for="filter_vendor_id">Vendor</label>
                                        <select name="filter_vendor_id" id="filter_vendor_id"
                                            class="form-select form-select-sm">
                                            <option value="">-- Semua Vendor --</option>
                                            <option value="is_null" {{ $filterVendorId == 'is_null' ? 'selected' : '' }}>
                                                Internal</option>
                                            @foreach ($vendorsForFilter as $vendor)
                                                <option value="{{ $vendor->id }}"
                                                    {{ $filterVendorId == $vendor->id ? 'selected' : '' }}>
                                                    {{ $vendor->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                {{-- Filter Status (Tetap ada untuk semua yang bisa filter) --}}
                                <div class="col-md-2 col-12">
                                    <label for="filter_status">Status</label>
                                    <select name="filter_status" id="filter_status" class="form-select form-select-sm">
                                        <option value="">-- Semua Status --</option>
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}"
                                                {{ $filterStatus == $status ? 'selected' : '' }}>
                                                @if ($status === 'pending_asisten')
                                                    Menunggu Asisten
                                                @elseif ($status === 'pending_manager')
                                                    Menunggu Manager
                                                @else
                                                    {{ Str::title(str_replace('_', ' ', $status)) }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-12 d-flex align-items-end mt-2">
                                    <button type="submit" class="btn btn-primary btn-sm me-1"><i class="bi bi-filter"></i>
                                        Filter</button>
                                    <a href="{{ route('monthly_timesheets.index') }}"
                                        class="btn btn-light-secondary btn-sm"><i class="bi bi-x-circle"></i> Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        @else
            {{-- Jika personil, tampilkan filter sederhana hanya untuk Bulan dan Tahun --}}
            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Filter Data</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('monthly_timesheets.index') }}" method="GET" class="form">
                            <div class="row gy-2">
                                <div class="col-md-3 col-6">
                                    <label for="filter_month">Bulan</label>
                                    <select name="filter_month" id="filter_month" class="form-select form-select-sm">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}"
                                                {{ $filterMonth == $m ? 'selected' : '' }}>
                                                {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-2 col-6">
                                    <label for="filter_year">Tahun</label>
                                    <select name="filter_year" id="filter_year" class="form-select form-select-sm">
                                        @for ($y = date('Y'); $y >= date('Y') - 5; $y--)
                                            <option value="{{ $y }}" {{ $filterYear == $y ? 'selected' : '' }}>
                                                {{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-3 col-12"> {{-- Filter Status untuk Personil juga --}}
                                    <label for="filter_status">Status</label>
                                    <select name="filter_status" id="filter_status" class="form-select form-select-sm">
                                        <option value="">-- Semua Status --</option>
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}"
                                                {{ $filterStatus == $status ? 'selected' : '' }}>
                                                @if ($status === 'pending_asisten')
                                                    Menunggu Asisten
                                                @elseif ($status === 'pending_manager')
                                                    Menunggu Manager
                                                @else
                                                    {{ Str::title(str_replace('_', ' ', $status)) }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end mt-2">
                                    <button type="submit" class="btn btn-primary btn-sm me-1"><i class="bi bi-filter"></i>
                                        Filter</button>
                                    <a href="{{ route('monthly_timesheets.index') }}"
                                        class="btn btn-light-secondary btn-sm"><i class="bi bi-x-circle"></i> Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        @endif
        {{-- === AKHIR BAGIAN FILTER === --}}

        <section class="section">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-8 col-12">
                            {{-- Judul tabel disesuaikan --}}
                            @if (Auth::user()->role === 'personil')
                                <h4 class="card-title">Rekap Timesheet Saya
                                @else
                                    <h4 class="card-title">Daftar Rekap Timesheet
                            @endif
                            @if (request('filter_month') && request('filter_year'))
                                ({{ \Carbon\Carbon::create(request('filter_year'), request('filter_month'), 1)->format('F Y') }})
                            @endif
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm" id="tableTimesheets">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Periode</th>
                                    @if (Auth::user()->role !== 'personil')
                                        {{-- Kolom ini tidak perlu untuk personil --}}
                                        <th>Nama Karyawan</th>
                                        <th>Vendor</th>
                                    @endif
                                    <th class="text-center">Hadir</th>
                                    <th class="text-center">Alpha</th>
                                    <th class="text-center">Total Lembur</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($timesheets as $timesheet)
                                    <tr>
                                        <td>{{ $loop->iteration + $timesheets->firstItem() - 1 }}</td>
                                        <td class="text-nowrap">
                                            {{ $timesheet->period_start_date ? $timesheet->period_start_date->format('d/m/y') : '?' }}
                                            -
                                            {{ $timesheet->period_end_date ? $timesheet->period_end_date->format('d/m/y') : '?' }}
                                        </td>
                                        @if (Auth::user()->role !== 'personil')
                                            <td>{{ $timesheet->user?->name ?? 'N/A' }}</td>
                                            <td>{{ $timesheet->user?->vendor?->name ?? 'Internal' }}</td>
                                        @endif
                                        <td class="text-center">{{ $timesheet->total_present_days }}</td>
                                        <td class="text-center">{{ $timesheet->total_alpha_days }}</td>
                                        <td class="text-nowrap text-center">{{ $timesheet->total_overtime_formatted }}
                                        </td>
                                        <td class="text-center">
                                            @php
                                                $statusClass = App\Helpers\StatusHelper::timesheetStatusColor(
                                                    $timesheet->status,
                                                );
                                                $statusText = '';
                                                switch ($timesheet->status) {
                                                    case 'generated':
                                                        $statusText = 'Generated';
                                                        break;
                                                    case 'pending_asisten':
                                                        $statusText = 'Menunggu Asisten';
                                                        break;
                                                    case 'pending_manager':
                                                        $statusText = 'Menunggu Manager';
                                                        break;
                                                    case 'approved':
                                                        $statusText = 'Disetujui';
                                                        break;
                                                    case 'rejected':
                                                        $statusText = 'Ditolak';
                                                        break;
                                                    default:
                                                        $statusText = Str::title(
                                                            str_replace('_', ' ', $timesheet->status),
                                                        );
                                                }
                                            @endphp
                                            <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                                            {{-- Tooltip info tambahan --}}
                                            @if ($timesheet->status == 'rejected' && $timesheet->rejecter)
                                                <i class="bi bi-info-circle-fill text-danger" data-bs-toggle="tooltip"
                                                    title="Ditolak oleh: {{ $timesheet->rejecter->name }} | Alasan: {{ Str::limit($timesheet->notes ?? 'Tidak ada catatan', 70) }}"></i>
                                            @elseif ($timesheet->status == 'approved' && $timesheet->approverManager)
                                                <i class="bi bi-check-circle-fill text-success" data-bs-toggle="tooltip"
                                                    title="Disetujui oleh: {{ $timesheet->approverManager->name }}"></i>
                                            @elseif ($timesheet->status == 'pending_manager' && $timesheet->approverAsisten)
                                                <i class="bi bi-check-circle text-info" data-bs-toggle="tooltip"
                                                    title="Disetujui Asisten: {{ $timesheet->approverAsisten->name }}"></i>
                                            @endif
                                        </td>
                                        <td class="text-center text-nowrap">
                                            {{-- Tombol Lihat Detail (Semua role yang bisa view) --}}
                                            @can('view', $timesheet)
                                                <a href="{{ route('monthly_timesheets.show', $timesheet->id) }}"
                                                    class="btn btn-info btn-sm d-inline-block me-1" data-bs-toggle="tooltip"
                                                    title="Lihat Detail">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            @endcan

                                            @if ($timesheet->status === 'approved')
                                                @can('export', $timesheet)
                                                    <a href="{{ route('monthly_timesheets.export', ['timesheet' => $timesheet->id, 'format' => 'pdf']) }}"
                                                        class="btn btn-light-secondary btn-sm d-inline-block me-1"
                                                        data-bs-toggle="tooltip" title="Unduh PDF" target="_blank">
                                                        <i class="bi bi-printer-fill"></i>
                                                    </a>
                                                @endcan
                                            @endif

                                            @if ($timesheet->status === 'rejected')
                                                @can('forceReprocess', $timesheet)
                                                    <form
                                                        action="{{ route('monthly_timesheets.force-reprocess', $timesheet->id) }}"
                                                        method="POST" class="d-inline"
                                                        onsubmit="return confirm('Apakah Anda yakin ingin memproses ulang timesheet ini? Ini akan menghitung ulang semua data dan mengembalikan statusnya ke Generated untuk alur persetujuan dari awal.');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-warning btn-sm"
                                                            data-bs-toggle="tooltip" title="Proses Ulang Timesheet Ditolak">
                                                            <i class="bi bi-arrow-clockwise"></i>
                                                        </button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ Auth::user()->role !== 'personil' ? '9' : '7' }}"
                                            class="text-center"> {{-- Sesuaikan colspan --}}
                                            Tidak ada data timesheet yang cocok dengan filter Anda.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $timesheets->appends(request()->query())->links() }}
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection



@push('js')
    {{-- Script untuk tooltip (diambil dari view Overtime) --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inisialisasi Tooltip Bootstrap 5
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                // Tambahkan opsi agar tooltip tidak hilang saat mouse keluar lalu masuk lagi cepat
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover' // Hanya muncul saat hover
                })
            });
        });
    </script>
    {{-- Jika menggunakan select2, tambahkan JS-nya --}}
    {{-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({ theme: 'bootstrap4' }); // Sesuaikan tema jika perlu
        });
    </script> --}}
@endpush

@push('styles')
    {{-- Jika menggunakan select2 --}}
    {{-- <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" /> --}}
    {{-- CSS tambahan jika ada --}}
@endpush

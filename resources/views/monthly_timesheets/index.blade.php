@extends('layout.app') {{-- Menggunakan layout utama Anda --}}

{{-- Judul Halaman (Opsional, bisa diatur di layout) --}}
{{-- @section('title', 'Rekap Timesheet Bulanan') --}}

@section('content')
<div id="main">
    {{-- Header Halaman & Breadcrumb (Mengikuti gaya Overtime) --}}
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Rekap Timesheet Bulanan</h3>
                    <p class="text-subtitle text-muted">
                        Daftar rekapitulasi timesheet bulanan karyawan.
                    </p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Rekap Timesheet</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    {{-- Akhir Header Halaman --}}

    {{-- Bagian Filter (Mengikuti gaya Overtime) --}}
    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Filter Data Timesheet</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('monthly_timesheets.index') }}" method="GET" class="form"> {{-- Sesuaikan route --}}
                    <div class="row gy-2"> {{-- gy-2 untuk gutter vertikal --}}

                        {{-- Filter Bulan & Tahun --}}
                        <div class="col-md-3 col-6"> {{-- Ukuran kolom disesuaikan --}}
                            <label for="filter_month">Bulan</label>
                            <select name="filter_month" id="filter_month" class="form-select form-select-sm"> {{-- form-select-sm --}}
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ $filterMonth == $m ? 'selected' : '' }}>
                                        {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                                    </option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-2 col-6"> {{-- Ukuran kolom disesuaikan --}}
                            <label for="filter_year">Tahun</label>
                            <select name="filter_year" id="filter_year" class="form-select form-select-sm"> {{-- form-select-sm --}}
                                @for ($y = date('Y'); $y >= date('Y') - 5; $y--)
                                    <option value="{{ $y }}" {{ $filterYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>

                        {{-- Filter Karyawan (Hanya Admin/Manajemen) --}}
                        @if(Auth::user()->role === 'admin' || Auth::user()->role === 'manajemen')
                            <div class="col-md-3 col-12">
                                <label for="filter_user_id">Karyawan</label>
                                <select name="filter_user_id" id="filter_user_id" class="form-select form-select-sm select2"> {{-- form-select-sm & select2 --}}
                                    <option value="">-- Semua Karyawan --</option>
                                    @foreach ($usersForFilter as $userFilter)
                                        <option value="{{ $userFilter->id }}" {{ $filterUserId == $userFilter->id ? 'selected' : '' }}>{{ $userFilter->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        {{-- Filter Vendor (Hanya Admin/Manajemen) --}}
                        @if(Auth::user()->role === 'admin' || Auth::user()->role === 'manajemen')
                            <div class="col-md-2 col-12">
                                <label for="filter_vendor_id">Vendor</label>
                                <select name="filter_vendor_id" id="filter_vendor_id" class="form-select form-select-sm"> {{-- form-select-sm --}}
                                    <option value="">-- Semua Vendor --</option>
                                    <option value="is_null" {{ $filterVendorId == 'is_null' ? 'selected' : '' }}>Internal</option>
                                    @foreach ($vendorsForFilter as $vendor)
                                        <option value="{{ $vendor->id }}" {{ $filterVendorId == $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        {{-- Filter Status --}}
                        <div class="col-md-2 col-12">
                            <label for="filter_status">Status</label>
                            <select name="filter_status" id="filter_status" class="form-select form-select-sm"> {{-- form-select-sm --}}
                                <option value="">-- Semua Status --</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" {{ $filterStatus == $status ? 'selected' : '' }}>
                                        {{ Str::title(str_replace('_', ' ', $status)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Tombol Filter & Reset --}}
                        <div class="col-md-12 d-flex align-items-end mt-2"> {{-- Tambah margin top jika perlu --}}
                            <button type="submit" class="btn btn-primary btn-sm me-1"><i class="bi bi-filter"></i> Filter</button>
                            <a href="{{ route('monthly_timesheets.index') }}" class="btn btn-light-secondary btn-sm"><i class="bi bi-x-circle"></i> Reset</a>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </section>
    {{-- === AKHIR BAGIAN FILTER === --}}

    {{-- Bagian Tabel Daftar Timesheet --}}
    <section class="section">
        <div class="card">
            <div class="card-header">
                 <div class="row">
                     <div class="col-md-8 col-12">
                         <h4 class="card-title">Daftar Rekap Timesheet
                             @if(request('filter_month') && request('filter_year'))
                                 ({{ \Carbon\Carbon::create(request('filter_year'), request('filter_month'), 1)->format('F Y') }})
                             @endif
                         </h4>
                     </div>
                     {{-- Optional: Quick Search jika diperlukan --}}
                     {{-- <div class="col-md-4 col-12"> ... form quick search ... </div> --}}
                 </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    {{-- Menggunakan gaya tabel dari Overtime --}}
                    <table class="table table-striped table-hover table-sm" id="tableTimesheets">
                        <thead>
                            <tr>
                                {{-- <th><input class="form-check-input" type="checkbox"></th> --}} {{-- Checkbox jika perlu bulk action --}}
                                <th>No</th>
                                <th>Periode</th>
                                <th>Nama Karyawan</th>
                                <th>Vendor</th>
                                <th>Hadir</th>
                                <th>Alpha</th>
                                <th>Total Lembur</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($timesheets as $timesheet)
                                <tr>
                                    {{-- <td><input class="form-check-input item-checkbox" type="checkbox" value="{{ $timesheet->id }}"></td> --}}
                                    <td>{{ $loop->iteration + $timesheets->firstItem() - 1 }}</td>
                                    <td class="text-nowrap">{{ $timesheet->period_start_date->format('d/m/y') }} - {{ $timesheet->period_end_date->format('d/m/y') }}</td>
                                    <td>{{ $timesheet->user->name ?? 'N/A' }}</td>
                                    {{-- Menampilkan Vendor dari user, atau 'Internal' --}}
                                    <td>{{ $timesheet->user->vendor->name ?? 'Internal' }}</td>
                                    <td class="text-center">{{ $timesheet->total_present_days }}</td>
                                    <td class="text-center">{{ $timesheet->total_alpha_days }}</td>
                                    <td class="text-nowrap">{{ $timesheet->total_overtime_formatted }}</td>
                                    <td class="text-center">
                                        {{-- Logika badge status disamakan --}}
                                        @php
                                            $statusClass = '';
                                            $statusText = Str::title(str_replace('_', ' ', $timesheet->status));
                                            switch ($timesheet->status) {
                                                case 'generated': $statusClass = 'bg-light-secondary text-dark'; break; // Warna beda untuk generated
                                                case 'pending_asisten': $statusClass = 'bg-warning'; $statusText = 'Menunggu Asisten'; break;
                                                case 'pending_manager_approval': $statusClass = 'bg-info'; $statusText = 'Menunggu Manager'; break;
                                                case 'approved': $statusClass = 'bg-success'; break;
                                                case 'rejected': $statusClass = 'bg-danger'; break;
                                                default: $statusClass = 'bg-dark';
                                            }
                                        @endphp
                                        <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                                         {{-- Info tambahan ttg approval/reject --}}
                                         @if ($timesheet->status == 'rejected' && $timesheet->rejecter)
                                            <i class="bi bi-info-circle-fill text-danger" data-bs-toggle="tooltip" title="Ditolak oleh: {{ $timesheet->rejecter->name }} | Alasan: {{ Str::limit($timesheet->notes, 50) }}"></i>
                                         @elseif ($timesheet->status == 'approved' && $timesheet->approverManager)
                                             <i class="bi bi-check-circle-fill text-success" data-bs-toggle="tooltip" title="Disetujui oleh: {{ $timesheet->approverManager->name }}"></i>
                                         @elseif ($timesheet->status == 'pending_manager_approval' && $timesheet->approverAsisten)
                                              <i class="bi bi-check-circle text-info" data-bs-toggle="tooltip" title="Disetujui Asisten: {{ $timesheet->approverAsisten->name }}"></i>
                                         @endif
                                    </td>
                                    <td class="text-nowrap"> {{-- Aksi --}}
                                        {{-- Tombol Lihat Detail --}}
                                         <a href="{{ route('monthly_timesheets.show', $timesheet->id) }}" class="btn btn-info btn-sm d-inline-block me-1" data-bs-toggle="tooltip" title="Lihat Detail">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        {{-- Tombol Export PDF (jika status approved) --}}
                                        @if($timesheet->status === 'approved')
                                            {{-- @can('exportPdf', $timesheet) --}} {{-- Idealnya pakai Policy --}}
                                             <a href="{{ route('monthly-timesheets.export.pdf', $timesheet->id) }}" class="btn btn-light btn-sm d-inline-block me-1" data-bs-toggle="tooltip" title="Unduh PDF" target="_blank">
                                                <i class="bi bi-printer-fill"></i>
                                            </a>
                                            {{-- @endcan --}}
                                        @endif
                                        {{-- Tombol lain jika perlu --}}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    {{-- Sesuaikan colspan --}}
                                    <td colspan="9" class="text-center">Tidak ada data timesheet yang cocok dengan filter Anda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Link Pagination --}}
                <div class="mt-3">
                    {{ $timesheets->links() }}
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('js')
    {{-- Script untuk tooltip (diambil dari view Overtime) --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
             // Inisialisasi Tooltip Bootstrap 5
             var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
             var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
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

@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@push('css')
    {{-- CSS Opsional untuk Simple DataTables --}}
    {{-- <link rel="stylesheet" href="{{ asset('assets/extensions/simple-datatables/style.css') }}"> --}}
    {{-- <link rel="stylesheet" href="{{ asset('assets/compiled/css/table-datatable.css') }}"> --}}
    <style>
        /* Mengadopsi style dari overtimes/index.blade.php dan approval_list */
        .table th, .table td {
            vertical-align: middle;
            font-size: 0.9rem;
            padding: 0.6rem 0.75rem;
        }
        .detail-label {
            font-weight: 500;
            color: #6c757d;
            display: inline-block;
            width: 80px; /* Sesuaikan lebar */
            font-size: 0.85rem;
        }
        .detail-value {
            font-weight: bold;
            font-size: 0.85rem;
        }
        .badge {
            font-size: 0.8em;
            padding: 0.4em 0.6em;
            text-transform: capitalize; /* Agar status seperti 'pending' jadi 'Pending' */
        }
         .text-muted small {
             font-size: 0.8rem;
        }
         /* --- CSS untuk Tabel Responsif (Sama seperti approval_list) --- */
         @media (max-width: 767px) {
            .table thead { display: none; }
            .table, .table tbody, .table tr, .table td { display: block; width: 100%; }
            .table tr { margin-bottom: 1rem; border: 1px solid #dee2e6; border-radius: .25rem; overflow: hidden; }
            .table td { text-align: right; padding-left: 50%; position: relative; border-top: none; min-height: 38px;}
            .table td:last-child { border-bottom: none; }
            .table td::before { content: attr(data-label); position: absolute; left: 0.75rem; width: 45%; padding-right: 10px; white-space: nowrap; text-align: left; font-weight: bold; color: #495057; }
             /* Penanganan khusus kolom status */
             .table td.status-cell { text-align: center; padding-left: 0; }
             .table td.status-cell::before { display: none; }
             /* Jika ada kolom aksi di masa depan */
             /* .table td.action-buttons-user { text-align: center; padding-left: 0; } */
             /* .table td.action-buttons-user::before { display: none; } */
             /* .table td.action-buttons-user form, .table td.action-buttons-user button { margin-bottom: 5px; } */
        }
        /* --- Akhir CSS Responsif --- */
    </style>
@endpush

@section('content')
    <div id="main">
        {{-- Header Halaman & Breadcrumb --}}
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Daftar Koreksi Absensi Saya</h3>
                        <p class="text-subtitle text-muted">Riwayat pengajuan koreksi absensi yang telah Anda buat.</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Koreksi Absensi Saya</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        {{-- Akhir Header Halaman --}}

         {{-- Tombol Ajukan Koreksi Baru --}}
         <div class="mb-3">
            <a href="{{ route('attendance_corrections.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Ajukan Koreksi Baru
            </a>
        </div>

        {{-- Bagian Filter (Contoh Filter Status) --}}
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Filter Riwayat Koreksi</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('attendance_corrections.index') }}" method="GET" class="form">
                        <div class="row gy-2">
                            {{-- Filter Status --}}
                            <div class="col-md-4 col-12">
                                <label for="filter_status">Status Pengajuan</label>
                                <select name="filter_status" id="filter_status" class="form-select form-select-sm">
                                    <option value="">-- Semua Status --</option>
                                    <option value="pending" {{ request('filter_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="approved" {{ request('filter_status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="rejected" {{ request('filter_status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                </select>
                            </div>
                            {{-- Tombol Filter & Reset --}}
                            <div class="col-md-3 col-12 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm me-1"><i class="bi bi-filter"></i> Filter</button>
                                <a href="{{ route('attendance_corrections.index') }}" class="btn btn-light-secondary btn-sm"><i class="bi bi-x-circle"></i> Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        {{-- === AKHIR BAGIAN FILTER === --}}


        {{-- Bagian Tabel Daftar Koreksi --}}
        <section class="section">
            <div class="card">
                <div class="card-header">
                     <h4 class="card-title">Riwayat Pengajuan Koreksi</h4>
                     {{-- Mungkin tambahkan Quick Search di sini jika perlu --}}
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm" id="table-user-corrections"> {{-- table-sm untuk lebih ringkas --}}
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tgl Koreksi</th>
                                    <th>Detail Pengajuan</th>
                                    <th>Alasan</th>
                                    <th>Tgl Diajukan</th>
                                    <th>Status</th>
                                    <th>Diproses Oleh</th>
                                    <th>Tgl Proses</th>
                                    <th>Ket. Tolak</th>
                                    {{-- <th>Aksi</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($userCorrections as $index => $correction)
                                    <tr>
                                        <td data-label="#">{{ $userCorrections->firstItem() + $index }}</td>
                                        <td data-label="Tgl Koreksi">{{ $correction->correction_date->format('d M Y') }}</td>
                                        <td data-label="Detail Pengajuan">
                                            @if($correction->requested_clock_in)
                                                <div><span class="detail-label">Jam Masuk:</span> <span class="detail-value">{{ \Carbon\Carbon::parse($correction->requested_clock_in)->format('H:i') }}</span></div>
                                            @endif
                                            @if($correction->requested_clock_out)
                                                 <div><span class="detail-label">Jam Keluar:</span> <span class="detail-value">{{ \Carbon\Carbon::parse($correction->requested_clock_out)->format('H:i') }}</span></div>
                                            @endif
                                            @if($correction->requestedShift)
                                                 <div><span class="detail-label">Shift:</span> <span class="detail-value">{{ $correction->requestedShift->name }}</span></div>
                                            @endif
                                        </td>
                                        <td data-label="Alasan"><span data-bs-toggle="tooltip" title="{{ $correction->reason }}">{{ Str::limit($correction->reason, 50) }}</span></td>
                                        <td data-label="Tgl Diajukan">{{ $correction->created_at->format('d M Y H:i') }}</td>
                                        <td data-label="Status" class="status-cell">
                                            @php
                                                $statusClass = '';
                                                $statusText = Str::title($correction->status); // Default text
                                                switch ($correction->status) {
                                                    case 'pending': $statusClass = 'bg-warning'; break;
                                                    case 'approved': $statusClass = 'bg-success'; break;
                                                    case 'rejected': $statusClass = 'bg-danger'; break;
                                                    default: $statusClass = 'bg-secondary';
                                                }
                                            @endphp
                                            <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                                        </td>
                                        <td data-label="Diproses Oleh">{{ $correction->processor->name ?? '-' }}</td>
                                        <td data-label="Tgl Proses">{{ $correction->processed_at ? $correction->processed_at->format('d M Y H:i') : '-' }}</td>
                                        <td data-label="Ket. Tolak">{{ $correction->reject_reason ?? '-' }}</td>
                                        {{-- <td data-label="Aksi" class="action-buttons-user"> --}}
                                            {{-- Tombol Detail? --}}
                                        {{-- </td> --}}
                                    </tr>
                                @empty
                                    <tr>
                                        {{-- colspan disesuaikan dengan jumlah kolom thead --}}
                                        <td colspan="9" class="text-center">Tidak ada data pengajuan koreksi ditemukan @if(request('filter_status')) dengan status "{{ request('filter_status') }}" @endif.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{-- Pagination Links --}}
                    <div class="mt-3">
                        {{ $userCorrections->links() }}
                    </div>
                </div>
            </div>
        </section>
        {{-- Akhir Bagian Tabel --}}

    </div>
@endsection

@push('js')
    {{-- <script src="{{ asset('assets/extensions/simple-datatables/umd/simple-datatables.js') }}"></script> --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Inisialisasi Tooltip untuk alasan yang panjang
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                // Pastikan Bootstrap Tooltip sudah dimuat di layout utama
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                }
                return null; // Kembalikan null jika Bootstrap atau Tooltip tidak ada
            }).filter(Boolean); // Hapus null dari array

            // Inisialisasi Simple DataTables jika diperlukan
            // const dataTable = new simpleDatatables.DataTable("#table-user-corrections");
        });
    </script>
@endpush

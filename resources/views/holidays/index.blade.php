{{-- resources/views/holidays/index.blade.php --}}
@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@php use Carbon\Carbon; @endphp {{-- Import Carbon untuk tahun --}}

@section('content')
<div id="main">
    {{-- Header Halaman & Breadcrumb --}}
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Manajemen Hari Libur</h3>
                    <p class="text-subtitle text-muted">Kelola daftar hari libur nasional dan cuti bersama.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Hari Libur</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    {{-- Akhir Header Halaman --}}

     {{-- Tombol Tambah Baru --}}
     <div class="mb-3">
         <a href="{{ route('holidays.create') }}" class="btn btn-primary">
             <i class="bi bi-plus-lg"></i> Tambah Hari Libur
         </a>
     </div>

    {{-- Bagian Tabel Daftar Hari Libur --}}
    <section class="section">
        <div class="card">
            <div class="card-header">
                 <div class="row">
                    <div class="col-md-6 col-12">
                        <h4 class="card-title">Daftar Hari Libur Tahun {{ $selectedYear }}</h4>
                    </div>
                    {{-- Filter Tahun --}}
                    <div class="col-md-6 col-12">
                        <form action="{{ route('holidays.index') }}" method="GET" class="d-flex justify-content-end">
                            <div class="input-group w-auto">
                                <label for="year" class="input-group-text">Tahun:</label>
                                <select name="year" id="year" class="form-select" onchange="this.form.submit()">
                                    @php
                                        $currentYear = Carbon::now()->year;
                                        // Tampilkan beberapa tahun ke belakang dan ke depan
                                        $startYear = $currentYear - 5;
                                        $endYear = $currentYear + 2;
                                    @endphp
                                    @for ($year = $endYear; $year >= $startYear; $year--)
                                        <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>
                                            {{ $year }}
                                        </option>
                                    @endfor
                                </select>
                                {{-- <button class="btn btn-primary btn-sm" type="submit">Filter</button> --}}
                            </div>
                        </form>
                    </div>
                 </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="tableHolidays">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Nama Hari Libur</th>
                                <th style="min-width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($holidays as $index => $holiday)
                                <tr>
                                    <td>{{ $loop->iteration + $holidays->firstItem() - 1 }}</td>
                                    <td>{{ $holiday->tanggal ? $holiday->tanggal->format('d F Y') : '-' }} <span class="text-muted">({{ $holiday->tanggal ? $holiday->tanggal->isoFormat('dddd') : '' }})</span></td>
                                    <td>{{ $holiday->nama_libur }}</td>
                                    <td class="text-nowrap">
                                        {{-- Tombol Edit --}}
                                        <a href="{{ route('holidays.edit', $holiday->tanggal->format('Y-m-d')) }}" {{-- Kirim tanggal sbg ID --}}
                                           class="btn btn-warning btn-sm d-inline-block me-1"
                                           data-bs-toggle="tooltip" title="Edit Hari Libur">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        {{-- Tombol Hapus (Gunakan form) --}}
                                        <form action="{{ route('holidays.destroy', $holiday->tanggal->format('Y-m-d')) }}" {{-- Kirim tanggal sbg ID --}}
                                              method="POST" class="d-inline-block delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm delete-button"
                                                    data-bs-toggle="tooltip" title="Hapus Hari Libur">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center">Belum ada data hari libur untuk tahun {{ $selectedYear }}.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Link Pagination --}}
                <div class="mt-3">
                    {{-- Append filter tahun ke pagination --}}
                    {{ $holidays->appends(['year' => $selectedYear])->links() }}
                </div>
            </div>
        </div>
    </section>
    {{-- Akhir Bagian Tabel --}}

</div>
@endsection

@push('js')
{{-- Pastikan SweetAlert2 sudah dimuat di layout --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inisialisasi Tooltip
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Event Listener untuk konfirmasi hapus
    const deleteButtons = document.querySelectorAll('.delete-button');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault(); // Mencegah form langsung submit
            const form = this.closest('form'); // Cari form terdekat
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Data hari libur ini akan dihapus!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit(); // Submit form jika dikonfirmasi
                }
            });
        });
    });
});
</script>
@endpush

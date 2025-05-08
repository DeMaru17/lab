{{-- Menggunakan layout utama Anda (misal: layout.app) --}}
@extends('layout.app')

{{-- Set Judul Halaman jika layout Anda mendukungnya --}}
{{-- @section('title', 'Detail Timesheet Bulanan') --}}

@section('content')
<div id="main">
    {{-- Header Halaman & Breadcrumb (mengikuti gaya Overtime) --}}
    <div class="page-heading">
        <div class="page-title mb-4">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Detail Timesheet Bulanan</h3>
                    {{-- Tambahkan null safety check untuk user --}}
                    <p class="text-subtitle text-muted">Periode: {{ $timesheet->period_start_date ? $timesheet->period_start_date->format('d M Y') : '?' }} - {{ $timesheet->period_end_date ? $timesheet->period_end_date->format('d M Y') : '?' }} | Karyawan: {{ $timesheet->user?->name ?? 'N/A' }}</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('monthly_timesheets.index') }}">Rekap Timesheet</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Detail</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    {{-- Akhir Header Halaman --}}

    <section class="section">
        {{-- 1. Bagian Ringkasan (Summary) dalam Card --}}
        <div class="card shadow mb-4"> {{-- Tambah class shadow jika diinginkan --}}
            <div class="card-header">
                <h4 class="card-title">Ringkasan Timesheet</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    {{-- Kolom Kiri: Info User & Status --}}
                    <div class="col-md-6">
                        <h6>Informasi Karyawan & Status</h6>
                        <table class="table table-sm table-borderless">
                             <tr><td style="width: 35%;">Nama</td><td>: {{ $timesheet->user?->name ?? 'N/A' }}</td></tr>
                             <tr><td>Jabatan</td><td>: {{ $timesheet->user?->jabatan ?? '-' }}</td></tr>
                             <tr><td>Vendor</td><td>: {{ $timesheet->user?->vendor?->name ?? 'Internal' }}</td></tr>
                             <tr><td>Status Timesheet</td><td>:
                                <span class="badge bg-{{ App\Helpers\StatusHelper::timesheetStatusColor($timesheet->status) }}">{{ Str::title(str_replace('_', ' ', $timesheet->status)) }}</span>
                             </td></tr>
                             {{-- Info Approval/Reject --}}
                            @if($timesheet->approved_at_asisten)
                                <tr><td>Disetujui Asisten</td><td>: {{ $timesheet->approverAsisten?->name ?? '-' }} ({{ $timesheet->approved_at_asisten->format('d/m/Y H:i') }})</td></tr>
                            @endif
                            @if($timesheet->approved_at_manager)
                                 <tr><td>Disetujui Manager</td><td>: {{ $timesheet->approverManager?->name ?? '-' }} ({{ $timesheet->approved_at_manager->format('d/m/Y H:i') }})</td></tr>
                            @endif
                            @if($timesheet->rejected_at)
                                <tr><td>Ditolak Oleh</td><td>: {{ $timesheet->rejecter?->name ?? '-' }} ({{ $timesheet->rejected_at->format('d/m/Y H:i') }})</td></tr>
                                <tr><td>Alasan Penolakan</td><td>: {{ $timesheet->notes }}</td></tr>
                            @endif
                        </table>
                    </div>
                     {{-- Kolom Kanan: Total Perhitungan --}}
                     <div class="col-md-6">
                         <h6>Ringkasan Kehadiran & Lembur</h6>
                         <table class="table table-sm table-borderless">
                            <tr><td style="width: 50%;">Total Hari Kerja Periode</td><td>: {{ $timesheet->total_work_days }} hari</td></tr>
                            <tr><td>Total Kehadiran</td><td>: {{ $timesheet->total_present_days }} hari</td></tr>
                            <tr><td>Total Keterlambatan</td><td>: {{ $timesheet->total_late_days }} kali</td></tr>
                            <tr><td>Total Pulang Cepat</td><td>: {{ $timesheet->total_early_leave_days }} kali</td></tr>
                            <tr><td>Total Alpha/Tidak Lengkap</td><td>: {{ $timesheet->total_alpha_days }} hari</td></tr>
                            <tr><td>Total Cuti/Sakit</td><td>: {{ $timesheet->total_leave_days }} hari</td></tr>
                            <tr><td>Total Dinas Luar</td><td>: {{ $timesheet->total_duty_days }} hari</td></tr>
                            <tr><td>Total Lembur (Approved)</td><td>: {{ $timesheet->total_overtime_formatted }} ({{ $timesheet->total_overtime_occurrences }} kali)</td></tr>
                            <tr><td>Total Lembur di Hari Libur</td><td>: {{ $timesheet->total_holiday_duty_days }} hari</td></tr>
                        </table>
                     </div>
                </div>
            </div>
        </div>

        {{-- 2. Bagian Detail Absensi Harian dalam Card --}}
        <div class="card shadow mb-4"> {{-- Tambah class shadow jika diinginkan --}}
            <div class="card-header">
                 <h4 class="card-title">Detail Absensi Harian</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    {{-- Menggunakan gaya tabel Overtime --}}
                    <table class="table table-striped table-hover table-sm" id="dataTableDaily" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Shift</th>
                                <th>Jam Masuk</th>
                                <th>Jam Keluar</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                                <th>Dikoreksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($dailyAttendances as $attendance)
                                <tr>
                                    <td class="text-nowrap">{{ $attendance->attendance_date->format('d/m/Y (D)') }}</td>
                                    <td>{{ $attendance->shift?->name ?? '-' }}</td>
                                    <td class="text-nowrap">{{ $attendance->clock_in_time ? $attendance->clock_in_time->format('H:i:s') : '-' }}</td>
                                    <td class="text-nowrap">{{ $attendance->clock_out_time ? $attendance->clock_out_time->format('H:i:s') : '-' }}</td>
                                    <td>
                                        {{-- Menggunakan helper untuk warna badge --}}
                                        <span class="badge bg-{{ App\Helpers\StatusHelper::attendanceStatusColor($attendance->attendance_status) }}">
                                            {{ $attendance->attendance_status ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td>{{ $attendance->notes ?? '-' }}</td>
                                    <td>{{ $attendance->is_corrected ? 'Ya' : 'Tidak' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">Tidak ada data absensi untuk periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- 3. Bagian Tombol Aksi --}}
        <div class="mb-4 d-flex flex-wrap gap-2"> {{-- Menggunakan flexbox & gap --}}
            {{-- Tombol Aksi Asisten --}}
            @can('approveAsisten', $timesheet)
                @if(in_array($timesheet->status, ['generated', 'rejected']))
                    <form action="{{ route('monthly-timesheets.approval.asisten.approve', ['timesheet' => $timesheet->id]) }}" method="POST" class="approve-form">
                        @csrf
                        @method('PUT')
                        <button type="submit" class="btn btn-success btn-sm approve-button">
                            <i class="bi bi-check-lg"></i> Approve (Asisten)
                        </button>
                    </form>
                @endif
            @endcan

            {{-- Tombol Aksi Manager --}}
             @can('approveManager', $timesheet)
                @if(in_array($timesheet->status, ['pending_manager_approval', 'rejected']))
                     <form action="{{ route('monthly-timesheets.approval.manager.approve', ['timesheet' => $timesheet->id]) }}" method="POST" class="approve-form">
                        @csrf
                        @method('PUT')
                        <button type="submit" class="btn btn-primary btn-sm approve-button">
                            <i class="bi bi-check-all"></i> Approve Final (Manager)
                        </button>
                    </form>
                @endif
            @endcan

            {{-- Tombol Aksi Reject (Bisa oleh Asisten/Manager) --}}
             @can('reject', $timesheet)
                 @if(in_array($timesheet->status, ['generated', 'pending_manager_approval']))
                     {{-- Tombol untuk membuka modal reject --}}
                     <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <i class="bi bi-x-lg"></i> Tolak Timesheet
                     </button>
                @endif
            @endcan

            {{-- Tombol Export PDF --}}
             @can('exportPdf', $timesheet)
                 @if($timesheet->status === 'approved')
                    <a href="{{ route('monthly-timesheets.export.pdf', ['timesheet' => $timesheet->id, 'format' => 'pdf']) }}" class="btn btn-light btn-sm" target="_blank">
                         <i class="bi bi-printer-fill"></i> Export PDF
                    </a>
                 @endif
            @endcan

            {{-- Tombol Kembali (sesuaikan route tujuan jika perlu) --}}
            <a href="{{ url()->previous() }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>

        {{-- Modal untuk Alasan Reject (Gaya Bootstrap 5) --}}
        @can('reject', $timesheet)
            @if(in_array($timesheet->status, ['generated', 'pending_manager_approval']))
            <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ route('monthly-timesheets.approval.reject', ['timesheet' => $timesheet->id]) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h5 class="modal-title" id="rejectModalLabel">Tolak Timesheet</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                                    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" required minlength="5">{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-danger">Tolak</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif
        @endcan

    </section> {{-- Akhir Section Utama --}}
</div> {{-- Akhir #main --}}
@endsection

@push('js')
{{-- Script untuk tooltip & konfirmasi SweetAlert (diambil dari view Overtime) --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
         // Inisialisasi Tooltip Bootstrap 5
         var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
         var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
             return new bootstrap.Tooltip(tooltipTriggerEl, { trigger: 'hover' });
         });

         // Konfirmasi untuk tombol Approve
         const approveForms = document.querySelectorAll('.approve-form');
         approveForms.forEach(form => {
             form.addEventListener('submit', function(event) {
                 event.preventDefault(); // Hentikan submit default
                 const buttonText = event.submitter ? event.submitter.innerText : 'Menyetujui'; // Ambil teks tombol
                 Swal.fire({
                     title: 'Konfirmasi Persetujuan',
                     text: `Anda yakin ingin ${buttonText.toLowerCase()} timesheet ini?`,
                     icon: 'question',
                     showCancelButton: true,
                     confirmButtonColor: '#3085d6', // Biru
                     cancelButtonColor: '#6c757d', // Abu-abu
                     confirmButtonText: 'Ya, Setuju!',
                     cancelButtonText: 'Batal'
                 }).then((result) => {
                     if (result.isConfirmed) {
                         form.submit(); // Lanjutkan submit jika dikonfirmasi
                     }
                 });
             });
         });

        // Tambahkan JS lain jika perlu (misal untuk DataTables di tabel detail)

    });
</script>
@endpush

{{-- @push('styles') --}}
{{-- CSS tambahan jika ada --}}
{{-- @endpush --}}

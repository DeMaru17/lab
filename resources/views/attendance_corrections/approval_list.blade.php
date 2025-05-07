{{-- resources/views/attendance_corrections/approval_list.blade.php --}}
@extends('layout.app')

@push('css')
    {{-- Tambahkan CSS untuk Simple DataTables jika ingin menggunakannya nanti --}}
    {{-- <link rel="stylesheet" href="{{ asset('assets/extensions/simple-datatables/style.css') }}"> --}}
    {{-- <link rel="stylesheet" href="{{ asset('assets/compiled/css/table-datatable.css') }}"> --}}
    <style>
        /* Style untuk modal reject */
        .swal2-textarea {
            min-height: 100px;
        }
        /* Beri sedikit ruang antar tombol aksi */
        .action-buttons form {
            display: inline-block;
            margin-right: 5px;
        }
        .table th, .table td {
            vertical-align: middle; /* Agar konten sel sejajar vertikal */
        }
        .detail-label {
            font-weight: 500;
            color: #6c757d; /* Warna abu-abu */
            display: inline-block;
            width: 70px; /* Sesuaikan lebar label */
        }
        .detail-value {
            font-weight: bold;
        }
    </style>
@endpush

@section('content')
    <div id="main">
        {{-- Header Halaman & Breadcrumb --}}
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Persetujuan Koreksi Absensi</h3>
                        <p class="text-subtitle text-muted">Daftar pengajuan koreksi absensi yang memerlukan persetujuan Anda.</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Approval Koreksi Absensi</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Daftar Pengajuan Pending</h4>
                    {{-- TODO: Tambahkan form filter jika perlu (berdasarkan tanggal, nama, dll.) --}}
                </div>
                <div class="card-body">
                    @if($pendingCorrections->isEmpty())
                        <div class="alert alert-light-info color-info">
                            <i class="bi bi-exclamation-circle"></i> Tidak ada pengajuan koreksi yang menunggu persetujuan Anda saat ini.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="table-corrections"> {{-- Beri ID jika pakai datatables --}}
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Pengaju</th>
                                        <th>Jabatan</th>
                                        <th>Tgl Koreksi</th>
                                        <th>Detail Pengajuan</th>
                                        <th>Alasan</th>
                                        <th>Tgl Diajukan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pendingCorrections as $index => $correction)
                                        <tr>
                                            <td>{{ $pendingCorrections->firstItem() + $index }}</td>
                                            <td>{{ $correction->requester->name ?? 'N/A' }}</td>
                                            <td>{{ $correction->requester->jabatan ?? 'N/A' }}</td>
                                            <td>{{ $correction->correction_date->format('d M Y') }}</td>
                                            <td>
                                                {{-- Tampilkan detail jam & shift yang diajukan --}}
                                                @if($correction->requested_clock_in)
                                                    <div><span class="detail-label">Jam Masuk:</span> <span class="detail-value">{{ \Carbon\Carbon::parse($correction->requested_clock_in)->format('H:i') }}</span></div>
                                                @endif
                                                @if($correction->requested_clock_out)
                                                     <div><span class="detail-label">Jam Keluar:</span> <span class="detail-value">{{ \Carbon\Carbon::parse($correction->requested_clock_out)->format('H:i') }}</span></div>
                                                @endif
                                                @if($correction->requestedShift)
                                                     <div><span class="detail-label">Shift:</span> <span class="detail-value">{{ $correction->requestedShift->name }}</span></div>
                                                @endif
                                                {{-- Tampilkan data asli jika ada --}}
                                                @if($correction->originalAttendance)
                                                <hr class="my-1">
                                                <small class="text-muted">
                                                    (Asli:
                                                    In: {{ $correction->originalAttendance->clock_in_time ? \Carbon\Carbon::parse($correction->originalAttendance->clock_in_time)->format('H:i') : '-' }} |
                                                    Out: {{ $correction->originalAttendance->clock_out_time ? \Carbon\Carbon::parse($correction->originalAttendance->clock_out_time)->format('H:i') : '-' }} |
                                                    Status: {{ $correction->originalAttendance->attendance_status ?? '-' }}
                                                    )
                                                </small>
                                                @endif
                                            </td>
                                            <td>{{ $correction->reason }}</td>
                                            <td>{{ $correction->created_at->format('d M Y H:i') }}</td>
                                            <td class="action-buttons">
                                                {{-- Tombol Approve --}}
                                                <form action="{{ route('attendance_corrections.approval.approve', $correction->id) }}" method="POST" class="approve-form">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-success btn-sm" title="Setujui">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                </form>

                                                {{-- Tombol Reject (Trigger Modal) --}}
                                                <button type="button" class="btn btn-danger btn-sm reject-button"
                                                        data-correction-id="{{ $correction->id }}"
                                                        data-requester-name="{{ $correction->requester->name ?? 'N/A' }}"
                                                        data-correction-date="{{ $correction->correction_date->format('d M Y') }}"
                                                        title="Tolak">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        {{-- Pagination Links --}}
                        <div class="mt-3">
                            {{ $pendingCorrections->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>

    {{-- Hidden Form untuk Reject (di luar tabel) --}}
    <form id="reject-form" method="POST" style="display: none;">
        @csrf
        @method('PATCH')
        <input type="hidden" name="reject_reason" id="reject-reason-input">
    </form>

@endsection

@push('js')
    {{-- Pastikan SweetAlert2 sudah dimuat di layout utama --}}
    {{-- <script src="{{ asset('assets/extensions/sweetalert2/sweetalert2.min.js') }}"></script> --}}
    {{-- <script src="{{ asset('assets/extensions/simple-datatables/umd/simple-datatables.js') }}"></script> --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Inisialisasi Simple DataTables jika diperlukan
            // const dataTable = new simpleDatatables.DataTable("#table-corrections");

            // --- Konfirmasi Approve ---
            const approveForms = document.querySelectorAll('.approve-form');
            approveForms.forEach(form => {
                form.addEventListener('submit', function (event) {
                    event.preventDefault(); // Hentikan submit default
                    Swal.fire({
                        title: 'Setujui Koreksi Ini?',
                        text: "Data absensi asli akan diperbarui sesuai pengajuan ini.",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Ya, Setujui!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.submit(); // Lanjutkan submit form jika dikonfirmasi
                        }
                    });
                });
            });

            // --- Modal Input Alasan Reject ---
            const rejectButtons = document.querySelectorAll('.reject-button');
            const rejectForm = document.getElementById('reject-form');
            const rejectReasonInput = document.getElementById('reject-reason-input');

            rejectButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const correctionId = this.dataset.correctionId;
                    const requesterName = this.dataset.requesterName;
                    const correctionDate = this.dataset.correctionDate;
                    // Update action form reject sesuai ID koreksi
                    rejectForm.action = `/attendance-corrections/approval/${correctionId}/reject`; // Sesuaikan URL

                    Swal.fire({
                        title: `Tolak Koreksi ${requesterName}?`,
                        html: `Tanggal: <strong>${correctionDate}</strong><br>Masukkan alasan penolakan:`,
                        icon: 'warning',
                        input: 'textarea', // Gunakan textarea
                        inputPlaceholder: 'Masukkan alasan penolakan di sini...',
                        inputAttributes: {
                            'aria-label': 'Alasan penolakan',
                            'required': 'required', // Tambahkan validasi dasar
                            'minlength': 5 // Contoh validasi panjang minimal
                        },
                        showCancelButton: true,
                        confirmButtonColor: '#d33', // Warna merah untuk tolak
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Ya, Tolak!',
                        cancelButtonText: 'Batal',
                        inputValidator: (value) => { // Validasi input SweetAlert
                            if (!value) {
                                return 'Alasan penolakan wajib diisi!'
                            }
                            if (value.length < 5) {
                                return 'Alasan penolakan minimal 5 karakter.'
                            }
                        },
                        preConfirm: (reason) => {
                            // Masukkan alasan ke input hidden sebelum submit
                            rejectReasonInput.value = reason;
                            return true; // Lanjutkan submit
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            rejectForm.submit(); // Submit form reject
                        }
                    });
                });
            });
        });
    </script>
@endpush

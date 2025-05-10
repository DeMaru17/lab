@extends('layout.app')

@section('content')
    <div id="main">
        {{-- Header Halaman & Breadcrumb --}}
        <div class="page-heading">
            <div class="page-title mb-4">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Detail Timesheet Bulanan</h3>
                        <p class="text-subtitle text-muted">
                            Periode:
                            {{ $timesheet->period_start_date ? $timesheet->period_start_date->format('d M Y') : '?' }} -
                            {{ $timesheet->period_end_date ? $timesheet->period_end_date->format('d M Y') : '?' }}
                            <br>Karyawan: <strong>{{ $timesheet->user?->name ?? 'N/A' }}</strong>
                        </p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                                @if (Auth::user()->role === 'admin' || (Auth::user()->role === 'manajemen' && Auth::user()->id !== $timesheet->user_id))
                                    <li class="breadcrumb-item"><a href="{{ route('monthly_timesheets.index') }}">Rekap
                                            Timesheet</a></li>
                                @elseif(Auth::user()->id === $timesheet->user_id && Auth::user()->role === 'personil')
                                    <li class="breadcrumb-item"><a href="{{ route('monthly_timesheets.index') }}">Timesheet
                                            Saya</a></li>
                                @else
                                    <li class="breadcrumb-item"><a
                                            href="{{ url()->previous() != url()->current() ? url()->previous() : route('monthly_timesheets.index') }}">Kembali</a>
                                    </li>
                                @endif
                                <li class="breadcrumb-item active" aria-current="page">Detail</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        {{-- Akhir Header Halaman --}}

        <section class="section">
            @if ($timesheet->status === 'rejected')
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h4 class="alert-heading"><i class="bi bi-x-octagon-fill"></i> Timesheet Ditolak!</h4>
                    <p>Timesheet untuk periode ini telah ditolak oleh
                        <strong>{{ $timesheet->rejecter?->name ?? 'N/A' }}</strong> pada tanggal
                        {{ $timesheet->rejected_at ? $timesheet->rejected_at->format('d M Y H:i') : '-' }}.</p>
                    <p><strong>Alasan Penolakan:</strong>
                        {{ $timesheet->notes ?: 'Tidak ada alasan spesifik yang diberikan.' }}</p>
                    @if (Auth::id() == $timesheet->user_id)
                        <hr>
                        <p class="mb-0">
                            Silakan periksa detail absensi harian di bawah ini dan ajukan koreksi jika diperlukan.
                            Setelah semua koreksi Anda disetujui, timesheet ini akan diproses ulang secara otomatis oleh
                            sistem pada proses berikutnya, atau Anda dapat meminta Admin/Manager Anda untuk memproses ulang
                            lebih cepat jika mendesak.
                        </p>
                    @endif
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="card shadow mb-4">
                <div class="card-header">
                    <h4 class="card-title">Ringkasan Timesheet</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Informasi Karyawan & Status</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td style="width: 35%;">Nama</td>
                                    <td>: <strong>{{ $timesheet->user?->name ?? 'N/A' }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Jabatan</td>
                                    <td>: {{ $timesheet->user?->jabatan ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td>Vendor</td>
                                    <td>: {{ $timesheet->user?->vendor?->name ?? 'Internal' }}</td>
                                </tr>
                                <tr>
                                    <td>Status Timesheet</td>
                                    <td>:
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
                                                    $statusText = Str::title(str_replace('_', ' ', $timesheet->status));
                                            }
                                        @endphp
                                        <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                                    </td>
                                </tr>
                                @if ($timesheet->approved_at_asisten)
                                    <tr>
                                        <td>Disetujui Asisten</td>
                                        <td>: {{ $timesheet->approverAsisten?->name ?? '-' }}
                                            ({{ $timesheet->approved_at_asisten->format('d/m/Y H:i') }})</td>
                                    </tr>
                                @endif
                                @if ($timesheet->approved_at_manager)
                                    <tr>
                                        <td>Disetujui Manager</td>
                                        <td>: {{ $timesheet->approverManager?->name ?? '-' }}
                                            ({{ $timesheet->approved_at_manager->format('d/m/Y H:i') }})</td>
                                    </tr>
                                @endif
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Ringkasan Kehadiran & Lembur</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td style="width: 50%;">Total Hari Kerja Periode</td>
                                    <td>: {{ $timesheet->total_work_days }} hari</td>
                                </tr>
                                <tr>
                                    <td>Total Kehadiran</td>
                                    <td>: {{ $timesheet->total_present_days }} hari</td>
                                </tr>
                                <tr>
                                    <td>Total Keterlambatan</td>
                                    <td>: {{ $timesheet->total_late_days }} kali</td>
                                </tr>
                                <tr>
                                    <td>Total Pulang Cepat</td>
                                    <td>: {{ $timesheet->total_early_leave_days }} kali</td>
                                </tr>
                                <tr>
                                    <td>Total Alpha/Tidak Lengkap</td>
                                    <td>: {{ $timesheet->total_alpha_days }} hari</td>
                                </tr>
                                <tr>
                                    <td>Total Cuti/Sakit</td>
                                    <td>: {{ $timesheet->total_leave_days }} hari</td>
                                </tr>
                                <tr>
                                    <td>Total Dinas Luar</td>
                                    <td>: {{ $timesheet->total_duty_days }} hari</td>
                                </tr>
                                <tr>
                                    <td>Total Lembur (Approved)</td>
                                    <td>: {{ $timesheet->total_overtime_formatted }}
                                        ({{ $timesheet->total_overtime_occurrences }} kali)</td>
                                </tr>
                                <tr>
                                    <td>Total Lembur di Hari Libur</td>
                                    <td>: {{ $timesheet->total_holiday_duty_days }} hari</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header">
                    <h4 class="card-title">Detail Absensi Harian</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm" id="dataTableDaily" width="100%"
                            cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Shift</th>
                                    <th class="text-center">Jam Masuk</th>
                                    <th class="text-center">Jam Keluar</th>
                                    <th class="text-center">Foto Selfie</th> {{-- SATU KOLOM UNTUK TOMBOL MODAL --}}
                                    <th class="text-center">Status</th>
                                    <th>Keterangan</th>
                                    <th class="text-center">Dikoreksi</th>
                                    @if (Auth::id() == $timesheet->user_id && $timesheet->status === 'rejected')
                                        <th class="text-center">Aksi Koreksi</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Hitung colspan dinamis berdasarkan kondisi tombol Aksi Koreksi --}}
                                @php $colspanCount = (Auth::id() == $timesheet->user_id && $timesheet->status === 'rejected') ? 9 : 8; @endphp
                                @forelse ($dailyAttendances as $attendance)
                                    <tr>
                                        <td class="text-nowrap">{{ $attendance->attendance_date->format('d/m/Y (D)') }}
                                        </td>
                                        <td>{{ $attendance->shift?->name ?? '-' }}</td>
                                        <td class="text-nowrap text-center">
                                            {{ $attendance->clock_in_time ? $attendance->clock_in_time->format('H:i:s') : '-' }}
                                        </td>
                                        <td class="text-nowrap text-center">
                                            {{ $attendance->clock_out_time ? $attendance->clock_out_time->format('H:i:s') : '-' }}
                                        </td>
                                        {{-- Kolom Tombol Lihat Foto Selfie (Modal Trigger) --}}
                                        <td class="text-center">
                                            @php
                                                // Cek apakah ada foto masuk ATAU foto keluar yang valid
                                                $hasClockInPhoto =
                                                    $attendance->clock_in_photo_path &&
                                                    Storage::disk('public')->exists($attendance->clock_in_photo_path);
                                                $hasClockOutPhoto =
                                                    $attendance->clock_out_photo_path &&
                                                    Storage::disk('public')->exists($attendance->clock_out_photo_path);
                                            @endphp
                                            @if ($hasClockInPhoto || $hasClockOutPhoto)
                                                <button type="button"
                                                    class="btn btn-outline-info btn-sm p-1 view-selfie-btn"
                                                    data-bs-toggle="tooltip"
                                                    title="Lihat Foto Selfie: {{ $attendance->user?->name ?? $timesheet->user?->name }} ({{ $attendance->attendance_date->format('d M Y') }})"
                                                    data-name="{{ $attendance->user?->name ?? $timesheet->user?->name }}"
                                                    data-date="{{ $attendance->attendance_date->format('d M Y') }}"
                                                    data-clockin-img="{{ $hasClockInPhoto ? Storage::url($attendance->clock_in_photo_path) : '' }}"
                                                    data-clockin-time="{{ $attendance->clock_in_time ? $attendance->clock_in_time->format('H:i:s') : '-' }}"
                                                    data-clockout-img="{{ $hasClockOutPhoto ? Storage::url($attendance->clock_out_photo_path) : '' }}"
                                                    data-clockout-time="{{ $attendance->clock_out_time ? $attendance->clock_out_time->format('H:i:s') : '-' }}">
                                                    <i class="bi bi-images"></i>
                                                </button>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-{{ App\Helpers\StatusHelper::attendanceStatusColor($attendance->attendance_status) }}">
                                                {{ $attendance->attendance_status ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td>{{ $attendance->notes ?? '-' }}</td>
                                        <td class="text-center">
                                            @if ($attendance->is_corrected)
                                                <span class="badge bg-info">Ya</span>
                                            @else
                                                <span class="badge bg-light text-dark">Tidak</span>
                                            @endif
                                        </td>
                                        @if (Auth::id() == $timesheet->user_id && $timesheet->status === 'rejected')
                                            <td class="text-center">
                                                <a href="{{ route('attendance_corrections.create', ['attendance_date' => $attendance->attendance_date->format('Y-m-d')]) }}"
                                                    class="btn btn-warning btn-sm" data-bs-toggle="tooltip"
                                                    title="Ajukan Koreksi untuk tanggal ini">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $colspanCount }}" class="text-center">
                                            Tidak ada data absensi untuk periode ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Modal Selfie (diletakkan sekali di luar tabel, di akhir section atau sebelum @endsection) --}}
            <div class="modal fade" id="selfieModal" tabindex="-1" aria-labelledby="selfieModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered"> {{-- modal-xl untuk lebih lebar --}}
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="selfieModalLabel">Foto Selfie Absensi</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <h6 id="selfieModalEmployeeNameDate" class="mb-3 text-center">Nama - Tanggal</h6>
                            <div class="row">
                                <div class="col-md-6 text-center border-end">
                                    <p class="mb-1"><strong>Foto Masuk (<span
                                                id="selfieModalClockInTime">-</span>)</strong></p>
                                    <div
                                        style="min-height: 200px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; border-radius: .25rem; padding: 10px;">
                                        <img id="selfieModalClockInImage" src="#" alt="Foto Selfie Masuk"
                                            class="img-fluid rounded" style="max-height: 400px; display: none;">
                                        <p id="noSelfieModalClockInImage" class="text-muted"
                                            style="display: none; margin: auto;">Tidak ada foto.</p>
                                    </div>
                                </div>
                                <div class="col-md-6 text-center">
                                    <p class="mb-1"><strong>Foto Keluar (<span
                                                id="selfieModalClockOutTime">-</span>)</strong></p>
                                    <div
                                        style="min-height: 200px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; border-radius: .25rem; padding: 10px;">
                                        <img id="selfieModalClockOutImage" src="#" alt="Foto Selfie Keluar"
                                            class="img-fluid rounded" style="max-height: 400px; display: none;">
                                        <p id="noSelfieModalClockOutImage" class="text-muted"
                                            style="display: none; margin: auto;">Tidak ada foto.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Bagian Tombol Aksi Utama dan Modal Reject --}}
            {{-- (Kode untuk tombol Approve, Reject, Force Reprocess, Export PDF, dan Modal Reject seperti yang sudah ada sebelumnya) --}}
            <div class="mb-4 d-flex flex-wrap justify-content-start gap-2">
                @can('approveAsisten', $timesheet)
                    @if (in_array($timesheet->status, ['generated', 'rejected']))
                        <form
                            action="{{ route('monthly_timesheets.approval.asisten.approve', ['timesheet' => $timesheet->id]) }}"
                            method="POST" class="approve-form d-inline">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="btn btn-success btn-sm approve-button">
                                <i class="bi bi-check-lg"></i> Approve (Asisten)
                            </button>
                        </form>
                    @endif
                @endcan

                @can('approveManager', $timesheet)
                    @if (in_array($timesheet->status, ['pending_manager', 'rejected']))
                        <form
                            action="{{ route('monthly_timesheets.approval.manager.approve', ['timesheet' => $timesheet->id]) }}"
                            method="POST" class="approve-form d-inline">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="btn btn-primary btn-sm approve-button">
                                <i class="bi bi-check-all"></i> Approve Final (Manager)
                            </button>
                        </form>
                    @endif
                @endcan

                @can('reject', $timesheet)
                    @if (in_array($timesheet->status, ['generated', 'pending_manager']))
                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal"
                            data-bs-target="#rejectModal">
                            <i class="bi bi-x-lg"></i> Tolak Timesheet
                        </button>
                    @endif
                @endcan

                @if ($timesheet->status === 'rejected')
                    @can('forceReprocess', $timesheet)
                        <form action="{{ route('monthly-timesheets.force-reprocess', $timesheet->id) }}" method="POST"
                            class="d-inline"
                            onsubmit="return confirm('Apakah Anda yakin ingin memproses ulang timesheet ini? Ini akan menghitung ulang semua data dan mengembalikan statusnya ke Generated untuk alur persetujuan dari awal.');">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-sm" data-bs-toggle="tooltip"
                                title="Proses Ulang Timesheet Ditolak">
                                <i class="bi bi-arrow-clockwise"></i> Proses Ulang
                            </button>
                        </form>
                    @endcan
                @endif

                @if ($timesheet->status === 'approved')
                    @can('export', $timesheet)
                        <a href="{{ route('monthly_timesheets.export', ['timesheet' => $timesheet->id, 'format' => 'pdf']) }}"
                            class="btn btn-light-secondary btn-sm" target="_blank" data-bs-toggle="tooltip"
                            title="Unduh PDF">
                            <i class="bi bi-printer-fill"></i> Export PDF
                        </a>
                    @endcan
                @endif

                <a href="{{ url()->previous() != url()->current() ? url()->previous() : route('monthly_timesheets.index') }}"
                    class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>
            </div>

            @can('reject', $timesheet)
                @if (in_array($timesheet->status, ['generated', 'pending_manager']))
                    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form
                                    action="{{ route('monthly_timesheets.approval.reject', ['timesheet' => $timesheet->id]) }}"
                                    method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="rejectModalLabel">Tolak Timesheet</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label for="notes" class="form-label">Alasan Penolakan <span
                                                    class="text-danger">*</span></label>
                                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3"
                                                required minlength="5">{{ old('notes') }}</textarea>
                                            @error('notes')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-danger">Tolak</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            @endcan
        </section>
    </div>
@endsection

@push('js')
    {{-- Script tooltip & SweetAlert yang sudah ada --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inisialisasi Tooltip Bootstrap 5
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover'
                });
            });

            // Konfirmasi untuk tombol Approve Forms
            const approveForms = document.querySelectorAll('.approve-form');
            approveForms.forEach(form => {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    const buttonText = event.submitter ? event.submitter.innerText.trim() :
                        'Menyetujui';
                    Swal.fire({
                        title: 'Konfirmasi Persetujuan',
                        text: `Anda yakin ingin ${buttonText.toLowerCase()} timesheet ini?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#435ebe',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, Lanjutkan!', // Disesuaikan agar lebih umum
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });

            // Script untuk Modal Selfie
            const selfieModalElement = document.getElementById('selfieModal');
            if (selfieModalElement) {
                const selfieModal = new bootstrap.Modal(selfieModalElement);
                const modalEmployeeNameDate = document.getElementById('selfieModalEmployeeNameDate');
                const clockInImage = document.getElementById('selfieModalClockInImage');
                const noClockInImage = document.getElementById('noSelfieModalClockInImage');
                const clockInTime = document.getElementById('selfieModalClockInTime');
                const clockOutImage = document.getElementById('selfieModalClockOutImage');
                const noClockOutImage = document.getElementById('noSelfieModalClockOutImage');
                const clockOutTime = document.getElementById('selfieModalClockOutTime');

                document.querySelectorAll('.view-selfie-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const name = this.dataset.name;
                        const date = this.dataset.date;
                        const clockInImgUrl = this.dataset.clockinImg;
                        const clockInTimeVal = this.dataset.clockinTime;
                        const clockOutImgUrl = this.dataset.clockoutImg;
                        const clockOutTimeVal = this.dataset.clockoutTime;

                        modalEmployeeNameDate.textContent = `${name} - ${date}`;
                        clockInTime.textContent = clockInTimeVal;
                        clockOutTime.textContent = clockOutTimeVal;

                        if (clockInImgUrl) {
                            clockInImage.src = clockInImgUrl;
                            clockInImage.style.display = 'block';
                            noClockInImage.style.display = 'none';
                        } else {
                            clockInImage.src = '#';
                            clockInImage.style.display = 'none';
                            noClockInImage.style.display = 'block';
                        }

                        if (clockOutImgUrl) {
                            clockOutImage.src = clockOutImgUrl;
                            clockOutImage.style.display = 'block';
                            noClockOutImage.style.display = 'none';
                        } else {
                            clockOutImage.src = '#';
                            clockOutImage.style.display = 'none';
                            noClockOutImage.style.display = 'block';
                        }
                        selfieModal.show();
                    });
                });
            }
        });
    </script>
@endpush

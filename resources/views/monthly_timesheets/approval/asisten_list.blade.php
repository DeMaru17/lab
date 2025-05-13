@extends('layout.app')

@section('content')
    <div id="main">
        {{-- Header Halaman & Breadcrumb --}}
        <div class="page-heading">
            <div class="page-title mb-4">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Persetujuan Timesheet (Asisten)</h3>
                        <p class="text-subtitle text-muted">
                            Daftar timesheet bulanan yang menunggu persetujuan Anda.
                        </p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Persetujuan Timesheet Asisten</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        {{-- Akhir Header Halaman --}}

        {{-- Filter Periode --}}
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Filter Periode</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('monthly_timesheets.approval.asisten.list') }}" method="GET" class="form">
                        <div class="row gy-2">
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
                            <div class="col-md-3 col-12 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm me-1"><i class="bi bi-filter"></i>
                                    Filter</button>
                                <a href="{{ route('monthly_timesheets.approval.asisten.list') }}"
                                    class="btn btn-light-secondary btn-sm"><i class="bi bi-x-circle"></i> Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        {{-- Akhir Filter Periode --}}

        {{-- Tabel Daftar Approval Asisten --}}
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Menunggu Persetujuan Asisten
                        @if ($hasFiltered && request('filter_month') && request('filter_year'))
                            ({{ \Carbon\Carbon::create(request('filter_year'), request('filter_month'), 1)->format('F Y') }})
                        @elseif($hasFiltered)
                            {{-- Jika filter lain aktif tapi bukan bulan/tahun, atau default bulan/tahun --}}
                            ({{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }})
                        @endif
                    </h4>
                </div>
                <div class="card-body">
                    @if ($hasFiltered)
                        {{-- Form Bulk Approve --}}
                        <form id="bulk-approve-form-asisten"
                            action="{{ route('monthly_timesheets.approval.bulk.approve') }}" method="POST">
                            @csrf
                            <input type="hidden" name="approval_level" value="asisten">
                            {{-- <div id="selected-ids-container-asisten"></div> --}} {{-- Dihapus karena ID dikirim via checkbox --}}

                            {{-- Tombol Aksi Massal --}}
                            <div class="mb-3">
                                <button type="button" class="btn btn-success btn-sm" id="bulk-approve-btn-asisten"
                                    data-bs-toggle="modal" data-bs-target="#bulkApproveConfirmModalTimesheet" disabled>
                                    <i class="bi bi-check-lg"></i> Setujui yang Dipilih (<span
                                        class="selected-count-display">0</span>)
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-sm" id="tableAsistenApproval">
                                    <thead>
                                        <tr>
                                            <th style="width: 1%;"><input class="form-check-input" type="checkbox"
                                                    id="select-all-asisten"></th>
                                            <th>No</th>
                                            <th>Periode</th>
                                            <th>Nama Karyawan</th>
                                            <th>Jabatan</th>
                                            <th>Status Saat Ini</th>
                                            <th>Info Tambahan</th>
                                            <th style="min-width: 150px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($pendingAsistenTimesheets as $timesheet)
                                            <tr>
                                                <td><input class="form-check-input item-checkbox-asisten" type="checkbox"
                                                        name="selected_ids[]" value="{{ $timesheet->id }}"></td>
                                                <td>{{ $loop->iteration + $pendingAsistenTimesheets->firstItem() - 1 }}
                                                </td>
                                                <td class="text-nowrap">
                                                    {{ $timesheet->period_start_date?->format('d/m/y') }} -
                                                    {{ $timesheet->period_end_date?->format('d/m/y') }}</td>
                                                <td>{{ $timesheet->user?->name ?? 'N/A' }}</td>
                                                <td>{{ $timesheet->user?->jabatan ?? '-' }}</td>
                                                <td class="text-center">
                                                    <span
                                                        class="badge bg-{{ $timesheet->status == 'rejected' ? 'danger' : 'secondary' }}">
                                                        {{ Str::title(str_replace('_', ' ', $timesheet->status)) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if ($timesheet->status == 'rejected')
                                                        <small class="text-danger fst-italic" data-bs-toggle="tooltip"
                                                            title="Alasan: {{ $timesheet->notes }}">
                                                            Ditolak oleh {{ $timesheet->rejecter?->name ?? '?' }}
                                                        </small>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td class="text-nowrap">
                                                    <a href="{{ route('monthly_timesheets.show', ['timesheet' => $timesheet->id]) }}"
                                                        class="btn btn-info btn-sm d-inline-block me-1"
                                                        data-bs-toggle="tooltip" title="Lihat Detail & Proses">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    @if (in_array($timesheet->status, ['generated', 'rejected']))
                                                        {{-- Tombol reject hanya untuk status ini --}}
                                                        <button type="button" class="btn btn-danger btn-sm me-1"
                                                            data-bs-toggle="modal" data-bs-target="#rejectTimesheetModal"
                                                            data-timesheet-id="{{ $timesheet->id }}"
                                                            data-user-name="{{ $timesheet->user?->name ?? 'N/A' }}"
                                                            data-bs-toggle="tooltip" title="Tolak">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    @endif
                                                    {{-- Tombol approve individual tidak ditampilkan di sini, karena ada di halaman detail atau via bulk --}}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center">Tidak ada timesheet yang menunggu
                                                    persetujuan Anda untuk periode yang difilter.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </form> {{-- Akhir Form Bulk Approve --}}

                        {{-- Link Pagination --}}
                        <div class="mt-3">
                            @if ($pendingAsistenTimesheets instanceof \Illuminate\Pagination\AbstractPaginator)
                                {{ $pendingAsistenTimesheets->links() }}
                            @endif
                        </div>
                    @else
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle-fill"></i>
                            Silakan gunakan filter periode di atas untuk menampilkan data timesheet yang menunggu
                            persetujuan Anda.
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Include Modals --}}
        @include('monthly_timesheets.approval._bulk_approve_confirm_modal')
        @include('monthly_timesheets.approval._reject_modal')
        {{-- Modal _approve_modal mungkin tidak diperlukan jika approve individual hanya dari halaman detail --}}
        {{-- @include('monthly_timesheets.approval._approve_modal') --}}

    </div>
@endsection

@push('js')
    {{-- JavaScript untuk Bulk & Modals (Asisten) --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Inisialisasi Tooltip ---
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover'
                });
            });

            // --- Logika Checkbox & Tombol Bulk Approve ---
            const selectAllCheckboxAsisten = document.getElementById('select-all-asisten');
            const itemCheckboxesAsisten = document.querySelectorAll('.item-checkbox-asisten');
            const bulkApproveBtnAsisten = document.getElementById('bulk-approve-btn-asisten');
            const selectedCountSpansAsisten = document.querySelectorAll(
                '#bulk-approve-btn-asisten .selected-count-display'); // Lebih spesifik
            const mainBulkFormAsisten = document.getElementById('bulk-approve-form-asisten');

            function updateBulkButtonsAsisten() {
                const selectedCheckboxes = document.querySelectorAll('.item-checkbox-asisten:checked');
                const count = selectedCheckboxes.length;
                selectedCountSpansAsisten.forEach(span => span.textContent = count);
                if (bulkApproveBtnAsisten) {
                    bulkApproveBtnAsisten.disabled = count === 0;
                }
            }

            if (selectAllCheckboxAsisten) {
                selectAllCheckboxAsisten.addEventListener('change', function() {
                    itemCheckboxesAsisten.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateBulkButtonsAsisten();
                });
            }

            itemCheckboxesAsisten.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (!this.checked && selectAllCheckboxAsisten) {
                        selectAllCheckboxAsisten.checked = false;
                    } else if (selectAllCheckboxAsisten) {
                        selectAllCheckboxAsisten.checked = Array.from(itemCheckboxesAsisten).every(
                            cb => cb.checked);
                    }
                    updateBulkButtonsAsisten();
                });
            });

            // --- Handling Modal Konfirmasi Bulk Approve ---
            const bulkApproveConfirmModalEl = document.getElementById('bulkApproveConfirmModalTimesheet');
            const confirmBulkApproveBtn = document.getElementById('confirmBulkApproveBtnTimesheet');
            const bulkAsistenText = document.getElementById('bulk-approve-asisten-text');
            const bulkManagerText = document.getElementById('bulk-approve-manager-text');

            if (bulkApproveConfirmModalEl && confirmBulkApproveBtn && mainBulkFormAsisten) {
                bulkApproveConfirmModalEl.addEventListener('show.bs.modal', function(event) {
                    const count = document.querySelectorAll('.item-checkbox-asisten:checked').length;
                    bulkApproveConfirmModalEl.querySelector('.selected-count-display').textContent = count;
                    if (bulkAsistenText) bulkAsistenText.style.display = 'block';
                    if (bulkManagerText) bulkManagerText.style.display = 'none';
                    confirmBulkApproveBtn.disabled = count === 0;
                });

                confirmBulkApproveBtn.addEventListener('click', function() {
                    mainBulkFormAsisten.submit();
                    this.disabled = true;
                    this.textContent = 'Memproses...';
                });
            }

            // --- Handling Modal Reject Individual ---
            const rejectModalEl = document.getElementById('rejectTimesheetModal');
            const rejectForm = document.getElementById('rejectTimesheetForm');
            const rejectUserNameSpan = document.getElementById('rejectTimesheetUserName');
            const rejectNotesTextarea = document.getElementById('rejectTimesheetNotes');

            if (rejectModalEl && rejectForm && rejectUserNameSpan && rejectNotesTextarea) {
                rejectModalEl.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const timesheetId = button.getAttribute('data-timesheet-id');
                    const userName = button.getAttribute('data-user-name');
                    rejectUserNameSpan.textContent = userName;
                    const actionUrl =
                        "{{ route('monthly_timesheets.approval.reject', ['timesheet' => ':id']) }}"
                        .replace(':id', timesheetId);
                    rejectForm.action = actionUrl;
                    rejectNotesTextarea.value = '';
                    rejectNotesTextarea.classList.remove('is-invalid');
                });

                rejectForm.addEventListener('submit', function(event) {
                    if (!rejectNotesTextarea.value || rejectNotesTextarea.value.trim().length < 5) {
                        rejectNotesTextarea.classList.add('is-invalid');
                        event.preventDefault();
                        rejectNotesTextarea.focus();
                    } else {
                        rejectNotesTextarea.classList.remove('is-invalid');
                        rejectForm.querySelector('button[type="submit"]').disabled = true;
                        rejectForm.querySelector('button[type="submit"]').textContent = 'Memproses...';
                    }
                });
            }
            // Panggil untuk inisialisasi status tombol bulk
            updateBulkButtonsAsisten();
        });
    </script>
@endpush

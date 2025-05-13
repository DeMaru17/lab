@extends('layout.app')

@section('content')
    <div id="main">
        {{-- Header Halaman & Breadcrumb --}}
        <div class="page-heading">
            <div class="page-title mb-4">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Persetujuan Timesheet (Manager)</h3>
                        <p class="text-subtitle text-muted">
                            Daftar timesheet bulanan yang menunggu persetujuan final Anda.
                        </p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Persetujuan Timesheet Manager</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        {{-- Akhir Header Halaman --}}

        {{-- Filter (Tetap ada untuk Manager) --}}
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Filter Data</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('monthly_timesheets.approval.manager.list') }}" method="GET" class="form">
                        <div class="row gy-2">
                            {{-- Filter Bulan & Tahun --}}
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
                            {{-- Filter Karyawan --}}
                            <div class="col-md-3 col-12">
                                <label for="filter_user_id">Karyawan</label>
                                <select name="filter_user_id" id="filter_user_id"
                                    class="form-select form-select-sm select2">
                                    <option value="">-- Semua Karyawan --</option>
                                    @foreach ($usersForFilter as $userFilter)
                                        <option value="{{ $userFilter->id }}"
                                            {{ $filterUserId == $userFilter->id ? 'selected' : '' }}>
                                            {{ $userFilter->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- Filter Vendor --}}
                            <div class="col-md-2 col-12">
                                <label for="filter_vendor_id">Vendor</label>
                                <select name="filter_vendor_id" id="filter_vendor_id" class="form-select form-select-sm">
                                    <option value="">-- Semua Vendor --</option>
                                    <option value="is_null" {{ $filterVendorId == 'is_null' ? 'selected' : '' }}>Internal
                                    </option>
                                    @foreach ($vendorsForFilter as $vendor)
                                        <option value="{{ $vendor->id }}"
                                            {{ $filterVendorId == $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- Tombol Filter & Reset --}}
                            <div class="col-md-2 col-12 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm me-1"><i class="bi bi-filter"></i>
                                    Filter</button>
                                <a href="{{ route('monthly_timesheets.approval.manager.list') }}"
                                    class="btn btn-light-secondary btn-sm"><i class="bi bi-x-circle"></i> Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        {{-- Akhir Filter --}}

        {{-- Tabel Daftar Approval Manager dengan Bulk Action --}}
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Menunggu Persetujuan Manager
                        @if ($hasFiltered && request('filter_month') && request('filter_year'))
                            ({{ \Carbon\Carbon::create(request('filter_year'), request('filter_month'), 1)->format('F Y') }})
                        @elseif($hasFiltered)
                            ({{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }})
                        @endif
                    </h4>
                </div>
                <div class="card-body">
                    @if ($hasFiltered)
                        <form id="bulk-approve-form-manager"
                            action="{{ route('monthly_timesheets.approval.bulk.approve') }}" method="POST">
                            @csrf
                            <input type="hidden" name="approval_level" value="manager">

                            <div class="mb-3">
                                <button type="button" class="btn btn-success btn-sm" id="bulk-approve-btn-manager"
                                    data-bs-toggle="modal" data-bs-target="#bulkApproveConfirmModalTimesheet" disabled>
                                    <i class="bi bi-check-lg"></i> Setujui Final yang Dipilih (<span
                                        class="selected-count-display">0</span>)
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-sm" id="tableManagerApproval">
                                    <thead>
                                        <tr>
                                            <th style="width: 1%;"><input class="form-check-input" type="checkbox"
                                                    id="select-all-manager"></th>
                                            <th>No</th>
                                            <th>Periode</th>
                                            <th>Nama Karyawan</th>
                                            <th>Vendor</th>
                                            <th>Jabatan</th>
                                            <th>Approved By (Asisten)</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($pendingManagerTimesheets as $timesheet)
                                            <tr>
                                                <td><input class="form-check-input item-checkbox-manager" type="checkbox"
                                                        name="selected_ids[]" value="{{ $timesheet->id }}"></td>
                                                <td>{{ $loop->iteration + $pendingManagerTimesheets->firstItem() - 1 }}
                                                </td>
                                                <td class="text-nowrap">
                                                    {{ $timesheet->period_start_date?->format('d/m/y') }} -
                                                    {{ $timesheet->period_end_date?->format('d/m/y') }}</td>
                                                <td>{{ $timesheet->user?->name ?? 'N/A' }}</td>
                                                <td>{{ $timesheet->user?->vendor?->name ?? 'Internal' }}</td>
                                                <td>{{ $timesheet->user?->jabatan ?? '-' }}</td>
                                                <td>
                                                    {{ $timesheet->approverAsisten?->name ?? 'N/A' }}
                                                    @if ($timesheet->approved_at_asisten)
                                                        <small
                                                            class="text-muted d-block">{{ $timesheet->approved_at_asisten->format('d/m/y H:i') }}</small>
                                                    @endif
                                                </td>
                                                <td class="text-nowrap">
                                                    <a href="{{ route('monthly_timesheets.show', ['timesheet' => $timesheet->id]) }}"
                                                        class="btn btn-info btn-sm d-inline-block me-1"
                                                        data-bs-toggle="tooltip" title="Lihat Detail & Proses">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    @if (in_array($timesheet->status, ['pending_manager']))
                                                        <button type="button" class="btn btn-danger btn-sm"
                                                            data-bs-toggle="modal" data-bs-target="#rejectTimesheetModal"
                                                            data-timesheet-id="{{ $timesheet->id }}"
                                                            data-user-name="{{ $timesheet->user?->name ?? 'N/A' }}"
                                                            data-bs-toggle="tooltip" title="Tolak">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center">Tidak ada timesheet yang menunggu
                                                    persetujuan final Anda untuk periode yang difilter.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </form>

                        <div class="mt-3">
                            @if ($pendingManagerTimesheets instanceof \Illuminate\Pagination\AbstractPaginator)
                                {{ $pendingManagerTimesheets->links() }}
                            @endif
                        </div>
                    @else
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle-fill"></i>
                            Silakan gunakan filter di atas untuk menampilkan data timesheet yang menunggu persetujuan final
                            Anda.
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Include Modals (Sama seperti di asisten_list.blade.php) --}}
        @include('monthly_timesheets.approval._bulk_approve_confirm_modal')
        @include('monthly_timesheets.approval._reject_modal')
        {{-- @include('monthly_timesheets.approval._approve_modal') --}}
    </div>
@endsection

@push('js')
    {{-- JavaScript untuk Bulk & Modals (Manager) --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover'
                });
            });

            const selectAllCheckboxManager = document.getElementById('select-all-manager');
            const itemCheckboxesManager = document.querySelectorAll('.item-checkbox-manager');
            const bulkApproveBtnManager = document.getElementById('bulk-approve-btn-manager');
            const selectedCountSpansManager = document.querySelectorAll(
                '#bulk-approve-btn-manager .selected-count-display');
            const mainBulkFormManager = document.getElementById('bulk-approve-form-manager');

            function updateBulkButtonsManager() {
                const selectedCheckboxes = document.querySelectorAll('.item-checkbox-manager:checked');
                const count = selectedCheckboxes.length;
                selectedCountSpansManager.forEach(span => span.textContent = count);
                if (bulkApproveBtnManager) {
                    bulkApproveBtnManager.disabled = count === 0;
                }
            }

            if (selectAllCheckboxManager) {
                selectAllCheckboxManager.addEventListener('change', function() {
                    itemCheckboxesManager.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateBulkButtonsManager();
                });
            }

            itemCheckboxesManager.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (!this.checked && selectAllCheckboxManager) {
                        selectAllCheckboxManager.checked = false;
                    } else if (selectAllCheckboxManager) {
                        selectAllCheckboxManager.checked = Array.from(itemCheckboxesManager).every(
                            cb => cb.checked);
                    }
                    updateBulkButtonsManager();
                });
            });

            const bulkApproveConfirmModalEl = document.getElementById('bulkApproveConfirmModalTimesheet');
            const confirmBulkApproveBtn = document.getElementById('confirmBulkApproveBtnTimesheet');
            const bulkAsistenText = document.getElementById('bulk-approve-asisten-text');
            const bulkManagerText = document.getElementById('bulk-approve-manager-text');

            if (bulkApproveConfirmModalEl && confirmBulkApproveBtn && mainBulkFormManager) {
                bulkApproveConfirmModalEl.addEventListener('show.bs.modal', function(event) {
                    const count = document.querySelectorAll('.item-checkbox-manager:checked')
                    .length; // Gunakan selector manager
                    bulkApproveConfirmModalEl.querySelector('.selected-count-display').textContent = count;
                    if (bulkManagerText) bulkManagerText.style.display = 'block';
                    if (bulkAsistenText) bulkAsistenText.style.display = 'none';
                    confirmBulkApproveBtn.disabled = count === 0;
                });
                confirmBulkApproveBtn.addEventListener('click', function() {
                    mainBulkFormManager.submit();
                    this.disabled = true;
                    this.textContent = 'Memproses...';
                });
            }

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
            updateBulkButtonsManager(); // Panggil untuk inisialisasi
        });
    </script>
@endpush

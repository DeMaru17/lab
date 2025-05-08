@extends('layout.app')

@section('content')
<div id="main">
    {{-- Header Halaman & Breadcrumb --}}
    <div class="page-heading">
        <div class="page-title mb-4">
            {{-- ... Judul dan Breadcrumb ... --}}
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
            <div class="card-header"><h4 class="card-title">Filter Periode</h4></div>
            <div class="card-body">
                 <form action="{{ route('monthly_timesheets.approval.asisten.list') }}" method="GET" class="form">
                    {{-- ... Form filter periode ... --}}
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
                                    <option value="{{ $y }}" {{ $filterYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-3 col-12 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm me-1"><i class="bi bi-filter"></i> Filter</button>
                            <a href="{{ route('monthly_timesheets.approval.asisten.list') }}" class="btn btn-light-secondary btn-sm"><i class="bi bi-x-circle"></i> Reset</a>
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
                     @if(request('filter_month') && request('filter_year'))
                         ({{ \Carbon\Carbon::create(request('filter_year'), request('filter_month'), 1)->format('F Y') }})
                     @endif
                 </h4>
            </div>
            <div class="card-body">
                {{-- Form Bulk Approve --}}
                <form id="bulk-approve-form-asisten" action="{{ route('monthly_timesheets.approval.bulk.approve') }}" method="POST">
                    @csrf
                    <input type="hidden" name="approval_level" value="asisten">
                    <div id="selected-ids-container-asisten"></div> {{-- Tidak wajib jika tidak pakai JS canggih --}}

                    {{-- Tombol Aksi Massal --}}
                    <div class="mb-3">
                        {{-- @can('bulkApproveAsisten', \App\Models\MonthlyTimesheet::class) --}}
                        <button type="button" class="btn btn-success btn-sm" id="bulk-approve-btn-asisten"
                            data-bs-toggle="modal" data-bs-target="#bulkApproveConfirmModalTimesheet" disabled>
                            <i class="bi bi-check-lg"></i> Setujui yang Dipilih (<span class="selected-count-display">0</span>)
                        </button>
                        {{-- @endcan --}}
                         {{-- Tambahkan tombol bulk reject jika Anda membuatnya --}}
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm" id="tableAsistenApproval">
                            <thead>
                                <tr>
                                    <th style="width: 1%;"><input class="form-check-input" type="checkbox" id="select-all-asisten"></th>
                                    <th>No</th>
                                    <th>Periode</th>
                                    <th>Nama Karyawan</th>
                                    <th>Jabatan</th>
                                    <th>Status Saat Ini</th>
                                    <th>Info Tambahan</th>
                                    <th style="min-width: 150px;">Aksi</th> {{-- Lebarkan kolom Aksi --}}
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pendingAsistenTimesheets as $timesheet)
                                    <tr>
                                        <td><input class="form-check-input item-checkbox-asisten" type="checkbox" name="selected_ids[]" value="{{ $timesheet->id }}"></td>
                                        <td>{{ $loop->iteration + $pendingAsistenTimesheets->firstItem() - 1 }}</td>
                                        <td class="text-nowrap">{{ $timesheet->period_start_date?->format('d/m/y') }} - {{ $timesheet->period_end_date?->format('d/m/y') }}</td>
                                        <td>{{ $timesheet->user?->name ?? 'N/A' }}</td>
                                        <td>{{ $timesheet->user?->jabatan ?? '-' }}</td>
                                        <td class="text-center">
                                             <span class="badge bg-{{ $timesheet->status == 'rejected' ? 'danger' : 'secondary' }}">
                                                {{ Str::title(str_replace('_', ' ', $timesheet->status)) }}
                                             </span>
                                        </td>
                                        <td>
                                            @if($timesheet->status == 'rejected')
                                                <small class="text-danger fst-italic" data-bs-toggle="tooltip" title="Alasan: {{ $timesheet->notes }}">
                                                    Ditolak oleh {{ $timesheet->rejecter?->name ?? '?' }}
                                                </small>
                                            @else - @endif
                                        </td>
                                        <td class="text-nowrap">
                                            {{-- Tombol Detail --}}
                                            <a href="{{ route('monthly_timesheets.show', ['timesheet' => $timesheet->id]) }}" class="btn btn-info btn-sm d-inline-block me-1" data-bs-toggle="tooltip" title="Lihat Detail">
                                                <i class="bi bi-eye"></i>
                                            </a>

                                            {{-- ===================================== --}}
                                            {{-- == TOMBOL AKSI INDIVIDUAL DI SINI == --}}
                                            {{-- ===================================== --}}

                                            {{-- Tombol Reject Individual (Trigger Modal) --}}
                                            {{-- @can('reject', $timesheet) --}}
                                                {{-- Tampilkan hanya jika status memungkinkan untuk direject oleh Asisten --}}
                                                @if(in_array($timesheet->status, ['generated']))
                                                <button type="button" class="btn btn-danger btn-sm me-1" data-bs-toggle="modal" data-bs-target="#rejectTimesheetModal"
                                                    data-timesheet-id="{{ $timesheet->id }}"
                                                    data-user-name="{{ $timesheet->user?->name ?? 'N/A' }}"
                                                    data-bs-toggle="tooltip" title="Tolak">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                                @endif
                                            {{-- @endcan --}}

                                            {{-- Tombol Approve Individual (Trigger Modal) --}}
                                            {{-- @can('approveAsisten', $timesheet) --}}
                                                 {{-- Tampilkan hanya jika status memungkinkan untuk diapprove oleh Asisten --}}
                                                 {{-- @if(in_array($timesheet->status, ['generated', 'rejected'])) --}}
                                                 {{-- <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#approveTimesheetModal"
                                                    data-timesheet-id="{{ $timesheet->id }}"
                                                    data-user-name="{{ $timesheet->user?->name ?? 'N/A' }}"
                                                    data-approval-level="asisten" {{-- Tandai level --}}
                                                    {{-- data-bs-toggle="tooltip" title="Setujui">
                                                    <i class="bi bi-check-lg"></i>
                                                </button> --}}
                                                 {{-- @endif --}}
                                            {{-- @endcan --}}
                                            {{-- ===================================== --}}
                                            {{-- == AKHIR TOMBOL AKSI INDIVIDUAL   == --}}
                                            {{-- ===================================== --}}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">Tidak ada timesheet yang menunggu persetujuan Anda saat ini.</td> {{-- Sesuaikan colspan --}}
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form> {{-- Akhir Form Bulk Approve --}}

                {{-- Link Pagination --}}
                <div class="mt-3">
                    {{ $pendingAsistenTimesheets->links() }}
                </div>
            </div>
        </div>
    </section>

    {{-- Include Modals (Pastikan path benar) --}}
    @include('monthly_timesheets.approval._bulk_approve_confirm_modal')
    @include('monthly_timesheets.approval._reject_modal')
    @include('monthly_timesheets.approval._approve_modal')

</div>
@endsection

@push('js')
{{-- JavaScript untuk Bulk & Modals (Asisten) --}}
{{-- Salin dari jawaban sebelumnya yang sudah lengkap dan sesuai --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Inisialisasi Tooltip ---
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, { trigger: 'hover' });
    });

    // --- Logika Checkbox & Tombol Bulk Approve ---
    const selectAllCheckbox = document.getElementById('select-all-asisten'); // ID unik
    const itemCheckboxes = document.querySelectorAll('.item-checkbox-asisten'); // Kelas unik
    const bulkApproveBtn = document.getElementById('bulk-approve-btn-asisten'); // ID unik
    const selectedCountSpans = document.querySelectorAll('.selected-count-display');
    const mainBulkForm = document.getElementById('bulk-approve-form-asisten'); // ID Form unik

    function updateBulkButtonsAsisten() { // Nama fungsi unik (opsional)
        const selectedCheckboxes = document.querySelectorAll('.item-checkbox-asisten:checked');
        const count = selectedCheckboxes.length;
        selectedCountSpans.forEach(span => span.textContent = count);
        if (bulkApproveBtn) { bulkApproveBtn.disabled = count === 0; }
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            itemCheckboxes.forEach(checkbox => { checkbox.checked = this.checked; });
            updateBulkButtonsAsisten();
        });
    }

    itemCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            if (!this.checked && selectAllCheckbox) { selectAllCheckbox.checked = false; }
            else if (selectAllCheckbox) { selectAllCheckbox.checked = Array.from(itemCheckboxes).every(cb => cb.checked); }
            updateBulkButtonsAsisten();
        });
    });

    // --- Handling Modal Konfirmasi Bulk Approve ---
    const bulkApproveConfirmModalEl = document.getElementById('bulkApproveConfirmModalTimesheet');
    const confirmBulkApproveBtn = document.getElementById('confirmBulkApproveBtnTimesheet');
    const bulkAsistenText = document.getElementById('bulk-approve-asisten-text');
    const bulkManagerText = document.getElementById('bulk-approve-manager-text');

    if (bulkApproveConfirmModalEl && confirmBulkApproveBtn && mainBulkForm) {
         bulkApproveConfirmModalEl.addEventListener('show.bs.modal', function (event) {
             const count = document.querySelectorAll('.item-checkbox-asisten:checked').length;
             bulkApproveConfirmModalEl.querySelector('.selected-count-display').textContent = count;
             // Tampilkan teks yang sesuai untuk Asisten
             if (bulkAsistenText) bulkAsistenText.style.display = 'block';
             if (bulkManagerText) bulkManagerText.style.display = 'none';
             confirmBulkApproveBtn.disabled = count === 0;
         });

        confirmBulkApproveBtn.addEventListener('click', function () {
            mainBulkForm.submit();
            this.disabled = true; this.textContent = 'Memproses...';
        });
    }

     // --- Handling Modal Reject Individual ---
    const rejectModalEl = document.getElementById('rejectTimesheetModal');
    const rejectForm = document.getElementById('rejectTimesheetForm');
    const rejectUserNameSpan = document.getElementById('rejectTimesheetUserName');
    const rejectNotesTextarea = document.getElementById('rejectTimesheetNotes');

     if (rejectModalEl && rejectForm && rejectUserNameSpan && rejectNotesTextarea) {
        rejectModalEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const timesheetId = button.getAttribute('data-timesheet-id');
            const userName = button.getAttribute('data-user-name');
            rejectUserNameSpan.textContent = userName;
            // Gunakan route helper jika memungkinkan atau bangun URL manual
            const actionUrl = "{{ route('monthly_timesheets.approval.reject', ['timesheet' => ':id']) }}".replace(':id', timesheetId);
            // const actionUrl = "{{ url('monthly-timesheets/approval') }}/" + timesheetId + "/reject"; // Alternatif
            rejectForm.action = actionUrl;
            rejectNotesTextarea.value = '';
            rejectNotesTextarea.classList.remove('is-invalid');
        });

         rejectForm.addEventListener('submit', function(event) {
             if (!rejectNotesTextarea.value || rejectNotesTextarea.value.trim().length < 5) {
                 rejectNotesTextarea.classList.add('is-invalid');
                 event.preventDefault(); rejectNotesTextarea.focus();
             } else {
                 rejectNotesTextarea.classList.remove('is-invalid');
                 rejectForm.querySelector('button[type="submit"]').disabled = true;
                 rejectForm.querySelector('button[type="submit"]').textContent = 'Memproses...';
             }
         });
    }

    // --- Handling Modal Approve Individual ---
    const approveModalEl = document.getElementById('approveTimesheetModal');
    const approveForm = document.getElementById('approveTimesheetForm');
    const approveUserNameSpan = document.getElementById('approveTimesheetUserName');
    const approveAsistenNote = document.getElementById('approveTimesheetAsistenNote');
    const approveManagerNote = document.getElementById('approveTimesheetManagerNote');

    if (approveModalEl && approveForm && approveUserNameSpan && approveAsistenNote && approveManagerNote) {
         approveModalEl.addEventListener('show.bs.modal', function(event) {
             const button = event.relatedTarget;
             const timesheetId = button.getAttribute('data-timesheet-id');
             const userName = button.getAttribute('data-user-name');
             const level = button.getAttribute('data-approval-level');

             approveUserNameSpan.textContent = userName;

             let actionUrl = '';
             if (level === 'asisten') {
                 // Gunakan route helper
                 actionUrl = "{{ route('monthly_timesheets.approval.asisten.approve', ['timesheet' => ':id']) }}".replace(':id', timesheetId);
                 // actionUrl = "{{ url('monthly-timesheets/approval') }}/" + timesheetId + "/approve/asisten"; // Alternatif
                 approveAsistenNote.style.display = 'block';
                 approveManagerNote.style.display = 'none';
             } else if (level === 'manager') {
                 // Gunakan route helper
                  actionUrl = "{{ route('monthly_timesheets.approval.manager.approve', ['timesheet' => ':id']) }}".replace(':id', timesheetId);
                 // actionUrl = "{{ url('monthly-timesheets/approval') }}/" + timesheetId + "/approve/manager"; // Alternatif
                 approveAsistenNote.style.display = 'none';
                 approveManagerNote.style.display = 'block';
             }
             approveForm.action = actionUrl;
         });

          approveForm.addEventListener('submit', function(event) {
             approveForm.querySelector('button[type="submit"]').disabled = true;
             approveForm.querySelector('button[type="submit"]').textContent = 'Memproses...';
         });
    }

    // Update state tombol bulk saat halaman pertama kali load
    updateBulkButtonsAsisten();

});
</script>
@endpush

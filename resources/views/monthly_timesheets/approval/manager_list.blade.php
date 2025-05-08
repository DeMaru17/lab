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
                    {{-- ... Form filter lengkap (Periode, User, Vendor) ... --}}
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
                                    <option value="{{ $y }}" {{ $filterYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        {{-- Filter Karyawan --}}
                        <div class="col-md-3 col-12">
                            <label for="filter_user_id">Karyawan</label>
                            <select name="filter_user_id" id="filter_user_id" class="form-select form-select-sm select2">
                                <option value="">-- Semua Karyawan --</option>
                                @foreach ($usersForFilter as $userFilter)
                                    <option value="{{ $userFilter->id }}" {{ $filterUserId == $userFilter->id ? 'selected' : '' }}>{{ $userFilter->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Filter Vendor --}}
                        <div class="col-md-2 col-12">
                            <label for="filter_vendor_id">Vendor</label>
                            <select name="filter_vendor_id" id="filter_vendor_id" class="form-select form-select-sm">
                                <option value="">-- Semua Vendor --</option>
                                <option value="is_null" {{ $filterVendorId == 'is_null' ? 'selected' : '' }}>Internal</option>
                                @foreach ($vendorsForFilter as $vendor)
                                    <option value="{{ $vendor->id }}" {{ $filterVendorId == $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Tombol Filter & Reset --}}
                        <div class="col-md-2 col-12 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm me-1"><i class="bi bi-filter"></i> Filter</button>
                            <a href="{{ route('monthly_timesheets.approval.manager.list') }}" class="btn btn-light-secondary btn-sm"><i class="bi bi-x-circle"></i> Reset</a>
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
                      @if(request('filter_month') && request('filter_year'))
                         ({{ \Carbon\Carbon::create(request('filter_year'), request('filter_month'), 1)->format('F Y') }})
                     @endif
                 </h4>
            </div>
            <div class="card-body">
                 {{-- ================================================ --}}
                 {{-- == FORM UNTUK BULK APPROVE MANAGER            == --}}
                 {{-- ================================================ --}}
                 <form id="bulk-approve-form-manager" action="{{ route('monthly_timesheets.approval.bulk.approve') }}" method="POST">
                    @csrf
                    <input type="hidden" name="approval_level" value="manager"> {{-- Level: manager --}}
                    <div id="selected-ids-container-manager"></div> {{-- Container ID unik --}}

                    {{-- Tombol Aksi Massal --}}
                    <div class="mb-3">
                         @can('bulkApproveManager', \App\Models\MonthlyTimesheet::class) {{-- Policy bulk L2 --}}
                        <button type="button" class="btn btn-primary btn-sm" id="bulk-approve-btn-manager" {{-- ID unik --}}
                            data-bs-toggle="modal" data-bs-target="#bulkApproveConfirmModalTimesheet" disabled> {{-- Target modal SAMA --}}
                            <i class="bi bi-check-all"></i> Setujui Final yang Dipilih (<span class="selected-count-display">0</span>) {{-- Kelas counter sama --}}
                        </button>
                        @endcan
                         {{-- Tambah tombol bulk reject jika perlu --}}
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm" id="tableManagerApproval">
                            <thead>
                                <tr>
                                    {{-- Checkbox Select All --}}
                                    <th style="width: 1%;"><input class="form-check-input" type="checkbox" id="select-all-manager"></th> {{-- ID unik --}}
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
                                        {{-- Checkbox per Baris --}}
                                        <td><input class="form-check-input item-checkbox-manager" type="checkbox" name="selected_ids[]" value="{{ $timesheet->id }}"></td> {{-- Kelas unik --}}
                                        <td>{{ $loop->iteration + $pendingManagerTimesheets->firstItem() - 1 }}</td>
                                        <td class="text-nowrap">{{ $timesheet->period_start_date?->format('d/m/y') }} - {{ $timesheet->period_end_date?->format('d/m/y') }}</td>
                                        <td>{{ $timesheet->user?->name ?? 'N/A' }}</td>
                                        <td>{{ $timesheet->user?->vendor?->name ?? 'Internal' }}</td>
                                        <td>{{ $timesheet->user?->jabatan ?? '-' }}</td>
                                        <td>
                                            {{ $timesheet->approverAsisten?->name ?? 'N/A' }}
                                            @if($timesheet->approved_at_asisten)
                                                <small class="text-muted d-block">{{ $timesheet->approved_at_asisten->format('d/m/y H:i') }}</small>
                                            @endif
                                        </td>
                                         <td class="text-nowrap">
                                            {{-- Tombol Detail --}}
                                            <a href="{{ route('monthly_timesheets.show', ['timesheet' => $timesheet->id]) }}" class="btn btn-info btn-sm d-inline-block me-1" data-bs-toggle="tooltip" title="Lihat Detail & Proses Individual">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            {{-- Tombol Reject Individual (Manager) --}}
                                            @can('reject', $timesheet)
                                                @if(in_array($timesheet->status, ['pending_manager_approval'])) {{-- Hanya reject yg menunggu dia --}}
                                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectTimesheetModal"
                                                    data-timesheet-id="{{ $timesheet->id }}"
                                                    data-user-name="{{ $timesheet->user?->name ?? 'N/A' }}"
                                                    data-bs-toggle="tooltip" title="Tolak">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                                @endif
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">Tidak ada timesheet yang menunggu persetujuan final Anda saat ini.</td> {{-- Sesuaikan colspan --}}
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form> {{-- Akhir Form Bulk Approve --}}

                {{-- Link Pagination --}}
                <div class="mt-3">
                    {{ $pendingManagerTimesheets->links() }}
                </div>
            </div>
        </div>
    </section>

    {{-- ================================================ --}}
    {{-- ==              INCLUDE MODALS                == --}}
    {{-- ================================================ --}}

    {{-- 1. Modal Konfirmasi Bulk Approve (SAMA seperti di asisten_list) --}}
    {{-- ID unik: bulkApproveConfirmModalTimesheet --}}
    <div class="modal fade" id="bulkApproveConfirmModalTimesheet" tabindex="-1" aria-labelledby="bulkApproveConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="bulkApproveConfirmModalLabel">Konfirmasi Persetujuan Massal Timesheet</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Anda akan menyetujui <strong class="selected-count-display">0</strong> timesheet yang dipilih.</p>
                    {{-- Tambahkan teks spesifik untuk Manager (bisa diatur JS) --}}
                    <p id="bulk-approve-asisten-text" style="display: none;">Pengajuan akan diteruskan ke Manager.</p>
                    <p id="bulk-approve-manager-text" style="display: none;">Ini adalah persetujuan final.</p>
                    <p>Apakah Anda yakin ingin melanjutkan?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    {{-- Tombol ini akan men-trigger submit form aktif (asisten/manager) via JS --}}
                    <button type="button" class="btn btn-success" id="confirmBulkApproveBtnTimesheet">Ya, Setujui yang Dipilih</button>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Modal Reject Individual (SAMA seperti di asisten_list) --}}
    {{-- ID unik: rejectTimesheetModal --}}
    <div class="modal fade" id="rejectTimesheetModal" tabindex="-1" aria-labelledby="rejectTimesheetModalLabel" aria-hidden="true">
        {{-- ... (Konten modal reject sama persis) ... --}}
         <div class="modal-dialog">
            <div class="modal-content">
                 <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="rejectTimesheetModalLabel">Tolak Timesheet</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="rejectTimesheetForm" method="POST" action=""> {{-- Action diisi oleh JS --}}
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <p>Anda akan menolak timesheet untuk karyawan: <strong id="rejectTimesheetUserName">Nama Karyawan</strong>.</p>
                        <div class="mb-3">
                            <label for="rejectTimesheetNotes" class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="rejectTimesheetNotes" name="notes" rows="4" required minlength="5" placeholder="Masukkan alasan mengapa timesheet ditolak..."></textarea>
                            <div class="invalid-feedback">Alasan penolakan wajib diisi (min. 5 karakter).</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Tolak Timesheet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

@push('js')
{{-- ================================================ --}}
{{-- == JAVASCRIPT UNTUK BULK & MODALS (MANAGER)   == --}}
{{-- ================================================ --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Inisialisasi Tooltip ---
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, { trigger: 'hover' });
    });

    // --- Logika Checkbox & Tombol Bulk Approve (MANAGER) ---
    const selectAllCheckbox = document.getElementById('select-all-manager'); // ID unik
    const itemCheckboxes = document.querySelectorAll('.item-checkbox-manager'); // Kelas unik
    const bulkApproveBtn = document.getElementById('bulk-approve-btn-manager'); // ID unik
    const selectedCountSpans = document.querySelectorAll('.selected-count-display');
    const mainBulkForm = document.getElementById('bulk-approve-form-manager'); // ID Form unik

    function updateBulkButtonsManager() { // Nama fungsi unik (opsional)
        const selectedCheckboxes = document.querySelectorAll('.item-checkbox-manager:checked');
        const count = selectedCheckboxes.length;

        selectedCountSpans.forEach(span => span.textContent = count);

        if (bulkApproveBtn) {
            bulkApproveBtn.disabled = count === 0;
        }
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkButtonsManager();
        });
    }

    itemCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            if (!this.checked && selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            } else if (selectAllCheckbox) {
                selectAllCheckbox.checked = Array.from(itemCheckboxes).every(cb => cb.checked);
            }
            updateBulkButtonsManager();
        });
    });

    // --- Handling Modal Konfirmasi Bulk Approve (SAMA tapi perlu set teks) ---
    const bulkApproveConfirmModal = document.getElementById('bulkApproveConfirmModalTimesheet'); // ID Modal SAMA
    const confirmBulkApproveBtn = document.getElementById('confirmBulkApproveBtnTimesheet'); // ID Tombol Konfirm SAMA
    // Elemen teks spesifik di modal
    const asistenText = document.getElementById('bulk-approve-asisten-text');
    const managerText = document.getElementById('bulk-approve-manager-text');

    if (bulkApproveConfirmModal && confirmBulkApproveBtn && mainBulkForm) {
         // Tambahkan event listener saat modal DITAMPILKAN
         bulkApproveConfirmModal.addEventListener('show.bs.modal', function (event) {
             // Update count (bisa diambil dari span atau hitung ulang)
             const count = document.querySelectorAll('.item-checkbox-manager:checked').length;
             bulkApproveConfirmModal.querySelector('.selected-count-display').textContent = count;

             // Tampilkan teks yang sesuai untuk Manager
             if (managerText) managerText.style.display = 'block';
             if (asistenText) asistenText.style.display = 'none';
         });

        // Event listener untuk tombol konfirmasi di dalam modal
        confirmBulkApproveBtn.addEventListener('click', function () {
            // Cek form mana yg visible/aktif jika ID form berbeda, atau submit form ini saja
            if (mainBulkForm) { // Pastikan form manager ada
                 mainBulkForm.submit();
                 this.disabled = true;
                 this.textContent = 'Memproses...';
            } else {
                 console.error("Form bulk approve manager tidak ditemukan!");
            }
        });
    }

     // --- Handling Modal Reject Individual (SAMA) ---
    const rejectModal = document.getElementById('rejectTimesheetModal'); // ID Modal SAMA
    const rejectForm = document.getElementById('rejectTimesheetForm');
    const rejectUserNameSpan = document.getElementById('rejectTimesheetUserName');
    const rejectNotesTextarea = document.getElementById('rejectTimesheetNotes');

     if (rejectModal && rejectForm && rejectUserNameSpan && rejectNotesTextarea) {
        rejectModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const timesheetId = button.getAttribute('data-timesheet-id');
            const userName = button.getAttribute('data-user-name');

            rejectUserNameSpan.textContent = userName;
             // Set action URL form reject (route sama)
            const actionUrl = "{{ url('monthly-timesheets/approval') }}/" + timesheetId + "/reject";
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

    // Update state tombol saat halaman pertama kali load
    updateBulkButtonsManager();

});
</script>
@endpush

{{-- @push('styles') --}}
{{-- CSS tambahan jika ada --}}
{{-- @endpush --}}

{{-- resources/views/overtimes/index.blade.php --}}
@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@section('content')
    <div id="main">
        {{-- Header Halaman & Breadcrumb --}}
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Data Lembur</h3>
                        <p class="text-subtitle text-muted">
                            @if (in_array(Auth::user()->role, ['admin', 'manajemen']))
                                Daftar semua pengajuan lembur karyawan.
                            @else
                                Riwayat pengajuan lembur Anda.
                            @endif
                        </p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Lembur</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        {{-- Akhir Header Halaman --}}

        {{-- Tombol Ajukan Lembur Baru (Hanya untuk Personil & Admin) --}}
        {{-- Ganti 'create' dengan ability policy jika sudah dibuat --}}
        @if (in_array(Auth::user()->role, ['personil', 'admin']))
            <div class="mb-3">
                {{-- Pastikan nama route benar --}}
                <a href="{{ route('overtimes.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Ajukan Lembur Baru
                </a>
            </div>
        @endif

        {{-- Bagian Tabel Daftar Lembur --}}
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Filter Data Lembur</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('overtimes.index') }}" method="GET" class="form">
                        <div class="row gy-2">
                            {{-- Filter Tanggal --}}
                            <div class="col-md-3 col-6">
                                <label for="filter_start_date">Dari Tanggal</label>
                                <input type="date" id="filter_start_date" class="form-control form-control-sm"
                                    name="filter_start_date" value="{{ $startDate ?? '' }}">
                            </div>
                            <div class="col-md-3 col-6">
                                <label for="filter_end_date">Sampai Tanggal</label>
                                <input type="date" id="filter_end_date" class="form-control form-control-sm"
                                    name="filter_end_date" value="{{ $endDate ?? '' }}">
                            </div>

                            {{-- Filter Status --}}
                            <div class="col-md-3 col-12">
                                <label for="filter_status">Status</label>
                                <select name="filter_status" id="filter_status" class="form-select form-select-sm">
                                    <option value="">-- Semua Status --</option>
                                    <option value="pending" {{ $selectedStatus == 'pending' ? 'selected' : '' }}>Pending
                                        (Asisten)</option>
                                    <option value="pending_manager_approval"
                                        {{ $selectedStatus == 'pending_manager_approval' ? 'selected' : '' }}>Pending
                                        (Manager)</option>
                                    <option value="approved" {{ $selectedStatus == 'approved' ? 'selected' : '' }}>Approved
                                    </option>
                                    <option value="rejected" {{ $selectedStatus == 'rejected' ? 'selected' : '' }}>Rejected
                                    </option>
                                    <option value="cancelled" {{ $selectedStatus == 'cancelled' ? 'selected' : '' }}>
                                        Cancelled</option>
                                </select>
                            </div>

                            {{-- Filter Karyawan & Vendor (Hanya Admin/Manajemen) --}}
                            @if (in_array(Auth::user()->role, ['admin', 'manajemen']))
                                <div class="col-md-3 col-12">
                                    <label for="filter_user_id">Karyawan</label>
                                    <select name="filter_user_id" id="filter_user_id" class="form-select form-select-sm">
                                        <option value="">-- Semua Karyawan --</option>
                                        @foreach ($users as $userFilter)
                                            <option value="{{ $userFilter->id }}"
                                                {{ $selectedUserId == $userFilter->id ? 'selected' : '' }}>
                                                {{ $userFilter->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 col-12">
                                    <label for="filter_vendor_id">Vendor</label>
                                    <select name="filter_vendor_id" id="filter_vendor_id"
                                        class="form-select form-select-sm">
                                        <option value="">-- Semua Vendor --</option>
                                        <option value="is_null" {{ $selectedVendorId == 'is_null' ? 'selected' : '' }}>
                                            Internal Karyawan</option>
                                        @foreach ($vendors as $vendor)
                                            <option value="{{ $vendor->id }}"
                                                {{ $selectedVendorId == $vendor->id ? 'selected' : '' }}>
                                                {{ $vendor->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            {{-- Tombol Filter & Reset --}}
                            <div class="col-md-3 col-12 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm me-1"><i class="bi bi-filter"></i>
                                    Filter</button>
                                <a href="{{ route('overtimes.index') }}" class="btn btn-light-secondary btn-sm"><i
                                        class="bi bi-x-circle"></i> Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        {{-- === AKHIR BAGIAN FILTER === --}}


        {{-- Bagian Tabel Daftar Lembur --}}
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-8 col-12">
                            {{-- Judul dinamis berdasarkan filter --}}
                            <h4 class="card-title">Daftar Pengajuan Lembur
                                @if (request('filter_start_date'))
                                    ({{ \Carbon\Carbon::parse(request('filter_start_date'))->format('d/m/Y') }} -
                                    {{ \Carbon\Carbon::parse(request('filter_end_date'))->format('d/m/Y') }})
                                @endif
                            </h4>
                        </div>
                        {{-- Quick Search (jika masih diperlukan) --}}
                        @if (Auth::user()->role !== 'personil')
                            <div class="col-md-4 col-12">
                                <form action="{{ route('overtimes.index') }}" method="GET">
                                    {{-- Sertakan filter lain agar tidak hilang saat quick search --}}
                                    <input type="hidden" name="filter_start_date"
                                        value="{{ request('filter_start_date') }}">
                                    <input type="hidden" name="filter_end_date" value="{{ request('filter_end_date') }}">
                                    <input type="hidden" name="filter_status" value="{{ request('filter_status') }}">
                                    <input type="hidden" name="filter_user_id" value="{{ request('filter_user_id') }}">
                                    <input type="hidden" name="filter_vendor_id"
                                        value="{{ request('filter_vendor_id') }}">
                                    <div class="input-group">
                                        <input type="text" class="form-control form-control-sm"
                                            placeholder="Quick Search Nama/Uraian..." name="search"
                                            value="{{ request('search') }}">
                                        <button class="btn btn-secondary btn-sm" type="submit"><i
                                                class="bi bi-search"></i></button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    {{-- Form Aksi Massal (jika ada) --}}
                    <form id="bulk-action-form" action="" method="POST">
                        @csrf
                        <div id="selected-ids-container"></div>
                        <div class="mb-3 d-flex flex-wrap gap-2">
                            {{-- Tombol Bulk --}}
                            @if (Auth::user()->role == 'admin')
                                <button type="button" class="btn btn-info btn-sm" id="bulk-pdf-btn" disabled>
                                    <i class="bi bi-printer-fill"></i> Unduh PDF (<span
                                        class="selected-count-display">0</span>)
                                </button>
                            @endif
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm" id="tableOvertimes">
                                {{-- table-sm --}}
                                <thead>
                                    <tr>
                                        <th style="width: 1%;"><input class="form-check-input" type="checkbox"
                                                id="select-all"></th>
                                        <th>No</th>
                                        @if (Auth::user()->role !== 'personil')
                                            <th>Nama</th>
                                        @endif
                                        <th>Tgl Lembur</th>
                                        <th>Jam</th>
                                        <th>Durasi</th>
                                        <th>Uraian</th>
                                        <th>Status</th>
                                        <th style="min-width: 100px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- ... (Loop @forelse seperti sebelumnya, pastikan colspan @empty sesuai) ... --}}
                                    @forelse ($overtimes as $index => $overtime)
                                        <tr>
                                            <td><input class="form-check-input item-checkbox" type="checkbox"
                                                    value="{{ $overtime->id }}"></td>
                                            <td>{{ $loop->iteration + $overtimes->firstItem() - 1 }}</td>
                                            @if (Auth::user()->role !== 'personil')
                                                <td>{{ $overtime->user->name ?? 'N/A' }}</td>
                                            @endif
                                            <td>{{ $overtime->tanggal_lembur ? $overtime->tanggal_lembur->format('d/m/Y') : '-' }}
                                            </td>
                                            <td>{{ $overtime->jam_mulai ? $overtime->jam_mulai->format('H:i') : '-' }} -
                                                {{ $overtime->jam_selesai ? $overtime->jam_selesai->format('H:i') : '-' }}
                                            </td>
                                            <td class="text-center">
                                                @if (!is_null($overtime->durasi_menit))
                                                    {{ floor($overtime->durasi_menit / 60) }}j
                                                    {{ $overtime->durasi_menit % 60 }}m
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td><span data-bs-toggle="tooltip"
                                                    title="{{ $overtime->uraian_pekerjaan }}">{{ Str::limit($overtime->uraian_pekerjaan, 50) }}</span>
                                            </td>
                                            <td> {{-- Status --}} @php
                                                $statusClass = '';
                                                $statusText = Str::title(str_replace('_', ' ', $overtime->status));
                                                switch ($overtime->status) {
                                                    case 'pending':
                                                        $statusClass = 'bg-warning';
                                                        $statusText = 'Menunggu Approval Asisten';
                                                        break;
                                                    case 'pending_manager_approval':
                                                        $statusClass = 'bg-info';
                                                        $statusText = 'Menunggu Approval Manager';
                                                        break;
                                                    case 'approved':
                                                        $statusClass = 'bg-success';
                                                        break;
                                                    case 'rejected':
                                                        $statusClass = 'bg-danger';
                                                        break;
                                                    case 'cancelled':
                                                        $statusClass = 'bg-secondary';
                                                        break;
                                                    default:
                                                        $statusClass = 'bg-dark';
                                                }
                                            @endphp <span
                                                    class="badge {{ $statusClass }}">{{ $statusText }}</span>
                                                @if ($overtime->status == 'rejected')
                                                    <br><small class="text-danger fst-italic" data-bs-toggle="tooltip"
                                                        title="Alasan Penolakan"><i class="bi bi-x-circle-fill"></i>
                                                        {{ $overtime->notes ?? '...' }} @if ($overtime->rejecter)
                                                            (Oleh: {{ $overtime->rejecter->name }})
                                                        @endif </small>
                                                @endif
                                            </td>
                                            <td class="text-nowrap"> {{-- Aksi --}} @php $aksiDitampilkan = false; @endphp @if (Auth::id() == $overtime->user_id && in_array($overtime->status, ['pending', 'rejected']))
                                                    <a href="{{ route('overtimes.edit', $overtime->id) }}"
                                                        class="btn btn-warning btn-sm d-inline-block me-1"
                                                        data-bs-toggle="tooltip" title="Edit Pengajuan"><i
                                                            class="bi bi-pencil-square"></i></a> @php $aksiDitampilkan = true; @endphp
                                                    @endif @if (Auth::id() == $overtime->user_id && in_array($overtime->status, ['pending', 'approved']))
                                                        <form action="{{ route('overtimes.cancel', $overtime->id) }}"
                                                            method="POST" class="d-inline-block delete-form"> @csrf
                                                            <button type="submit"
                                                                class="btn btn-secondary btn-sm me-1 delete-button"
                                                                data-bs-toggle="tooltip" title="Batalkan Pengajuan"><i
                                                                    class="bi bi-slash-circle"></i></button>
                                                        </form>
                                                        @php $aksiDitampilkan = true; @endphp
                                                        @endif @if ($overtime->status == 'approved' && Auth::user()->role == 'admin')
                                                            <a href="{{ route('overtimes.pdf', $overtime->id) }}"
                                                                target="_blank"
                                                                class="btn btn-light btn-sm d-inline-block me-1"
                                                                data-bs-toggle="tooltip" title="Unduh PDF"><i
                                                                    class="bi bi-printer-fill"></i></a> @php $aksiDitampilkan = true; @endphp
                                                            @endif @if (Auth::user()->role == 'admin')
                                                                <form
                                                                    action="{{ route('overtimes.destroy', $overtime->id) }}"
                                                                    method="POST" class="d-inline-block delete-form">
                                                                    @csrf @method('DELETE') <button type="submit"
                                                                        class="btn btn-danger btn-sm delete-button"
                                                                        data-bs-toggle="tooltip" title="Hapus Data"><i
                                                                            class="bi bi-trash"></i></button> </form>
                                                                @php $aksiDitampilkan = true; @endphp
                                                                @endif @if (!$aksiDitampilkan)
                                                                    -
                                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ Auth::user()->role !== 'personil' ? 10 : 9 }}"
                                                class="text-center">Tidak ada data pengajuan lembur ditemukan.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </form> {{-- Akhir Form Aksi Massal --}}

                    {{-- Link Pagination --}}
                    <div class="mt-3">
                        {{-- Pagination links akan otomatis menyertakan filter karena appends() di controller --}}
                        {{ $overtimes->links() }}
                    </div>
                </div>
            </div>
        </section>
    </div>
    {{-- Modal Konfirmasi Bulk Approve --}}
    <div class="modal fade" id="progressModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="progressModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="progressModalLabel">Memproses Permintaan...</h5>
                    {{-- Tidak ada tombol close, karena proses harus selesai --}}
                </div>
                <div class="modal-body">
                    <p>Sedang membuat file ZIP berisi PDF yang Anda pilih. Mohon tunggu sebentar...</p>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                            aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection



@push('js')
    {{-- Pastikan SweetAlert2 & Bootstrap JS sudah dimuat --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inisialisasi Tooltip
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });

            // --- Logika Checkbox & Tombol Bulk ---
            const selectAllCheckbox = document.getElementById('select-all');
            const itemCheckboxes = document.querySelectorAll('.item-checkbox');
            const bulkApproveBtn = document.getElementById('bulk-approve-btn');
            const bulkPdfBtn = document.getElementById('bulk-pdf-btn');
            const selectedCountSpans = document.querySelectorAll('.selected-count-display');
            const selectedIdsContainer = document.getElementById('selected-ids-container');
            const mainBulkForm = document.getElementById('bulk-action-form');
            const approvalLevelInput = document.getElementById('bulk-approval-level'); // Ambil input level
            const progressModalElement = document.getElementById('progressModal');
            const progressModal = progressModalElement ? new bootstrap.Modal(progressModalElement) : null;
            const csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector(
                'meta[name="csrf-token"]').getAttribute('content') : document.querySelector(
                'input[name="_token"]').value;

            function updateBulkButtons() {
                const selectedCheckboxes = document.querySelectorAll('.item-checkbox:checked');
                const count = selectedCheckboxes.length;
                selectedCountSpans.forEach(span => span.textContent = count);
                if (bulkApproveBtn) bulkApproveBtn.disabled = count === 0;
                if (bulkPdfBtn) bulkPdfBtn.disabled = count === 0;

                // Update input hidden
                if (selectedIdsContainer && mainBulkForm) {
                    selectedIdsContainer.innerHTML = ''; // Kosongkan
                    selectedCheckboxes.forEach(cb => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'selected_ids[]';
                        input.value = cb.value;
                        selectedIdsContainer.appendChild(input);
                    });
                }
            }

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    itemCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateBulkButtons();
                });
            }

            itemCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (!this.checked) {
                        if (selectAllCheckbox) selectAllCheckbox.checked = false;
                    } else {
                        if (selectAllCheckbox) selectAllCheckbox.checked = Array.from(
                            itemCheckboxes).every(cb => cb.checked);
                    }
                    updateBulkButtons();
                });
            });

            // Event listener untuk tombol Bulk Download PDF
            if (bulkPdfBtn && mainBulkForm && progressModal) { // Pastikan modal juga ada
                bulkPdfBtn.addEventListener('click', function() {
                    const selectedCheckboxes = document.querySelectorAll('.item-checkbox:checked');
                    const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);

                    if (selectedIds.length === 0) {
                        Swal.fire('Peringatan', 'Pilih setidaknya satu pengajuan lembur.', 'warning');
                        return;
                    }

                    // Simpan teks asli tombol
                    const originalButtonHTML = this.innerHTML;
                    // Nonaktifkan tombol
                    this.disabled = true;
                    // Tampilkan Modal Progress Bar
                    progressModal.show();

                    // Kirim request AJAX menggunakan Fetch
                    fetch("{{ route('overtimes.bulk.pdf') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/zip, application/json'
                            },
                            body: JSON.stringify({
                                selected_ids: selectedIds
                            })
                        })
                        .then(response => {
                            // Sembunyikan modal SEGERA setelah mendapat respons (sebelum download)
                            progressModal.hide();
                            if (response.ok && response.headers.get('content-type')?.includes(
                                    'application/zip')) {
                                return response.blob().then(blob => ({
                                    blob,
                                    response
                                }));
                            } else if (!response.ok) {
                                return response.json().then(err => {
                                    throw new Error(err.error || `Error ${response.status}`);
                                });
                            } else {
                                throw new Error('Respons server tidak valid.');
                            }
                        })
                        .then(({
                            blob,
                            response
                        }) => {
                            // Proses download seperti sebelumnya
                            const contentDisposition = response.headers.get('content-disposition');
                            let filename = 'bulk_lembur.zip';
                            if (contentDisposition) {
                                const filenameMatch = contentDisposition.match(/filename="?(.+)"?/i);
                                if (filenameMatch && filenameMatch.length === 2) filename =
                                    filenameMatch[1];
                            }
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.style.display = 'none';
                            a.href = url;
                            a.download = filename;
                            document.body.appendChild(a);
                            a.click();
                            window.URL.revokeObjectURL(url);
                            a.remove();

                            // Kembalikan tombol ke state semula setelah download dipicu
                            this.disabled = false;
                            this.innerHTML = originalButtonHTML;
                        })
                        .catch(error => {
                            console.error('Bulk PDF download error:', error);
                            // Pastikan modal disembunyikan jika ada error sebelum download
                            if (bootstrap.Modal.getInstance(progressModalElement)) {
                                progressModal.hide();
                            }
                            Swal.fire('Gagal', error.message || 'Gagal mengunduh file PDF.', 'error');
                            // Kembalikan tombol ke state semula jika error
                            this.disabled = false;
                            this.innerHTML = originalButtonHTML;
                        });
                });
            } else {
                if (!progressModal) console.warn('Elemen modal #progressModal tidak ditemukan.');
            }

            // Event listener untuk tombol Bulk Approve (Trigger Modal)
            if (bulkApproveBtn && mainBulkForm) {
                bulkApproveBtn.addEventListener('click', function() {
                    const selectedCount = Array.from(document.querySelectorAll('.item-checkbox:checked'))
                        .length;
                    if (selectedCount === 0) {
                        Swal.fire('Peringatan', 'Pilih setidaknya satu pengajuan.', 'warning');
                        return;
                    }
                    // Set action ke route bulk approve dan levelnya
                    mainBulkForm.action = "{{ route('overtimes.approval.bulk.approve') }}";
                    mainBulkForm.method = "POST"; // Route bulk approve pakai POST
                    // Hapus input _method jika ada
                    const methodInput = mainBulkForm.querySelector('input[name="_method"]');
                    if (methodInput) methodInput.remove();
                    // Set level approval (sudah ada di input hidden)
                    // Tampilkan modal konfirmasi (jika ada)
                    const approveModal = document.getElementById(
                        'bulkApproveConfirmModal'); // Sesuaikan ID modal
                    if (approveModal) {
                        const approveCountSpan = approveModal.querySelector(
                            '#bulkApproveCount'); // Sesuaikan ID span
                        if (approveCountSpan) approveCountSpan.textContent = selectedCount;
                        var modalInstance = new bootstrap.Modal(approveModal);
                        modalInstance.show();
                        // Submit form utama dilakukan oleh tombol di dalam modal
                    } else {
                        // Jika tidak ada modal, langsung submit dengan konfirmasi JS
                        const confirmation = confirm(
                            `Anda yakin ingin menyetujui ${selectedCount} pengajuan lembur terpilih?`);
                        if (confirmation) {
                            mainBulkForm.submit();
                        }
                    }
                });
            }

            // Event listener untuk konfirmasi hapus/batal individual
            const confirmationButtons = document.querySelectorAll('.delete-button');
            confirmationButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    const form = this.closest('form');
                    let title = 'Apakah Anda yakin?';
                    let text = "Aksi ini tidak dapat diurungkan!";
                    let confirmText = 'Ya, lanjutkan!';
                    let confirmColor = '#3085d6';
                    if (form.querySelector('input[name="_method"][value="DELETE"]')) {
                        title = 'Yakin Hapus Data?';
                        text = "Data akan dihapus permanen!";
                        confirmText = 'Ya, hapus!';
                        confirmColor = '#d33';
                    } else {
                        title = 'Yakin Batalkan Pengajuan?';
                        text = "Pengajuan ini akan dibatalkan.";
                        confirmText = 'Ya, batalkan!';
                        confirmColor = '#6c757d';
                    }
                    Swal.fire({
                        title: title,
                        text: text,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: confirmColor,
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: confirmText,
                        cancelButtonText: 'Tidak'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });

            // Update state tombol saat halaman load
            updateBulkButtons();
        });
    </script>
@endpush

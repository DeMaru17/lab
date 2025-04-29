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
                {{-- ... (Card Header dengan Search Form) ... --}}
                <div class="card-body">

                    {{-- Form untuk Aksi Massal (Approve) --}}
                    <form id="bulk-action-form" action="" method="POST"> {{-- Action diatur JS --}}
                        @csrf
                        <input type="hidden" name="approval_level"
                            value="{{ Auth::user()->jabatan == 'manager' ? 'manager' : 'asisten' }}">
                        {{-- Input hidden untuk ID terpilih (diisi oleh JS) --}}
                        <div id="selected-ids-container"></div>

                        <div class="mb-3 d-flex gap-2">
                            
                            {{-- Tombol Bulk Download PDF --}}
                            {{-- Hanya tampil jika Admin? Sesuaikan kondisi @if --}}
                            @if (Auth::user()->role == 'admin')
                                <button type="button" class="btn btn-info" id="bulk-pdf-btn" disabled>
                                    <i class="bi bi-printer-fill"></i> Unduh PDF Terpilih (<span
                                        class="selected-count-display">0</span>)
                                </button>
                            @endif
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="tableOvertimes">
                                <thead>
                                    <tr>
                                        <th style="width: 1%;">
                                            <input class="form-check-input" type="checkbox" id="select-all">
                                        </th>
                                        <th>No</th>
                                        @if (Auth::user()->role !== 'personil')
                                            <th>Nama Karyawan</th>
                                        @endif
                                        <th>Tanggal Lembur</th>
                                        <th>Jam Mulai</th>
                                        <th>Jam Selesai</th>
                                        <th>Durasi</th>
                                        <th>Uraian Pekerjaan</th>
                                        <th>Status</th>
                                        <th style="min-width: 100px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($overtimes as $index => $overtime)
                                        @php
                                            $userTotal = $monthlyTotals[$overtime->user_id] ?? 0;
                                            $isOverLimit = $userTotal >= 3240;
                                        @endphp
                                        <tr class="{{ $isOverLimit && Auth::user()->role !== 'personil' ? 'table-danger' : '' }}"
                                            @if ($isOverLimit && Auth::user()->role !== 'personil') data-bs-toggle="tooltip" title="Total lembur bulan ini: {{ round($userTotal / 60, 1) }} jam (Melebihi batas 54 jam)" @endif>
                                            <td>
                                                {{-- Checkbox per item --}}
                                                <input class="form-check-input item-checkbox" type="checkbox"
                                                    value="{{ $overtime->id }}">
                                            </td>
                                            {{-- ... (Kolom data lainnya seperti sebelumnya) ... --}}
                                            <td>{{ $loop->iteration + $overtimes->firstItem() - 1 }}</td>
                                            @if (Auth::user()->role !== 'personil')
                                                <td>{{ $overtime->user->name ?? 'N/A' }}</td>
                                            @endif
                                            <td>{{ $overtime->tanggal_lembur ? $overtime->tanggal_lembur->format('d/m/Y') : '-' }}
                                            </td>
                                            <td>{{ $overtime->jam_mulai ? $overtime->jam_mulai->format('H:i') : '-' }}</td>
                                            <td>{{ $overtime->jam_selesai ? $overtime->jam_selesai->format('H:i') : '-' }}
                                            </td>
                                            <td class="text-center">
                                                @if (!is_null($overtime->durasi_menit))
                                                    {{ floor($overtime->durasi_menit / 60) }}j
                                                    {{ $overtime->durasi_menit % 60 }}m
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td> <span data-bs-toggle="tooltip"
                                                    title="{{ $overtime->uraian_pekerjaan }}">{{ Str::limit($overtime->uraian_pekerjaan, 50) }}</span>
                                            </td>
                                            <td> {{-- Status --}}
                                                @php
                                                    $statusClass = 'bg-dark';
                                                    $statusText = 'Status Tidak Diketahui';

                                                    if (isset($overtime) && $overtime->status) {
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
                                                                $statusText = 'Disetujui';
                                                                break;
                                                            case 'rejected':
                                                                $statusClass = 'bg-danger';
                                                                $statusText = 'Ditolak';
                                                                break;
                                                            case 'cancelled':
                                                                $statusClass = 'bg-secondary';
                                                                $statusText = 'Dibatalkan';
                                                                break;
                                                        }
                                                    }
                                                @endphp

                                                <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                                                @if ($overtime->status == 'rejected')
                                                    <br><small class="text-danger fst-italic" data-bs-toggle="tooltip"
                                                        title="Alasan Penolakan"><i class="bi bi-x-circle-fill"></i>
                                                        {{ $overtime->notes ?? '...' }} @if ($overtime->rejecter)
                                                            (Oleh: {{ $overtime->rejecter->name }})
                                                        @endif </small>
                                                @endif
                                            </td>
                                            <td class="text-nowrap">
                                                {{-- ... (Tombol Aksi Individual: Edit, Cancel, Hapus, PDF tunggal jika perlu) ... --}}
                                                @php $aksiDitampilkan = false; @endphp
                                                @if (Auth::id() == $overtime->user_id && in_array($overtime->status, ['pending', 'rejected']))
                                                    <a href="{{ route('overtimes.edit', $overtime->id) }}"
                                                        class="btn btn-warning btn-sm d-inline-block me-1"
                                                        data-bs-toggle="tooltip" title="Edit Pengajuan"><i
                                                            class="bi bi-pencil-square"></i></a> @php $aksiDitampilkan = true; @endphp
                                                @endif
                                                @if (Auth::id() == $overtime->user_id && in_array($overtime->status, ['pending', 'approved']))
                                                    <form action="{{ route('overtimes.cancel', $overtime->id) }}"
                                                        method="POST" class="d-inline-block delete-form"> @csrf <button
                                                            type="submit"
                                                            class="btn btn-secondary btn-sm me-1 delete-button"
                                                            data-bs-toggle="tooltip" title="Batalkan Pengajuan"><i
                                                                class="bi bi-slash-circle"></i></button> </form>
                                                    @php $aksiDitampilkan = true; @endphp
                                                @endif
                                                @if ($overtime->status == 'approved' && Auth::user()->role == 'admin')
                                                    <a href="{{ route('overtimes.pdf', $overtime->id) }}" target="_blank"
                                                        class="btn btn-light btn-sm d-inline-block me-1"
                                                        data-bs-toggle="tooltip" title="Unduh PDF"><i
                                                            class="bi bi-printer-fill"></i></a> @php $aksiDitampilkan = true; @endphp
                                                @endif
                                                @if (Auth::user()->role == 'admin')
                                                    <form action="{{ route('overtimes.destroy', $overtime->id) }}"
                                                        method="POST" class="d-inline-block delete-form"> @csrf
                                                        @method('DELETE') <button type="submit"
                                                            class="btn btn-danger btn-sm delete-button"
                                                            data-bs-toggle="tooltip" title="Hapus Data"><i
                                                                class="bi bi-trash"></i></button> </form> @php $aksiDitampilkan = true; @endphp
                                                @endif
                                                @if (!$aksiDitampilkan)
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ Auth::user()->role !== 'personil' ? 10 : 9 }}"
                                                class="text-center">Tidak ada data pengajuan lembur.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </form> {{-- Akhir Form Aksi Massal --}}

                    {{-- Link Pagination --}}
                    <div class="mt-3">
                        {{ $overtimes->links() }}
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection



@push('js')
    {{-- Pastikan SweetAlert2 & Bootstrap JS sudah dimuat di layout --}}
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
            const bulkApproveBtn = document.getElementById(
                'bulk-approve-btn'); // Tombol approve (jika ada di halaman ini)
            const bulkPdfBtn = document.getElementById('bulk-pdf-btn'); // Tombol PDF
            const selectedCountSpans = document.querySelectorAll('.selected-count-display'); // Semua span count
            const selectedIdsContainer = document.getElementById(
                'selected-ids-container'); // Container input hidden
            const mainBulkForm = document.getElementById('bulk-action-form'); // Form utama

            function updateBulkButtons() {
                const selectedCheckboxes = document.querySelectorAll('.item-checkbox:checked');
                const count = selectedCheckboxes.length;

                // Update semua span count
                selectedCountSpans.forEach(span => span.textContent = count);

                // Enable/disable tombol berdasarkan count
                if (bulkApproveBtn) bulkApproveBtn.disabled = count === 0;
                if (bulkPdfBtn) bulkPdfBtn.disabled = count === 0;

                // Update input hidden di form utama
                if (selectedIdsContainer && mainBulkForm) {
                    selectedIdsContainer.innerHTML = ''; // Kosongkan dulu
                    selectedCheckboxes.forEach(cb => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'selected_ids[]'; // Nama array
                        input.value = cb.value;
                        selectedIdsContainer.appendChild(input);
                    });
                }
            }

            // Event listener untuk checkbox "Select All"
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    itemCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateBulkButtons();
                });
            }

            // Event listener untuk checkbox per item
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
            if (bulkPdfBtn && mainBulkForm) {
                bulkPdfBtn.addEventListener('click', function() {
                    const selectedCount = parseInt(document.querySelector('.selected-count-display')
                        .textContent, 10);
                    if (selectedCount === 0) {
                        Swal.fire('Peringatan', 'Pilih setidaknya satu pengajuan lembur untuk diunduh.',
                            'warning');
                        return;
                    }

                    // Set action form ke route bulk PDF dan submit
                    mainBulkForm.action =
                        "{{ route('overtimes.bulk.pdf') }}"; // Ganti nama route jika berbeda
                    mainBulkForm.submit();
                });
            }

            // Event listener untuk tombol Bulk Approve (jika ada, trigger modal)
            if (bulkApproveBtn) {
                bulkApproveBtn.addEventListener('click', function() {
                    // Logika untuk menampilkan modal konfirmasi approve
                    // (Kode ini mungkin sudah ada jika Anda include _modal_scripts)
                    const selectedCount = parseInt(document.getElementById('selected-count').textContent,
                        10); // Sesuaikan ID span
                    if (selectedCount > 0) {
                        // Update count di modal approve (jika modalnya ada)
                        const approveCountSpan = document.getElementById(
                            'bulkApproveCount'); // Sesuaikan ID
                        if (approveCountSpan) approveCountSpan.textContent = selectedCount;
                        // Tampilkan modal approve (jika modalnya ada)
                        // var approveModalInstance = new bootstrap.Modal(document.getElementById('bulkApproveConfirmModal'));
                        // approveModalInstance.show();
                    } else {
                        Swal.fire('Peringatan', 'Pilih setidaknya satu pengajuan untuk disetujui.',
                            'warning');
                    }
                });
            }

            // Event listener untuk konfirmasi hapus/batal individual (Kode dari sebelumnya)
            const confirmationButtons = document.querySelectorAll('.delete-button');
            confirmationButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    /* ... logika SweetAlert ... */
                });
            });

            // Update state tombol saat halaman load
            updateBulkButtons();
        });
    </script>
@endpush

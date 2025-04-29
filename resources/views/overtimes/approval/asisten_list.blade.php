{{-- resources/views/overtimes/approval/asisten_list.blade.php --}}
@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@section('content')
    <div id="main">
        {{-- Header Halaman & Breadcrumb --}}
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Persetujuan Lembur Asisten Manager</h3>
                        <p class="text-subtitle text-muted">Daftar pengajuan lembur yang menunggu persetujuan Anda.</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Persetujuan Lembur Asisten</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        {{-- Akhir Header Halaman --}}

        {{-- Bagian Tabel Daftar Pengajuan --}}
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Menunggu Persetujuan Anda</h4>
                </div>
                <div class="card-body">
                    {{-- Form untuk Aksi Massal --}}
                    {{-- Kita gunakan form ini untuk mengirim ID yang dipilih --}}
                    <form id="bulk-approve-form-asisten" action="{{ route('overtimes.approval.bulk.approve') }}"
                        method="POST"> {{-- Buat route ini nanti --}}
                        @csrf
                        <input type="hidden" name="approval_level" value="asisten"> {{-- Tandai level --}}
                        {{-- Tombol Aksi Massal --}}
                        <div class="mb-3">
                            <button type="button" class="btn btn-success" id="bulk-approve-btn-asisten"
                                data-bs-toggle="modal" data-bs-target="#bulkApproveConfirmModal" disabled>
                                <i class="bi bi-check-lg"></i> Setujui yang Dipilih (<span
                                    id="selected-count-asisten">0</span>)
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="tableApprovalOvertimeAsisten">
                                <thead>
                                    <tr>
                                        {{-- Checkbox Select All --}}
                                        <th style="width: 1%;">
                                            <input class="form-check-input" type="checkbox" id="select-all-asisten">
                                        </th>
                                        <th>No</th>
                                        <th>Tgl Pengajuan</th>
                                        <th>Nama Pengaju</th>
                                        <th>Jabatan</th>
                                        <th>Tanggal Lembur</th>
                                        <th>Jam</th>
                                        <th>Durasi</th>
                                        <th>Uraian</th>
                                        <th style="min-width: 80px;">Aksi</th> {{-- Lebar dikurangi sedikit --}}
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($pendingOvertimes as $index => $overtime)
                                        @php
                                            $userTotal = $monthlyTotals[$overtime->user_id] ?? 0;
                                            $isOverLimit = $userTotal >= 3240;
                                        @endphp
                                        <tr class="{{ $isOverLimit ? 'table-warning' : '' }}"
                                            @if ($isOverLimit) data-bs-toggle="tooltip" title="Karyawan ini sudah > 54 jam lembur bulan ini ({{ round($userTotal / 60, 1) }} jam)" @endif>
                                            {{-- Checkbox per Baris --}}
                                            <td>
                                                <input class="form-check-input item-checkbox-asisten" type="checkbox"
                                                    name="selected_ids[]" value="{{ $overtime->id }}">
                                            </td>
                                            <td>{{ $loop->iteration + $pendingOvertimes->firstItem() - 1 }}</td>
                                            <td>{{ $overtime->created_at->format('d/m/Y H:i') }}</td>
                                            <td>{{ $overtime->user->name ?? 'N/A' }}</td>
                                            <td>{{ $overtime->user->jabatan ?? 'N/A' }}</td>
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
                                            <td>
                                                <span data-bs-toggle="tooltip" title="{{ $overtime->uraian_pekerjaan }}">
                                                    {{ Str::limit($overtime->uraian_pekerjaan, 40) }}
                                                </span>
                                            </td>
                                            <td class="text-nowrap">
                                                {{-- Tombol Tolak (Trigger Modal) - Tetap ada --}}
                                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal"
                                                    data-bs-target="#rejectOvertimeModal"
                                                    data-overtime-id="{{ $overtime->id }}"
                                                    data-user-name="{{ $overtime->user->name ?? 'N/A' }}"
                                                    data-bs-toggle="tooltip" title="Tolak Pengajuan">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                                {{-- Tombol Approve individual dihapus, diganti checkbox --}}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center">Tidak ada pengajuan lembur yang menunggu
                                                persetujuan Anda.</td> {{-- Sesuaikan colspan --}}
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </form> {{-- Akhir Form Aksi Massal --}}

                    {{-- Link Pagination --}}
                    <div class="mt-3">
                        {{ $pendingOvertimes->links() }}
                    </div>
                </div>
            </div>
        </section>
        {{-- Akhir Bagian Tabel --}}

        {{-- Include Modal Reject --}}
        @include('overtimes.approval._reject_modal')

        {{-- Include Modal Approve --}}
        @include('overtimes.approval._approve_modal')

        @include('overtimes.approval._bulk_approve_confirm_modal')

    </div>
@endsection

@push('js')
    {{-- Include script untuk kedua modal --}}
    @include('overtimes.approval._modal_scripts')
@endpush

@push('js')
    {{-- Include script untuk modal reject & tooltip --}}
    @include('overtimes.approval._modal_scripts') {{-- Pastikan script modal reject masih ada --}}
    {{-- Tambahkan script untuk handle checkbox & bulk approve --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('select-all-asisten');
            const itemCheckboxes = document.querySelectorAll('.item-checkbox-asisten');
            const bulkApproveBtn = document.getElementById('bulk-approve-btn-asisten');
            const selectedCountSpan = document.getElementById('selected-count-asisten');
            const bulkApproveForm = document.getElementById('bulk-approve-form-asisten');

            function updateButtonState() {
                const selectedCheckboxes = document.querySelectorAll('.item-checkbox-asisten:checked');
                const count = selectedCheckboxes.length;
                selectedCountSpan.textContent = count;
                bulkApproveBtn.disabled = count === 0; // Enable tombol jika ada yg dipilih
            }

            // Event listener untuk checkbox "Select All"
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    itemCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateButtonState();
                });
            }

            // Event listener untuk checkbox per item
            itemCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    // Jika ada item yg tidak tercentang, batalkan centang "Select All"
                    if (!this.checked) {
                        selectAllCheckbox.checked = false;
                    }
                    // Cek apakah semua item tercentang
                    else {
                        const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
                        selectAllCheckbox.checked = allChecked;
                    }
                    updateButtonState();
                });
            });

            // Konfirmasi sebelum submit bulk approve
            if (bulkApproveForm) {
                bulkApproveForm.addEventListener('submit', function(event) {
                    const selectedCount = parseInt(selectedCountSpan.textContent, 10);
                    if (selectedCount === 0) {
                        alert('Pilih setidaknya satu pengajuan untuk disetujui.');
                        event.preventDefault();
                        return;
                    }

                    const confirmation = confirm(
                        `Anda yakin ingin menyetujui ${selectedCount} pengajuan lembur yang dipilih (Level 1)?`
                    );
                    if (!confirmation) {
                        event.preventDefault(); // Batalkan submit jika tidak dikonfirmasi
                    }
                    // Jika dikonfirmasi, form akan tersubmit secara normal
                });
            }

            // Update state tombol saat halaman pertama kali load (jika ada old input/error)
            updateButtonState();
        });
    </script>
@endpush
